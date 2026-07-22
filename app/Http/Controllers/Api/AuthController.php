<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    /**
     * Same credential/lockout/inactive-account conventions as the web
     * LoginRequest — this just issues a Sanctum token instead of starting a
     * session. Not role-restricted: Managers and HR can log in here too,
     * they simply have no mobile screens of their own yet.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($validated['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        if (! Auth::attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Please contact HR.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        // Auth::attempt() above started a stateful session on this request
        // (irrelevant for a token-based mobile client) — log it back out so
        // no session cookie leaks into the response, the token is the only
        // credential the app should rely on afterward.
        Auth::logout();

        $token = $user->createToken('mobile-app-token')->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $this->userPayload($user),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['message' => 'Logged out.']]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($request->user())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'department_id' => $user->department_id,
            'is_active' => $user->is_active,
        ];
    }
}
