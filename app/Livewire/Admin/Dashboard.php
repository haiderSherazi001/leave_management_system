<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\DashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function mount(): void
    {
        abort_unless(Auth::user()->isHr(), 403);
    }

    public function render(DashboardService $service): View
    {
        return view('livewire.admin.dashboard', [
            'stats' => $service->attendanceOverview(),
        ]);
    }
}
