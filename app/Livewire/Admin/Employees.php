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
use Livewire\Component;

#[Layout('layouts.app')]
class Employees extends Component
{
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
            'password' => [$this->editingId === null ? 'required' : 'nullable', 'min:8'],
            'role' => ['required', Rule::enum(UserRole::class)],
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
            ],
            'joinedAt' => ['required', 'date'],
        ];
    }

    public function startCreate(): void
    {
        $this->reset(['editingId', 'name', 'email', 'password', 'departmentId', 'managerId', 'joinedAt']);
        $this->role = UserRole::Employee->value;
        $this->showForm = true;
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
        $this->managerId = $user->manager_id;
        $this->joinedAt = $user->joined_at;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset(['editingId', 'name', 'email', 'password', 'role', 'departmentId', 'managerId', 'joinedAt']);
    }

    public function save(EmployeeDirectoryService $service): void
    {
        $validated = $this->validate();

        if ($this->editingId === null) {
            $service->create(
                name: $validated['name'],
                email: $validated['email'],
                password: $validated['password'],
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

    public function render(EmployeeDirectoryService $service, DepartmentService $departments): View
    {
        return view('livewire.admin.employees', [
            'employees' => $service->list(),
            'departments' => $departments->options($this->departmentId),
            'managers' => $service->managerOptions($this->managerId),
            'roles' => UserRole::cases(),
        ]);
    }
}
