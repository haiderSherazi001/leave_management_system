<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\Tenant;
use Illuminate\Support\Facades\Auth;

final class DashboardService
{
    public function __construct(
        private readonly OfficeLocationService $officeLocation,
    ) {}

    /**
     * Company-wide attendance/leave counts for today, scoped to active
     * users across every role — check-in and leave are open to everyone,
     * so "company-wide" means the whole staff, not just employees.
     *
     * @return array{presentToday: int, lateToday: int, onLeaveToday: int, pendingManager: int, pendingHr: int}
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
        // which is scoped to a single manager's team. Split by stage
        // (rather than one combined count) so HR can tell at a glance
        // whether a pending request is sitting with a manager or with
        // HR itself — a single number gave no way to locate which queue
        // (if any) HR could actually act on.
        $pendingManager = LeaveRequest::where('status', LeaveRequestStatus::PendingManager)->count();
        $pendingHr = LeaveRequest::where('status', LeaveRequestStatus::PendingHR)->count();

        return [
            'presentToday' => $statusCounts[AttendanceStatus::Present->value] ?? 0,
            'lateToday' => $statusCounts[AttendanceStatus::Late->value] ?? 0,
            'onLeaveToday' => $onLeaveToday,
            'pendingManager' => $pendingManager,
            'pendingHr' => $pendingHr,
        ];
    }

    /**
     * Advisory, individually-dismissible nags for setup steps HR hasn't
     * done yet — not a checklist to complete, just a reminder while
     * something real is missing. Each disappears on its own once the
     * underlying condition is met, or earlier if HR dismisses it via
     * Company::dismissed_setup_alerts.
     *
     * @return array<int, array{key: string, message: string, route: string}>
     */
    public function setupAlerts(): array
    {
        $company = Auth::user()->company;
        $dismissed = $company->dismissedSetupAlertKeys();

        $candidates = [
            [
                'key' => 'leave_types',
                'message' => "No leave types configured yet — employees can't apply for leave until you add one.",
                'route' => 'admin.leave-types',
                'resolved' => LeaveType::query()->exists(),
            ],
            [
                'key' => 'work_schedule',
                'message' => "Work schedule isn't set — attendance can't be marked present or late accurately.",
                'route' => 'admin.work-schedule',
                'resolved' => WorkSchedule::query()->exists(),
            ],
            [
                'key' => 'office_location',
                'message' => "Office location isn't set — employees can't check in yet.",
                'route' => 'admin.office-location',
                'resolved' => $this->officeLocation->get() !== null,
            ],
            [
                'key' => 'employees',
                'message' => "You haven't invited anyone yet.",
                'route' => 'admin.employees',
                'resolved' => User::where('company_id', Tenant::id())->where('is_active', true)->count() > 1,
            ],
        ];

        return array_values(array_filter(
            $candidates,
            fn (array $alert): bool => ! $alert['resolved'] && ! in_array($alert['key'], $dismissed, true),
        ));
    }

    /**
     * A live, non-dismissible warning when requests have been sitting in
     * HR's own actionable queue too long — unlike setupAlerts(), this can
     * never be permanently silenced while a real backlog exists, since it
     * reflects current data rather than a one-time setup step.
     *
     * @return array{message: string, route: string}|null
     */
    public function stalePendingHrAlert(int $thresholdDays = 3): ?array
    {
        $count = LeaveRequest::where('status', LeaveRequestStatus::PendingHR)
            ->where('created_at', '<=', now()->subDays($thresholdDays))
            ->count();

        if ($count === 0) {
            return null;
        }

        return [
            'message' => "{$count} leave request(s) have been waiting on your approval for over {$thresholdDays} days.",
            'route' => 'admin.leave-approvals',
        ];
    }
}
