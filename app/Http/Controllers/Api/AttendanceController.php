<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AttendanceController extends Controller
{
    public function today(Request $request, AttendanceService $service): JsonResponse
    {
        $record = $service->findForDate((int) $request->user()->id, now()->toDateString());

        return response()->json([
            'data' => [
                'date' => now()->toDateString(),
                'record' => $record,
                'has_checked_in' => $record !== null && $record->check_in_at !== null,
                'has_checked_out' => $record !== null && $record->check_out_at !== null,
            ],
        ]);
    }

    /**
     * DomainException ("already checked in") and ValidationException ("too
     * far from office") both bubble straight out of AttendanceService and
     * are rendered as clean JSON errors by the global exception mapping in
     * bootstrap/app.php — nothing to catch here.
     */
    public function checkIn(Request $request, AttendanceService $service): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $userId = (int) $request->user()->id;
        $today = now()->toDateString();

        $service->checkIn($userId, $today, (float) $validated['latitude'], (float) $validated['longitude']);

        return response()->json([
            'data' => $service->findForDate($userId, $today),
        ]);
    }

    public function checkOut(Request $request, AttendanceService $service): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $today = now()->toDateString();

        $service->checkOut($userId, $today);

        return response()->json([
            'data' => $service->findForDate($userId, $today),
        ]);
    }

    public function history(Request $request, AttendanceService $service): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:90'],
        ]);

        return response()->json([
            'data' => $service->historyForUser((int) $request->user()->id, (int) ($validated['limit'] ?? 30)),
        ]);
    }
}
