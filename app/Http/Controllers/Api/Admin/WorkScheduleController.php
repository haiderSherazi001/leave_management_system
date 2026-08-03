<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\WorkScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HR-only mobile admin API for the company work schedule - mirrors
 * App\Livewire\Admin\WorkSchedule::rules() exactly. Unlike every other admin
 * section, there's only ever one schedule (a singleton row, not a list), so
 * this is a single GET/PUT pair rather than index/store/update/{id}.
 */
final class WorkScheduleController extends Controller
{
    public function show(Request $request, WorkScheduleService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $schedule = $service->get();

        // Same defaults WorkSchedule::mount() falls back to when no row
        // exists yet, so the mobile form starts pre-filled identically to
        // the web screen on a fresh install.
        return response()->json(['data' => $schedule === null
            ? ['working_days' => [1, 2, 3, 4, 5], 'start_time' => '09:00', 'end_time' => '17:00', 'grace_minutes' => 0]
            : [
                'working_days' => $schedule->working_days,
                'start_time' => substr((string) $schedule->start_time, 0, 5),
                'end_time' => substr((string) $schedule->end_time, 0, 5),
                'grace_minutes' => $schedule->grace_minutes,
            ],
        ]);
    }

    public function update(Request $request, WorkScheduleService $service): JsonResponse
    {
        abort_unless($request->user()->isHr(), 403);

        $validated = $request->validate([
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
        ]);

        $service->save(
            workingDays: $validated['working_days'],
            startTime: $validated['start_time'].':00',
            endTime: $validated['end_time'].':00',
            graceMinutes: $validated['grace_minutes'],
        );

        return response()->json(['data' => ['message' => 'Work schedule updated.']]);
    }
}
