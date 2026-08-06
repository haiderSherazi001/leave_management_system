<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\DepartmentService;
use App\Services\EmployeeDirectoryService;
use App\Support\Tenant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * HR-only mobile admin API for employee management - mirrors
 * App\Livewire\Admin\Employees exactly (same EmployeeDirectoryService calls,
 * same validation rules, translated from Livewire's stateful properties to
 * plain request input), so the hierarchy/uniqueness rules enforced on the
 * web admin screen apply identically here. Validation intentionally
 * duplicated rather than extracted into the service, to avoid touching the
 * already-shipped, already-tested Livewire component for this pass.
 */
final class EmployeeController extends Controller
{
    public function index(Request $request, EmployeeDirectoryService $employees, DepartmentService $departments): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        // departments->options()/employees->managerOptions() return raw
        // DB::table()->get() rows (every column, including the password
        // hash on the managers side) - fine for Livewire's server-side
        // rendering, which never serializes them, but not safe to return
        // directly as JSON. Mapped down to only what the mobile form needs.
        return response()->json([
            'data' => [
                'employees' => $employees->list(),
                'departments' => array_map(
                    fn (object $department) => ['id' => $department->id, 'name' => $department->name],
                    $departments->options(null),
                ),
                'managers' => array_map(
                    fn (object $manager) => ['id' => $manager->id, 'name' => $manager->name, 'role' => $manager->role],
                    $employees->managerOptions(null),
                ),
                'roles' => array_map(fn (UserRole $role) => ['value' => $role->value, 'label' => $role->label()], UserRole::cases()),
            ],
        ]);
    }

    public function store(Request $request, EmployeeDirectoryService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $validated = $this->validateEmployee($request, null);

        $user = $service->create(
            name: $validated['name'],
            email: $validated['email'],
            role: $validated['role'],
            departmentId: $validated['department_id'] ?? null,
            managerId: $validated['manager_id'] ?? null,
            joinedAt: $validated['joined_at'],
        );

        return response()->json(['data' => ['id' => $user->id]]);
    }

    public function update(Request $request, int $id, EmployeeDirectoryService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $validated = $this->validateEmployee($request, $id);

        $service->update(
            userId: $id,
            name: $validated['name'],
            email: $validated['email'],
            password: ($validated['password'] ?? '') !== '' ? $validated['password'] : null,
            role: $validated['role'],
            departmentId: $validated['department_id'] ?? null,
            managerId: $validated['manager_id'] ?? null,
            joinedAt: $validated['joined_at'],
        );

        return response()->json(['data' => ['message' => 'Employee updated.']]);
    }

    public function toggleActive(Request $request, int $id, EmployeeDirectoryService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $user = DB::table('users')->where('company_id', Tenant::id())->where('id', $id)->first();
        abort_if($user === null, 404);

        $service->setActive($id, ! (bool) $user->is_active);

        return response()->json(['data' => ['message' => $user->is_active ? 'Employee deactivated.' : 'Employee reactivated.']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEmployee(Request $request, ?int $editingId): array
    {
        // HR accounts never have a manager - forced here exactly like
        // Employees::save() forces it before validate(), so the backstop
        // rule below should never actually fire in practice.
        if ($request->input('role') === UserRole::Hr->value) {
            $request->merge(['manager_id' => null]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($editingId)],
            // Never required: creating an employee no longer takes a
            // password from HR at all (an invite email handles that) - this
            // only still applies when editing, as HR's optional manual
            // override.
            'password' => ['nullable', 'min:8'],
            'role' => [
                'required',
                Rule::enum(UserRole::class),
                $this->roleChangeRule($editingId),
            ],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true)->where('company_id', Tenant::id()),
                $this->departmentConflictRule($request, $editingId),
            ],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['manager', 'hr'])->where('is_active', true))->where('company_id', Tenant::id()),
                Rule::notIn([$editingId]),
                $this->managerHierarchyRule($request),
            ],
            'joined_at' => ['required', 'date'],
        ]);
    }

    /**
     * Changing a manager/HR's role away from manager-or-hr while they still
     * head a department or have direct reports would leave
     * departments.manager_id / other users' manager_id dangling.
     */
    private function roleChangeRule(?int $editingId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($editingId): void {
            if ($editingId === null || in_array($value, ['manager', 'hr'], true)) {
                return;
            }

            if (DB::table('departments')->where('company_id', Tenant::id())->where('manager_id', $editingId)->exists()) {
                $fail('This person heads a department — reassign it before changing their role.');

                return;
            }

            if (DB::table('users')->where('company_id', Tenant::id())->where('manager_id', $editingId)->where('is_active', true)->exists()) {
                $fail('This person has employees reporting to them — reassign those first.');
            }
        };
    }

    /**
     * A manager-role employee may only be placed into a department that has
     * no manager yet, or the one they already head.
     */
    private function departmentConflictRule(Request $request, ?int $editingId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request, $editingId): void {
            if ($value === null || $request->input('role') !== UserRole::Manager->value) {
                return;
            }

            $currentManagerId = DB::table('departments')->where('company_id', Tenant::id())->where('id', $value)->value('manager_id');

            if ($currentManagerId !== null && $currentManagerId !== $editingId) {
                $fail('This department already has a different manager assigned.');
            }
        };
    }

    /**
     * Strict top-down hierarchy: HR is never managed by anyone, a manager
     * may only be managed by HR (never another manager), an employee may be
     * managed by either.
     */
    private function managerHierarchyRule(Request $request): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($request): void {
            if ($value === null) {
                return;
            }

            if ($request->input('role') === UserRole::Hr->value) {
                $fail('HR accounts do not have a manager.');

                return;
            }

            if ($request->input('role') === UserRole::Manager->value
                && DB::table('users')->where('company_id', Tenant::id())->where('id', $value)->value('role') === UserRole::Manager->value) {
                $fail('A manager cannot be assigned as another manager\'s manager.');
            }
        };
    }
}
