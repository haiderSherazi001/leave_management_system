<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Without this, an unauthenticated request to routes/api.php that
        // doesn't itself send `Accept: application/json` (e.g. a bare curl
        // call) gets a 302 redirect to the `login` route instead of a JSON
        // 401 — the default Authenticate middleware only forces JSON when
        // it detects the request "expects" it. Every /api/* consumer here
        // is a machine client (the payroll integration, not a browser), so
        // it should always get a JSON error response regardless of the
        // Accept header it happens to send.
        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // Every service in this app signals a business-rule conflict (already
        // checked in, insufficient balance, overlapping request, etc.) by
        // throwing DomainException. Mapping it here once means every API
        // controller can call a service directly and get a clean JSON error
        // automatically, instead of repeating a try/catch in every method.
        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => $e->getMessage()], 409);
            }
        });
    })->create();
