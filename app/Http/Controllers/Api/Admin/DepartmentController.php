<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\DepartmentService;
use App\Services\EmployeeDirectoryService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * HR-only mobile admin API for department management - mirrors
 * App\Livewire\Admin\Departments::rules() exactly (same two rules: unique
 * name, and a manager can only head one department at a time).
 */
final class DepartmentController extends Controller
{
    public function index(Request $request, DepartmentService $departments, EmployeeDirectoryService $employees): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        return response()->json([
            'data' => [
                'departments' => $departments->list(),
                // Mapped down to id/name/role only - managerOptions() returns
                // every raw user column (including the password hash) for
                // internal Livewire use, same reasoning as EmployeeController.
                'managers' => array_map(
                    fn (object $manager) => ['id' => $manager->id, 'name' => $manager->name, 'role' => $manager->role],
                    $employees->managerOptions(null),
                ),
            ],
        ]);
    }

    public function store(Request $request, DepartmentService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $validated = $this->validateDepartment($request, null);

        $id = $service->create($validated['name'], $validated['manager_id'] ?? null);

        return response()->json(['data' => ['id' => $id]]);
    }

    public function update(Request $request, int $id, DepartmentService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $validated = $this->validateDepartment($request, $id);

        $service->update($id, $validated['name'], $validated['manager_id'] ?? null);

        return response()->json(['data' => ['message' => 'Department updated.']]);
    }

    public function toggleActive(Request $request, int $id, DepartmentService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $department = DB::table('departments')->where('company_id', Tenant::id())->where('id', $id)->first();
        abort_if($department === null, 404);

        $service->setActive($id, ! (bool) $department->is_active);

        return response()->json(['data' => ['message' => $department->is_active ? 'Department deactivated.' : 'Department reactivated.']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateDepartment(Request $request, ?int $editingId): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->where('company_id', Tenant::id())->ignore($editingId)],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['manager', 'hr'])->where('is_active', true))->where('company_id', Tenant::id()),
                Rule::unique('departments', 'manager_id')->where('company_id', Tenant::id())->ignore($editingId),
            ],
        ], [
            'manager_id.unique' => 'This manager already heads another department.',
        ]);
    }
}
