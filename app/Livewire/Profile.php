<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\LeaveBalanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Profile extends Component
{
    public function render(LeaveBalanceService $balances): View
    {
        $user = Auth::user();

        return view('livewire.profile', [
            'balances' => $balances->forUser($user->id, now()->year),
            'teamMembers' => $user->subordinates()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
