<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HolidayService;
use App\Services\LeaveBalanceService;
use App\Services\LeaveRequestService;
use App\Services\LeaveTypeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LeaveController extends Controller
{
    public function types(LeaveTypeService $service): JsonResponse
    {
        return response()->json(['data' => $service->activeList()]);
    }

    public function balances(Request $request, LeaveBalanceService $service): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['sometimes', 'integer'],
        ]);

        return response()->json([
            'data' => $service->forUser((int) $request->user()->id, (int) ($validated['year'] ?? now()->year)),
        ]);
    }

    /**
     * Mirrors the live day-count preview the web RequestForm already shows
     * as the employee picks dates, before they actually submit.
     */
    public function previewTotalDays(Request $request, LeaveRequestService $service): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['sometimes', 'boolean'],
        ]);

        $totalDays = $service->calculateTotalDays(
            CarbonImmutable::parse($validated['start_date']),
            CarbonImmutable::parse($validated['end_date']),
            (bool) ($validated['is_half_day'] ?? false),
        );

        return response()->json(['data' => ['total_days' => $totalDays]]);
    }

    /**
     * DomainException (insufficient balance, overlapping request, inactive
     * leave type, etc.) bubbles straight out of LeaveRequestService::submit()
     * and is rendered as a clean JSON error by the global exception mapping
     * in bootstrap/app.php.
     */
    public function store(Request $request, LeaveRequestService $service): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['sometimes', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $leaveRequestId = $service->submit(
            userId: (int) $request->user()->id,
            leaveTypeId: $validated['leave_type_id'],
            startDate: CarbonImmutable::parse($validated['start_date']),
            endDate: CarbonImmutable::parse($validated['end_date']),
            isHalfDay: (bool) ($validated['is_half_day'] ?? false),
            reason: $validated['reason'],
        );

        return response()->json(['data' => ['id' => $leaveRequestId]], 201);
    }

    public function history(Request $request, LeaveRequestService $service): JsonResponse
    {
        return response()->json(['data' => $service->forEmployee((int) $request->user()->id)]);
    }

    public function upcomingHolidays(Request $request, HolidayService $service): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        return response()->json(['data' => $service->upcoming($validated['limit'] ?? 5)]);
    }
}
