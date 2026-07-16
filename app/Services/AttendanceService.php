<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AttendanceService
{
    public function __construct(
        private readonly WorkScheduleService $schedule,
    ) {}

    public function checkIn(int $userId, string $date): void
    {
        $existing = DB::table('attendances')->where('user_id', $userId)->where('date', $date)->first();

        if ($existing !== null && $existing->check_in_at !== null) {
            throw new DomainException('You have already checked in today.');
        }

        $checkInAt = CarbonImmutable::now();
        $status = $this->determineCheckInStatus($checkInAt);

        if ($existing === null) {
            DB::table('attendances')->insert([
                'user_id' => $userId,
                'date' => $date,
                'check_in_at' => $checkInAt,
                'status' => $status,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $data = [
            'check_in_at' => $checkInAt,
            'updated_at' => now(),
        ];

        // An on_leave day (set by an approved leave request) stays on_leave even
        // if the employee still checks in for part of it (e.g. a half-day) —
        // the check-in time is recorded, but it doesn't override the status.
        if ($existing->status !== AttendanceStatus::OnLeave->value) {
            $data['status'] = $status;
        }

        DB::table('attendances')->where('id', $existing->id)->update($data);
    }

    public function checkOut(int $userId, string $date): void
    {
        $existing = DB::table('attendances')->where('user_id', $userId)->where('date', $date)->first();

        if ($existing === null || $existing->check_in_at === null) {
            throw new DomainException('You must check in before checking out.');
        }

        if ($existing->check_out_at !== null) {
            throw new DomainException('You have already checked out today.');
        }

        DB::table('attendances')->where('id', $existing->id)->update([
            'check_out_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Idempotent: safe to call more than once for the same user/date (e.g. a
     * retroactive approval, or two overlapping approved requests touching the
     * same day). Never touches check_in_at/check_out_at — only the status
     * reflects the leave; whatever attendance was actually recorded stays.
     */
    public function markOnLeave(int $userId, string $date): void
    {
        $existing = DB::table('attendances')->where('user_id', $userId)->where('date', $date)->first();

        if ($existing === null) {
            DB::table('attendances')->insert([
                'user_id' => $userId,
                'date' => $date,
                'status' => AttendanceStatus::OnLeave->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if ($existing->status === AttendanceStatus::OnLeave->value) {
            return;
        }

        DB::table('attendances')->where('id', $existing->id)->update([
            'status' => AttendanceStatus::OnLeave->value,
            'updated_at' => now(),
        ]);
    }

    public function findForDate(int $userId, string $date): ?object
    {
        return DB::table('attendances')
            ->where('user_id', $userId)
            ->where('date', $date)
            ->first();
    }

    /**
     * @return array<int, object>
     */
    public function historyForUser(int $userId, int $limit = 30): array
    {
        return DB::table('attendances')
            ->where('user_id', $userId)
            ->orderByDesc('date')
            ->limit($limit)
            ->get()
            ->all();
    }

    /**
     * present vs late, based on the configured work schedule's start time
     * plus its grace period. Defaults to present if no schedule is
     * configured yet, rather than blocking check-in on setup order.
     */
    private function determineCheckInStatus(CarbonImmutable $checkInAt): string
    {
        $schedule = $this->schedule->get();

        if ($schedule === null) {
            return AttendanceStatus::Present->value;
        }

        $threshold = CarbonImmutable::parse($checkInAt->toDateString().' '.$schedule->start_time)
            ->addMinutes($schedule->grace_minutes);

        return $checkInAt->greaterThan($threshold) ? AttendanceStatus::Late->value : AttendanceStatus::Present->value;
    }
}
