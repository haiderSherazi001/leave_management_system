<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AttendanceExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PayrollController extends Controller
{
    public function summary(Request $request, AttendanceExportService $service): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        return response()->json([
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'employees' => $service->summaryBetween($validated['start_date'], $validated['end_date']),
        ]);
    }
}
