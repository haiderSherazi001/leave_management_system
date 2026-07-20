<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class AttendanceExportService
{
    public function __construct(
        private readonly WorkScheduleService $schedule,
    ) {}

    /**
     * One row per active user for every working day in [$start, $end] —
     * synthesized, not just a query of existing attendance rows. Nothing
     * in this app ever writes an `absent` attendance row (no scheduled
     * backfill job exists), so a day with no attendance row and no
     * covering approved leave is reported as Absent here. Weekends and
     * holidays are skipped entirely, matching how every other working-day
     * calculation in this app (calculateTotalDays(), the attendance
     * auto-link) already treats "a day of work".
     *
     * @return Collection<int, array{name: string, date: string, check_in: ?string, check_out: ?string, status: string}>
     */
    public function rowsBetween(string $start, string $end): Collection
    {
        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $attendanceByKey = Attendance::with('user')
            ->whereBetween('date', [$start, $end])
            ->get()
            ->keyBy(fn (Attendance $attendance) => $attendance->user_id.'|'.$attendance->date->toDateString());

        $leaveDateKeys = $this->approvedLeaveDateKeys($start, $end);

        $rows = collect();
        $cursor = CarbonImmutable::parse($start);
        $rangeEnd = CarbonImmutable::parse($end);

        while ($cursor->lessThanOrEqualTo($rangeEnd)) {
            $date = $cursor->toDateString();

            if ($this->schedule->isWorkingDay($date)) {
                foreach ($users as $user) {
                    $rows->push($this->rowFor($user, $date, $attendanceByKey, $leaveDateKeys));
                }
            }

            $cursor = $cursor->addDay();
        }

        return $rows;
    }

    /**
     * @param  Collection<string, Attendance>  $attendanceByKey
     * @param  Collection<string, int>  $leaveDateKeys
     * @return array{name: string, date: string, check_in: ?string, check_out: ?string, status: string}
     */
    private function rowFor(User $user, string $date, Collection $attendanceByKey, Collection $leaveDateKeys): array
    {
        $key = $user->id.'|'.$date;
        $attendance = $attendanceByKey->get($key);

        if ($attendance !== null) {
            return [
                'name' => $user->name,
                'date' => $date,
                'check_in' => $attendance->check_in_at?->format('g:i A'),
                'check_out' => $attendance->check_out_at?->format('g:i A'),
                'status' => $attendance->status->label(),
            ];
        }

        return [
            'name' => $user->name,
            'date' => $date,
            'check_in' => null,
            'check_out' => null,
            'status' => $leaveDateKeys->has($key) ? AttendanceStatus::OnLeave->label() : AttendanceStatus::Absent->label(),
        ];
    }

    /**
     * "user_id|date" keys for every day covered by an approved leave
     * request that overlaps the range — expanded per-day so lookup during
     * the row loop is a single O(1) hash check, not a per-day range scan.
     *
     * @return Collection<string, int>
     */
    private function approvedLeaveDateKeys(string $start, string $end): Collection
    {
        $rangeStart = CarbonImmutable::parse($start);
        $rangeEnd = CarbonImmutable::parse($end);

        return LeaveRequest::where('status', LeaveRequestStatus::Approved)
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->get(['user_id', 'start_date', 'end_date'])
            ->flatMap(function (LeaveRequest $leave) use ($rangeStart, $rangeEnd) {
                $leaveStart = CarbonImmutable::parse($leave->start_date);
                $leaveEnd = CarbonImmutable::parse($leave->end_date);

                $cursor = $leaveStart->lessThan($rangeStart) ? $rangeStart : $leaveStart;
                $last = $leaveEnd->greaterThan($rangeEnd) ? $rangeEnd : $leaveEnd;

                $keys = [];

                while ($cursor->lessThanOrEqualTo($last)) {
                    $keys[] = $leave->user_id.'|'.$cursor->toDateString();
                    $cursor = $cursor->addDay();
                }

                return $keys;
            })
            ->flip();
    }
}
