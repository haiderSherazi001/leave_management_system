<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\LeaveRequest;

final class DashboardService
{
    /**
     * Company-wide attendance/leave counts for today, scoped to active
     * users across every role — check-in and leave are open to everyone,
     * so "company-wide" means the whole staff, not just employees.
     *
     * @return array{presentToday: int, lateToday: int, onLeaveToday: int, pendingRequests: int}
     */
    public function attendanceOverview(): array
    {
        $today = today()->toDateString();

        $statusCounts = Attendance::query()
            ->join('users', 'users.id', '=', 'attendances.user_id')
            ->where('users.is_active', true)
            ->whereDate('attendances.date', $today)
            ->whereIn('attendances.status', [AttendanceStatus::Present, AttendanceStatus::Late])
            ->selectRaw('attendances.status, count(*) as total')
            ->groupBy('attendances.status')
            ->pluck('total', 'attendances.status');

        // Sourced from leave_requests, not attendances: leave_requests is the
        // authoritative record (attendances.status='on_leave' is a
        // best-effort projection written outside a DB transaction, allowed
        // to fail silently without rolling back the approval). At most one
        // approved request per employee can cover any given day (enforced
        // by hasOverlappingRequest() at submit time), so this count equals
        // distinct employees on leave today.
        $onLeaveToday = LeaveRequest::query()
            ->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->where('users.is_active', true)
            ->where('leave_requests.status', LeaveRequestStatus::Approved)
            ->whereDate('leave_requests.start_date', '<=', $today)
            ->whereDate('leave_requests.end_date', '>=', $today)
            ->count();

        // Company-wide, unlike LeaveRequestService::pendingForApprover()
        // which is scoped to a single manager's team — HR has no approval
        // step of its own yet (multi-level approval is Phase 4), so this is
        // simply every request still awaiting a manager's decision.
        $pendingRequests = LeaveRequest::where('status', LeaveRequestStatus::Pending)->count();

        return [
            'presentToday' => $statusCounts[AttendanceStatus::Present->value] ?? 0,
            'lateToday' => $statusCounts[AttendanceStatus::Late->value] ?? 0,
            'onLeaveToday' => $onLeaveToday,
            'pendingRequests' => $pendingRequests,
        ];
    }
}
