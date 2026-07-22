<?php

declare(strict_types=1);

namespace App\Livewire\Leave;

use App\Services\LeaveRequestService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class ApprovalQueue extends Component
{
    use WithPagination;

    /** @var array<int, string> */
    public array $notes = [];

    public ?string $errorMessage = null;

    public string $tab = 'pending';

    public function mount(): void
    {
        abort_unless(Auth::user()->isManager(), 403);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'history' ? 'history' : 'pending';
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
        $approver = Auth::user();
        $note = $this->notes[$leaveRequestId] ?? null;

        try {
            if ($approve) {
                $service->approve($leaveRequestId, $approver, $note);
            } else {
                $service->reject($leaveRequestId, $approver, $note);
            }
        } catch (AuthorizationException) {
            $this->errorMessage = 'You are not authorized to act on this leave request.';
        } catch (DomainException $exception) {
            $this->errorMessage = $exception->getMessage();
        }

        unset($this->notes[$leaveRequestId]);
    }

    public function render(LeaveRequestService $service): View
    {
        $managerId = (int) Auth::id();

        return view('livewire.leave.approval-queue', [
            'requests' => $this->tab === 'pending' ? $service->pendingForApprover($managerId) : [],
            'history' => $this->tab === 'history' ? $service->historyForApprover($managerId) : null,
        ]);
    }
}
