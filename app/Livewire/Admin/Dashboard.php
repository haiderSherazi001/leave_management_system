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
    public bool $showWelcome = false;

    public function mount(): void
    {
        abort_unless(Auth::user()->isHr(), 403);

        $this->showWelcome = Auth::user()->welcomed_at === null;
    }

    public function dismissWelcome(): void
    {
        Auth::user()->update(['welcomed_at' => now()]);
        $this->showWelcome = false;
    }

    public function dismissSetupAlert(string $key): void
    {
        $company = Auth::user()->company;
        $dismissed = $company->dismissedSetupAlertKeys();

        if (! in_array($key, $dismissed, true)) {
            $dismissed[] = $key;
            $company->update(['dismissed_setup_alerts' => $dismissed]);
        }
    }

    public function render(DashboardService $service): View
    {
        return view('livewire.admin.dashboard', [
            'stats' => $service->attendanceOverview(),
            'setupAlerts' => $service->setupAlerts(),
            'stalePendingHrAlert' => $service->stalePendingHrAlert(),
        ]);
    }
}
