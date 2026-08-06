<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Public company signup: creates a new tenant and its first HR account in
 * one step. Always available (unlike the one-time-global /setup wizard this
 * replaces) — every company gets exactly one of these moments, its own.
 */
class RegisterCompanyController extends Controller
{
    public function create(): View
    {
        return view('auth.register-company');
    }

    public function store(Request $request, LeaveBalanceService $balances): RedirectResponse
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_address' => ['nullable', 'string', 'max:1000'],
            'company_website' => ['nullable', 'url', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $company = Company::create([
            'name' => $validated['company_name'],
            'address' => $validated['company_address'] ?? null,
            'website' => $validated['company_website'] ?? null,
        ]);

        // Pinned explicitly rather than left to resolve later via
        // Auth::user() - provisionForUser() below must run against this
        // exact company regardless of whatever tenant context (if any) was
        // already ambient when this request started.
        Tenant::set($company->id);

        $user = User::create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => UserRole::Hr->value,
            'is_active' => true,
            'joined_at' => now()->toDateString(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $balances->provisionForUser($user->id, now()->year);

        return redirect()->route($user->homeRouteName());
    }
}
