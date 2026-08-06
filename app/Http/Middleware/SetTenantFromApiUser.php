<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins the current tenant for Sanctum-token requests via $request->user()
 * rather than Auth::user() — this app's own API controllers already use
 * $request->user() over Auth::user() for exactly the reason this exists:
 * Auth::user() resolves the default guard, which isn't guaranteed to be the
 * one auth:sanctum just authenticated against. No equivalent is needed on
 * the web side; Livewire/session-guard code already resolves Auth::user()
 * correctly, since the default guard there IS the one that authenticated.
 */
class SetTenantFromApiUser
{
    public function handle(Request $request, Closure $next): Response
    {
        Tenant::set($request->user()?->company_id);

        return $next($request);
    }
}
