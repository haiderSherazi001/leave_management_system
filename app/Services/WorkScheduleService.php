<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class WorkScheduleService
{
    public function __construct(
        private readonly HolidayService $holidays,
    ) {}

    public function get(): ?object
    {
        $schedule = DB::table('work_schedule')->where('company_id', Tenant::id())->first();

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
        $existing = DB::table('work_schedule')->where('company_id', Tenant::id())->first();

        $data = [
            'working_days' => json_encode(array_values($workingDays)),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'grace_minutes' => $graceMinutes,
            'updated_at' => now(),
        ];

        if ($existing === null) {
            DB::table('work_schedule')->insert([...$data, 'company_id' => Tenant::id(), 'created_at' => now()]);
        } else {
            DB::table('work_schedule')->where('id', $existing->id)->update($data);
        }
    }

    /**
     * A day is a working day if it's not a configured holiday, and (when a
     * schedule exists) its weekday is one of the configured working days.
     * With no schedule configured yet, every non-holiday day counts as a
     * working day, so leave/attendance logic isn't blocked on setup order.
     */
    public function isWorkingDay(string $date): bool
    {
        if ($this->holidays->isHoliday($date)) {
            return false;
        }

        $schedule = $this->get();

        if ($schedule === null) {
            return true;
        }

        $dayOfWeek = CarbonImmutable::parse($date)->dayOfWeek;

        return in_array($dayOfWeek, $schedule->working_days, true);
    }
}
