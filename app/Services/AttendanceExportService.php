<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Support\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class AttendanceExportService
{
    public function __construct(
        private readonly WorkScheduleService $schedule,
        private readonly AttendanceService $attendance,
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
     * @return Collection<int, array{user_id: int, name: string, date: string, check_in: ?string, check_out: ?string, status: string, worked_minutes: ?int, hours_worked: string}>
     */
    public function rowsBetween(string $start, string $end): Collection
    {
        // User carries no automatic tenant scope (see the model's own
        // docblock - it's the Authenticatable, scoping it would recurse
        // into session auth resolution), so it's filtered explicitly here.
        $users = User::where('company_id', Tenant::id())->where('is_active', true)->orderBy('name')->get(['id', 'name']);

        $attendanceByKey = Attendance::with('user')
            ->whereBetween('date', [$start, $end])
            ->get()
            ->keyBy(fn (Attendance $attendance) => $attendance->user_id.'|'.$attendance->date);

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
     * @return array{user_id: int, name: string, date: string, check_in: ?string, check_out: ?string, status: string, worked_minutes: ?int, hours_worked: string}
     */
    private function rowFor(User $user, string $date, Collection $attendanceByKey, Collection $leaveDateKeys): array
    {
        $key = $user->id.'|'.$date;
        $attendance = $attendanceByKey->get($key);

        if ($attendance !== null) {
            $workedMinutes = $this->attendance->minutesWorked($attendance->check_in_at, $attendance->check_out_at, $attendance->status);

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'date' => $date,
                'check_in' => $attendance->check_in_at?->format('g:i A'),
                'check_out' => $attendance->check_out_at?->format('g:i A'),
                'status' => $attendance->status->label(),
                'worked_minutes' => $workedMinutes,
                'hours_worked' => $this->attendance->formatDuration($workedMinutes),
            ];
        }

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'date' => $date,
            'check_in' => null,
            'check_out' => null,
            'status' => $leaveDateKeys->has($key) ? AttendanceStatus::OnLeave->label() : AttendanceStatus::Absent->label(),
            'worked_minutes' => null,
            'hours_worked' => '—',
        ];
    }

    /**
     * Per-employee attendance/leave totals for [$start, $end] — the read
     * model behind the payroll API. Built on top of rowsBetween() (grouped
     * and counted, not re-queried) so "present", "absent", "late", and "on
     * leave" mean exactly the same thing here as they do in the Excel
     * export: synthesized per working day, not a raw count of stored
     * attendance rows (this app never writes an `absent` row at all).
     *
     * @return Collection<int, array{user_id: int, name: string, days_present: int, days_absent_or_late: int, approved_leave_days: int, total_hours_worked: float}>
     */
    public function summaryBetween(string $start, string $end): Collection
    {
        return $this->rowsBetween($start, $end)
            ->groupBy('user_id')
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return [
                    'user_id' => $first['user_id'],
                    'name' => $first['name'],
                    'days_present' => $rows->where('status', AttendanceStatus::Present->label())->count(),
                    'days_absent_or_late' => $rows->whereIn('status', [
                        AttendanceStatus::Absent->label(),
                        AttendanceStatus::Late->label(),
                    ])->count(),
                    'approved_leave_days' => $rows->where('status', AttendanceStatus::OnLeave->label())->count(),
                    'total_hours_worked' => round($rows->sum('worked_minutes') / 60, 2),
                ];
            })
            ->values();
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
