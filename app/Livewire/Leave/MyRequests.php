<?php

declare(strict_types=1);

namespace App\Livewire\Leave;

use App\Services\LeaveRequestService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class MyRequests extends Component
{
    public function render(LeaveRequestService $service): View
    {
        $userId = (int) Auth::id();

        return view('livewire.leave.my-requests', [
            'requests' => $service->forEmployee($userId),
        ]);
    }
}
