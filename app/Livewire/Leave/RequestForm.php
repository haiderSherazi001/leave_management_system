<?php

declare(strict_types=1);

namespace App\Livewire\Leave;

use App\Services\LeaveBalanceService;
use App\Services\LeaveRequestService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class RequestForm extends Component
{
    public ?int $leaveTypeId = null;

    public string $startDate = '';

    public string $endDate = '';

    public bool $isHalfDay = false;

    public string $reason = '';

    public ?string $successMessage = null;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'leaveTypeId' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('is_active', true)],
            'startDate' => ['required', 'date', 'after_or_equal:today'],
            'endDate' => ['required', 'date', 'after_or_equal:startDate'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function updatedIsHalfDay(bool $value): void
    {
        if ($value && $this->startDate !== '') {
            $this->endDate = $this->startDate;
        }
    }

    public function submit(LeaveRequestService $service): void
    {
        $this->successMessage = null;
        $this->validate();

        $userId = (int) Auth::id();

        try {
            $service->submit(
                userId: $userId,
                leaveTypeId: (int) $this->leaveTypeId,
                startDate: CarbonImmutable::parse($this->startDate),
                endDate: CarbonImmutable::parse($this->endDate),
                isHalfDay: $this->isHalfDay,
                reason: $this->reason,
            );
        } catch (DomainException $exception) {
            $this->addError('form', $exception->getMessage());

            return;
        }

        $this->reset(['leaveTypeId', 'startDate', 'endDate', 'isHalfDay', 'reason']);
        $this->successMessage = 'Leave request submitted successfully.';
    }

    public function render(LeaveBalanceService $balanceService): View
    {
        $userId = (int) Auth::id();

        return view('livewire.leave.request-form', [
            'leaveTypes' => DB::table('leave_types')->where('is_active', true)->orderBy('name')->get(),
            'balances' => $balanceService->forUser($userId, CarbonImmutable::now()->year),
        ]);
    }
}
