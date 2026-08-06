<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * One-time first-run setup: with no registration flow, there's otherwise no
 * way to create the very first HR account on a fresh deployment except
 * direct database access. Self-disabling — once any active HR account
 * exists, both actions just redirect to login, so this can never be used
 * to mint a second, unauthorized admin account later.
 */
class SetupController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if ($this->hrAlreadyExists()) {
            return redirect()->route('login');
        }

        return view('auth.setup');
    }

    public function store(Request $request, LeaveBalanceService $balances): RedirectResponse
    {
        // Re-checked here, not just in create() - two people loading the
        // empty form before either submits could otherwise both pass the
        // page-load check and both create an HR account.
        if ($this->hrAlreadyExists()) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        // joined_at defaults to today rather than asking for it on this
        // one-time bootstrap form - it's genuinely their first day using
        // the system. Balances are provisioned the same way
        // EmployeeDirectoryService::create() does for every other
        // employee - without this, HR (who can submit leave too, via the
        // existing auto-approve path) would have zero remaining balance
        // for every leave type and could never actually apply for leave.
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::Hr->value,
            'is_active' => true,
            'joined_at' => now()->toDateString(),
        ]);

        $balances->provisionForUser($user->id, now()->year);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route($user->homeRouteName());
    }

    private function hrAlreadyExists(): bool
    {
        return User::where('role', UserRole::Hr)->where('is_active', true)->exists();
    }
}
