<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Support\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AttendanceService
{
    public function __construct(
        private readonly WorkScheduleService $schedule,
        private readonly GeoLocationService $geoLocation,
    ) {}

    /**
     * Requires the employee's current coordinates so check-in can be
     * restricted to the office premises (a zero-budget "geofence" — no paid
     * geolocation API, just the browser's own HTML5 Geolocation plus a
     * Haversine distance check against the configured office location).
     */
    public function checkIn(int $userId, string $date, float $latitude, float $longitude): void
    {
        $existing = DB::table('attendances')
            ->where('company_id', Tenant::id())
            ->where('user_id', $userId)
            ->where('date', $date)
            ->first();

        if ($existing !== null && $existing->check_in_at !== null) {
            throw new DomainException('You have already checked in today.');
        }

        // office() throws a DomainException itself ("has not been configured
        // yet") if HR hasn't set one up - same as any other precondition
        // failure in this method, nothing extra to catch here.
        $office = $this->geoLocation->office();

        if (! $this->geoLocation->isWithinOfficeRadius($latitude, $longitude)) {
            throw ValidationException::withMessages([
                'location' => sprintf(
                    'You must be within %d meters of the office to check in.',
                    $office->radius_meters,
                ),
            ]);
        }

        $checkInAt = CarbonImmutable::now();
        $status = $this->determineCheckInStatus($checkInAt);

        if ($existing === null) {
            DB::table('attendances')->insert([
                'company_id' => Tenant::id(),
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
        $existing = DB::table('attendances')
            ->where('company_id', Tenant::id())
            ->where('user_id', $userId)
            ->where('date', $date)
            ->first();

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
        $existing = DB::table('attendances')
            ->where('company_id', Tenant::id())
            ->where('user_id', $userId)
            ->where('date', $date)
            ->first();

        if ($existing === null) {
            DB::table('attendances')->insert([
                'company_id' => Tenant::id(),
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

    /**
     * Minutes between check-in and check-out, or check-in and now if still
     * checked in — so a page showing "today" reflects time worked so far,
     * not just a final total that only appears after checking out.
     *
     * $status guards one specific case: an on_leave day with a check-in but
     * no check-out yet (an accidental check-in, or a legitimate half-day-
     * leave-then-worked-a-bit that hasn't been closed out). Counting that
     * live, all the way to now, would show an ever-growing "hours worked"
     * for someone who is, for the day, officially on leave - so it returns
     * null (no live count) until they actually check out, at which point
     * the real elapsed time is reported like any other completed session.
     */
    public function minutesWorked(
        CarbonInterface|string|null $checkInAt,
        CarbonInterface|string|null $checkOutAt,
        AttendanceStatus|string|null $status = null,
    ): ?int {
        if ($checkInAt === null) {
            return null;
        }

        $statusValue = $status instanceof AttendanceStatus ? $status->value : $status;

        if ($checkOutAt === null && $statusValue === AttendanceStatus::OnLeave->value) {
            return null;
        }

        $start = CarbonImmutable::parse($checkInAt);
        $end = $checkOutAt !== null ? CarbonImmutable::parse($checkOutAt) : CarbonImmutable::now();

        return max(0, intdiv($end->getTimestamp() - $start->getTimestamp(), 60));
    }

    public function formatDuration(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        return $hours > 0 ? "{$hours}h {$mins}m" : "{$mins}m";
    }

    public function findForDate(int $userId, string $date): ?object
    {
        return DB::table('attendances')
            ->where('company_id', Tenant::id())
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
            ->where('company_id', Tenant::id())
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
