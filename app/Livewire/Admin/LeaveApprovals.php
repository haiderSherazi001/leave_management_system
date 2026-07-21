<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\LeaveRequestService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class LeaveApprovals extends Component
{
    /** @var array<int, string> */
    public array $notes = [];

    public ?string $errorMessage = null;

    public function mount(): void
    {
        abort_unless(Auth::user()->isHr(), 403);
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

    public function render(LeaveRequestService $service): View
    {
        return view('livewire.admin.leave-approvals', [
            'requests' => $service->pendingForHr(),
        ]);
    }
}
