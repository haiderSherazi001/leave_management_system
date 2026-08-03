<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\LeaveRequestService;
use App\Services\LeaveTypeService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class LeaveApprovals extends Component
{
    use WithPagination;

    private const TABS = ['pending', 'awaiting_manager', 'history'];

    /** @var array<int, string> */
    public array $notes = [];

    public ?string $errorMessage = null;

    #[Url]
    public string $tab = 'pending';

    #[Url(as: 'q', history: true)]
    public string $historySearch = '';

    #[Url(as: 'type', history: true)]
    public ?int $historyLeaveType = null;

    #[Url(as: 'status', history: true)]
    public string $historyStatus = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->isHr(), 403);

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'pending';
        }
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'pending';
        $this->resetPage();
    }

    public function updatedHistorySearch(): void
    {
        $this->resetPage();
    }

    public function updatedHistoryLeaveType(): void
    {
        $this->resetPage();
    }

    public function updatedHistoryStatus(): void
    {
        $this->resetPage();
    }

    public function clearHistoryFilters(): void
    {
        $this->reset(['historySearch', 'historyLeaveType', 'historyStatus']);
        $this->resetPage();
    }

    public function approve(int $leaveRequestId, LeaveRequestService $service): void
    {
        $this->decide($leaveRequestId, $service, approve: true);
    }

    public function reject(int $leaveRequestId, LeaveRequestService $service): void
    {
        $this->decide($leaveRequestId, $service, approve: false);
    }

    private function decide(int $leaveRequestId, LeaveRequestService $service, bool $approve): void
    {
        $this->errorMessage = null;
        $hrUser = Auth::user();
        $note = $this->notes[$leaveRequestId] ?? null;

        try {
            if ($approve) {
                $service->approveByHr($leaveRequestId, $hrUser, $note);
            } else {
                $service->reject($leaveRequestId, $hrUser, $note);
            }
        } catch (AuthorizationException) {
            $this->errorMessage = 'You are not authorized to act on this leave request.';
        } catch (DomainException $exception) {
            $this->errorMessage = $exception->getMessage();
        }

        unset($this->notes[$leaveRequestId]);
    }

    public function render(LeaveRequestService $service, LeaveTypeService $leaveTypes): View
    {
        return view('livewire.admin.leave-approvals', [
            'requests' => $this->tab === 'pending' ? $service->pendingForHr() : [],
            'awaitingManager' => $this->tab === 'awaiting_manager' ? $service->pendingManagerCompanyWide() : [],
            'history' => $this->tab === 'history' ? $service->historyForHr(
                search: $this->historySearch,
                leaveTypeId: $this->historyLeaveType,
                status: $this->historyStatus,
            ) : null,
            'leaveTypes' => $leaveTypes->list(),
        ]);
    }
}
