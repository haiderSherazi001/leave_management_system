<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Services\DepartmentService;
use App\Services\EmployeeDirectoryService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Employees extends Component
{
    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $roleFilter = '';

    #[Url(as: 'department', history: true)]
    public ?int $departmentFilter = null;

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'employee';

    public ?int $departmentId = null;

    public ?int $managerId = null;

    public string $joinedAt = '';

    public bool $showForm = false;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->isHr(), 403);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            // Never required: creating an employee no longer takes a
            // password from HR at all (an invite email handles that) - this
            // only still applies when editing, as HR's optional manual
            // override (e.g. if an invite email never arrives).
            'password' => ['nullable', 'min:8'],
            'role' => [
                'required',
                Rule::enum(UserRole::class),
                // Changing a manager/HR's role away from manager-or-hr while
                // they still head a department or have direct reports would
                // leave departments.manager_id / other users' manager_id
                // dangling — pointing at someone no longer eligible to be
                // either. Force reassigning those first, same as the app
                // already requires for the reverse direction (assigning a
                // manager into an already-headed department).
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->editingId === null || in_array($value, ['manager', 'hr'], true)) {
                        return;
                    }

                    if (DB::table('departments')->where('manager_id', $this->editingId)->exists()) {
                        $fail('This person heads a department — reassign it before changing their role.');

                        return;
                    }

                    if (DB::table('users')->where('manager_id', $this->editingId)->where('is_active', true)->exists()) {
                        $fail('This person has employees reporting to them — reassign those first.');
                    }
                },
            ],
            'departmentId' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id')->where('is_active', true),
                // A department has exactly one manager (departments.manager_id).
                // A manager-role employee may only be placed into a department
                // that has no manager yet, or the one they already head —
                // never a department someone else already manages, which would
                // otherwise leave the same department pointing at two managers.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $this->role !== UserRole::Manager->value) {
                        return;
                    }

                    $currentManagerId = DB::table('departments')->where('id', $value)->value('manager_id');

                    if ($currentManagerId !== null && $currentManagerId !== $this->editingId) {
                        $fail('This department already has a different manager assigned.');
                    }
                },
            ],
            'managerId' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['manager', 'hr'])->where('is_active', true)),
                Rule::notIn([$this->editingId]),
                // Strict top-down hierarchy: HR is never managed by anyone
                // (save() already forces this to null before we get here —
                // this is the backstop); a manager may only be managed by HR,
                // never another manager, since manager-to-manager reporting
                // isn't a concept this app models (there's no multi-level
                // approval yet); an employee may be managed by either.
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    if ($this->role === UserRole::Hr->value) {
                        $fail('HR accounts do not have a manager.');

                        return;
                    }

                    if ($this->role === UserRole::Manager->value
                        && DB::table('users')->where('id', $value)->value('role') === UserRole::Manager->value) {
                        $fail('A manager cannot be assigned as another manager\'s manager.');
                    }
                },
            ],
            'joinedAt' => ['required', 'date'],
        ];
    }

    public function startCreate(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'departmentId', 'managerId', 'joinedAt']);
        $this->role = UserRole::Employee->value;
        $this->showForm = true;
        $this->dispatch('form-opened');
    }

    public function edit(int $userId): void
    {
        $user = DB::table('users')->where('id', $userId)->first();

        if ($user === null) {
            return;
        }

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->role;
        $this->departmentId = $user->department_id;
        // Self-healing: an HR record should never carry a manager_id, even if
        // one was set before this rule existed.
        $this->managerId = $user->role === UserRole::Hr->value ? null : $user->manager_id;
        $this->joinedAt = $user->joined_at;
        $this->showForm = true;
        $this->dispatch('form-opened');
    }

    /**
     * HR accounts never have a manager — clear it the moment HR is picked,
     * so the (now disabled) manager dropdown reflects that immediately
     * rather than waiting for save() to force it.
     */
    public function updatedRole(string $value): void
    {
        if ($value === UserRole::Hr->value) {
            $this->managerId = null;
        }
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset(['editingId', 'name', 'email', 'password', 'role', 'departmentId', 'managerId', 'joinedAt']);
    }

    public function save(EmployeeDirectoryService $service): void
    {
        if ($this->role === UserRole::Hr->value) {
            $this->managerId = null;
        }

        $validated = $this->validate();

        if ($this->editingId === null) {
            $service->create(
                name: $validated['name'],
                email: $validated['email'],
                role: $validated['role'],
                departmentId: $validated['departmentId'],
                managerId: $validated['managerId'],
                joinedAt: $validated['joinedAt'],
            );
        } else {
            $service->update(
                userId: $this->editingId,
                name: $validated['name'],
                email: $validated['email'],
                password: $validated['password'] !== '' ? $validated['password'] : null,
                role: $validated['role'],
                departmentId: $validated['departmentId'],
                managerId: $validated['managerId'],
                joinedAt: $validated['joinedAt'],
            );
        }

        $this->cancel();
    }

    public function toggleActive(int $userId, EmployeeDirectoryService $service): void
    {
        $this->errorMessage = null;
        $user = DB::table('users')->where('id', $userId)->first();

        if ($user === null) {
            return;
        }

        try {
            $service->setActive($userId, ! (bool) $user->is_active);
        } catch (DomainException $exception) {
            $this->errorMessage = $exception->getMessage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'roleFilter', 'departmentFilter', 'statusFilter']);
    }

    public function render(EmployeeDirectoryService $service, DepartmentService $departments): View
    {
        return view('livewire.admin.employees', [
            'employees' => $service->list(
                search: $this->search,
                role: $this->roleFilter,
                departmentId: $this->departmentFilter,
                status: $this->statusFilter,
            ),
            'departments' => $departments->options($this->departmentId),
            'allDepartments' => $departments->list(),
            'managers' => $service->managerOptions($this->managerId, $this->role),
            'roles' => UserRole::cases(),
        ]);
    }
}
