<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\LeaveTypeService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class LeaveTypes extends Component
{
    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $code = '';

    public int $yearlyAllocationDays = 0;

    public bool $carryForwardEnabled = false;

    public ?int $carryForwardMaxDays = null;

    public string $description = '';

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
            'name' => ['required', 'string', 'max:100', Rule::unique('leave_types', 'name')->ignore($this->editingId)],
            'code' => ['required', 'string', 'max:20', Rule::unique('leave_types', 'code')->ignore($this->editingId)],
            'yearlyAllocationDays' => ['required', 'integer', 'min:0', 'max:365'],
            'carryForwardEnabled' => ['boolean'],
            'carryForwardMaxDays' => ['nullable', 'integer', 'min:0', 'max:365'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function startCreate(): void
    {
        $this->reset(['editingId', 'name', 'code', 'yearlyAllocationDays', 'carryForwardEnabled', 'carryForwardMaxDays', 'description']);
        $this->showForm = true;
        $this->dispatch('form-opened');
    }

    public function edit(int $leaveTypeId): void
    {
        $leaveType = DB::table('leave_types')->where('id', $leaveTypeId)->first();

        if ($leaveType === null) {
            return;
        }

        $this->editingId = $leaveType->id;
        $this->name = $leaveType->name;
        $this->code = $leaveType->code;
        $this->yearlyAllocationDays = $leaveType->yearly_allocation_days;
        $this->carryForwardEnabled = (bool) $leaveType->carry_forward_enabled;
        $this->carryForwardMaxDays = $leaveType->carry_forward_max_days;
        $this->description = $leaveType->description ?? '';
        $this->showForm = true;
        $this->dispatch('form-opened');
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset(['editingId', 'name', 'code', 'yearlyAllocationDays', 'carryForwardEnabled', 'carryForwardMaxDays', 'description']);
    }

    public function save(LeaveTypeService $service): void
    {
        $validated = $this->validate();

        if ($this->editingId === null) {
            $service->create(
                name: $validated['name'],
                code: $validated['code'],
                yearlyAllocationDays: $validated['yearlyAllocationDays'],
                carryForwardEnabled: $validated['carryForwardEnabled'],
                carryForwardMaxDays: $validated['carryForwardMaxDays'],
                description: $validated['description'] !== '' ? $validated['description'] : null,
            );
        } else {
            $service->update(
                leaveTypeId: $this->editingId,
                name: $validated['name'],
                code: $validated['code'],
                yearlyAllocationDays: $validated['yearlyAllocationDays'],
                carryForwardEnabled: $validated['carryForwardEnabled'],
                carryForwardMaxDays: $validated['carryForwardMaxDays'],
                description: $validated['description'] !== '' ? $validated['description'] : null,
            );
        }

        $this->cancel();
    }

    public function toggleActive(int $leaveTypeId, LeaveTypeService $service): void
    {
        $leaveType = DB::table('leave_types')->where('id', $leaveTypeId)->first();

        if ($leaveType === null) {
            return;
        }

        $service->setActive($leaveTypeId, ! (bool) $leaveType->is_active);
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
    }

    public function render(LeaveTypeService $service): View
    {
        return view('livewire.admin.leave-types', [
            'leaveTypes' => $service->list(search: $this->search, status: $this->statusFilter),
        ]);
    }
}
