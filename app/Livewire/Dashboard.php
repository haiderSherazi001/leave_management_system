<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The generic Employee/Manager quick-links landing page. Was previously a
 * plain Blade view rendered by a route closure — the one page in the app
 * that wasn't a Livewire component, which meant Livewire never had a reason
 * to auto-inject its script tag here, so the Alpine.js it bundles (this
 * app's only Alpine instance, see the header dropdown / mobile sidebar
 * toggle) never loaded on this specific page. Converting it into a real
 * component fixes that at the root rather than special-casing this one page.
 */
#[Layout('layouts.app')]
class Dashboard extends Component
{
    public bool $showWelcome = false;

    /**
     * HR has its own real dashboard (Admin\Dashboard) with live company
     * KPIs - this generic quick-links page is for Employee/Manager only.
     * Redirect rather than just hiding the nav link to it, since visiting
     * the URL directly (bookmark, typed manually) should never show HR a
     * second, unrelated "dashboard".
     */
    public function mount(): void
    {
        if (Auth::user()->isHr()) {
            $this->redirect(route('admin.dashboard'));

            return;
        }

        $this->showWelcome = Auth::user()->welcomed_at === null;
    }

    public function dismissWelcome(): void
    {
        Auth::user()->update(['welcomed_at' => now()]);
        $this->showWelcome = false;
    }

    public function render(): View
    {
        return view('livewire.dashboard');
    }
}
