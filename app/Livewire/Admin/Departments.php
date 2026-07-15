<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\DepartmentService;
use App\Services\EmployeeDirectoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Departments extends Component
{
    public ?int $editingId = null;

    public string $name = '';

    public ?int $managerId = null;

    public bool $showForm = false;

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
            'name' => ['required', 'string', 'max:100', Rule::unique('departments', 'name')->ignore($this->editingId)],
            'managerId' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->whereIn('role', ['manager', 'hr'])->where('is_active', true)),
            ],
        ];
    }

    public function startCreate(): void
    {
        $this->reset(['editingId', 'name', 'managerId']);
        $this->showForm = true;
    }

    public function edit(int $departmentId): void
    {
        $department = DB::table('departments')->where('id', $departmentId)->first();

        if ($department === null) {
            return;
        }

        $this->editingId = $department->id;
        $this->name = $department->name;
        $this->managerId = $department->manager_id;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset(['editingId', 'name', 'managerId']);
    }

    public function save(DepartmentService $service): void
    {
        $validated = $this->validate();

        if ($this->editingId === null) {
            $service->create($validated['name'], $validated['managerId']);
        } else {
            $service->update($this->editingId, $validated['name'], $validated['managerId']);
        }

        $this->cancel();
    }

    public function toggleActive(int $departmentId, DepartmentService $service): void
    {
        $department = DB::table('departments')->where('id', $departmentId)->first();

        if ($department === null) {
            return;
        }

        $service->setActive($departmentId, ! (bool) $department->is_active);
    }

    public function render(DepartmentService $service, EmployeeDirectoryService $employees): View
    {
        return view('livewire.admin.departments', [
            'departments' => $service->list(),
            'managers' => $employees->managerOptions($this->managerId),
        ]);
    }
}
