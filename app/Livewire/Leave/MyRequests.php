<?php

declare(strict_types=1);

namespace App\Livewire\Leave;

use App\Services\LeaveRequestService;
use App\Services\LeaveTypeService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class MyRequests extends Component
{
    #[Url(as: 'type', history: true)]
    public ?int $leaveTypeFilter = null;

    #[Url(as: 'status', history: true)]
    public string $statusFilter = '';

    public function clearFilters(): void
    {
        $this->reset(['leaveTypeFilter', 'statusFilter']);
    }

    public function render(LeaveRequestService $service, LeaveTypeService $leaveTypes): View
    {
        $userId = (int) Auth::id();

        return view('livewire.leave.my-requests', [
            'requests' => $service->forEmployee(
                $userId,
                leaveTypeId: $this->leaveTypeFilter,
                status: $this->statusFilter,
            ),
            'leaveTypes' => $leaveTypes->list(),
        ]);
    }
}
