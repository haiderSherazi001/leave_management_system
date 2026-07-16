<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class WorkScheduleService
{
    public function get(): ?object
    {
        $schedule = DB::table('work_schedule')->first();

        if ($schedule === null) {
            return null;
        }

        return (object) [
            'id' => $schedule->id,
            'working_days' => json_decode($schedule->working_days, true),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'grace_minutes' => $schedule->grace_minutes,
        ];
    }

    /**
     * @param  array<int, int>  $workingDays
     */
    public function save(array $workingDays, string $startTime, string $endTime, int $graceMinutes): void
    {
        $existing = DB::table('work_schedule')->first();

        $data = [
            'working_days' => json_encode(array_values($workingDays)),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'grace_minutes' => $graceMinutes,
            'updated_at' => now(),
        ];

        if ($existing === null) {
            DB::table('work_schedule')->insert([...$data, 'created_at' => now()]);
        } else {
            DB::table('work_schedule')->where('id', $existing->id)->update($data);
        }
    }
}
