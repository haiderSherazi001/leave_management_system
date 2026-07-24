<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestAwaitingHrApprovalNotification;
use App\Notifications\LeaveRequestStatusNotification;
use App\Notifications\NewLeaveRequestNotification;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Notifications\Notification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

final class LeaveRequestService
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
        private readonly WorkScheduleService $schedule,
        private readonly AttendanceService $attendance,
    ) {}

    /**
     * Half-day is 0.5 for that one chosen day, but only if it's actually a
     * working day — a half-day request on a weekend/holiday has no working
     * time to take off, so it counts as zero (submit() below rejects any
     * zero-day request). For a multi-day range, only working days count —
     * weekends and configured holidays are excluded so a request spanning a
     * holiday doesn't consume balance for a day the employee wasn't
     * scheduled to work anyway.
     */
    public function calculateTotalDays(CarbonImmutable $startDate, CarbonImmutable $endDate, bool $isHalfDay): float
    {
        if ($isHalfDay) {
            return $this->schedule->isWorkingDay($startDate->toDateString()) ? 0.5 : 0.0;
        }

        $days = 0.0;
        $cursor = $startDate;

        while ($cursor->lessThanOrEqualTo($endDate)) {
            if ($this->schedule->isWorkingDay($cursor->toDateString())) {
                $days++;
            }

            $cursor = $cursor->addDay();
        }

        return $days;
    }

    public function submit(
        int $userId,
        int $leaveTypeId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        bool $isHalfDay,
        string $reason,
    ): int {
        if ($endDate->lessThan($startDate)) {
            throw new DomainException('End date cannot be before the start date.');
        }

        if ($isHalfDay && ! $startDate->isSameDay($endDate)) {
            throw new DomainException('A half-day request must have the same start and end date.');
        }

        $role = DB::table('users')->where('id', $userId)->where('is_active', true)->value('role');

        if ($role === null) {
            throw new DomainException('Inactive employees cannot submit leave requests.');
        }

        if (! DB::table('leave_types')->where('id', $leaveTypeId)->where('is_active', true)->exists()) {
            throw new DomainException('This leave type is no longer active.');
        }

        if ($this->hasOverlappingRequest($userId, $startDate, $endDate)) {
            throw new DomainException('You already have a pending or approved leave request that overlaps these dates.');
        }

        $totalDays = $this->calculateTotalDays($startDate, $endDate, $isHalfDay);

        if ($totalDays <= 0.0) {
            throw new DomainException('The selected date range does not include any working days.');
        }

        $year = $startDate->year;
        $remaining = $this->balances->remainingDays($userId, $leaveTypeId, $year);

        if ($totalDays > $remaining) {
            throw new DomainException("Requested {$totalDays} day(s) exceeds the remaining balance of {$remaining} day(s).");
        }

        // A Manager applying for their own leave can't be the one who signs off on it
        // — payroll compliance requires HR's final approval regardless of who's
        // asking — so their request skips straight to PendingHR, exactly as if they'd
        // manually forwarded it via approve(). HR has no one above them in the chain,
        // so an HR user's own request is still auto-approved immediately.
        $isManager = $role === UserRole::Manager->value;
        $isHr = $role === UserRole::Hr->value;

        $status = match (true) {
            $isHr => LeaveRequestStatus::Approved,
            $isManager => LeaveRequestStatus::PendingHR,
            default => LeaveRequestStatus::PendingManager,
        };

        $leaveRequestId = DB::table('leave_requests')->insertGetId([
            'user_id' => $userId,
            'leave_type_id' => $leaveTypeId,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'is_half_day' => $isHalfDay,
            'total_days' => $totalDays,
            'reason' => $reason,
            'status' => $status->value,
            'approver_id' => $isManager ? $userId : null,
            'hr_approver_id' => $isHr ? $userId : null,
            'decision_note' => $isHr ? 'Auto-approved' : null,
            'decided_at' => $isHr ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($isHr) {
            $this->finalizeApproval(LeaveRequest::findOrFail($leaveRequestId));
        } elseif ($isManager) {
            $this->notifyHrOfPendingApproval(LeaveRequest::findOrFail($leaveRequestId), User::findOrFail($userId));
        } else {
            $this->notifyManagersOfNewRequest($leaveRequestId, $userId, $leaveTypeId, $startDate, $endDate, $totalDays, $reason);
        }

        return $leaveRequestId;
    }

    /**
     * True if the employee already has a pending or approved request covering any of these dates.
     */
    public function hasOverlappingRequest(
        int $userId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        ?int $excludingRequestId = null,
    ): bool {
        $query = DB::table('leave_requests')
            ->where('user_id', $userId)
            ->whereIn('status', [
                LeaveRequestStatus::PendingManager->value,
                LeaveRequestStatus::PendingHR->value,
                LeaveRequestStatus::Approved->value,
            ])
            ->where('start_date', '<=', $endDate->toDateString())
            ->where('end_date', '>=', $startDate->toDateString());

        if ($excludingRequestId !== null) {
            $query->where('id', '!=', $excludingRequestId);
        }

        return $query->exists();
    }

    /**
     * Manager-stage approval: forwards a request from PendingManager to
     * PendingHR. This does not deduct balance or touch attendance — those
     * only happen once HR gives the final sign-off in approveByHr().
     * Authorization is enforced here (via LeaveRequestPolicy), not just in
     * the calling UI, so it can't be bypassed by any future caller.
     */
    public function approve(int $leaveRequestId, User $approver, ?string $note = null): void
    {
        $leaveRequest = LeaveRequest::find($leaveRequestId);

        if ($leaveRequest === null) {
            throw new DomainException('Leave request not found.');
        }

        Gate::forUser($approver)->authorize('approve', $leaveRequest);

        if ($leaveRequest->status !== LeaveRequestStatus::PendingManager) {
            throw new DomainException('Only requests pending manager approval can be approved at this stage.');
        }

        DB::table('leave_requests')
            ->where('id', $leaveRequest->id)
            ->update([
                'status' => LeaveRequestStatus::PendingHR->value,
                'approver_id' => $approver->id,
                'decision_note' => $note,
                'updated_at' => now(),
            ]);

        $this->notifyHrOfPendingApproval($leaveRequest, $approver);
    }

    /**
     * HR-stage final approval: PendingHR -> Approved. This is the point at
     * which balance is actually deducted and leave is linked into
     * attendance — the manager's earlier approve() only forwards the
     * request, it doesn't finalize it.
     */
    public function approveByHr(int $leaveRequestId, User $hrApprover, ?string $note = null): void
    {
        $leaveRequest = LeaveRequest::find($leaveRequestId);

        if ($leaveRequest === null) {
            throw new DomainException('Leave request not found.');
        }

        Gate::forUser($hrApprover)->authorize('approve', $leaveRequest);

        if ($leaveRequest->status !== LeaveRequestStatus::PendingHR) {
            throw new DomainException('Only requests pending HR approval can be approved at this stage.');
        }

        $remaining = $this->balances->remainingDays($leaveRequest->user_id, $leaveRequest->leave_type_id, $leaveRequest->start_date->year);

        if ((float) $leaveRequest->total_days > $remaining) {
            throw new DomainException('Employee no longer has sufficient balance for this request.');
        }

        DB::table('leave_requests')
            ->where('id', $leaveRequest->id)
            ->update([
                'status' => LeaveRequestStatus::Approved->value,
                'hr_approver_id' => $hrApprover->id,
                'decision_note' => $note,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);

        $this->finalizeApproval($leaveRequest);
        $this->notifyEmployeeOfDecision($leaveRequest, $hrApprover, $note, LeaveRequestStatus::Approved);
    }

    /**
     * Deducts balance and links attendance for a request that has just
     * become Approved — shared by approveByHr() (the normal path) and
     * submit() (an HR user auto-approving their own leave, which has no
     * HR stage to pass through since HR is already the top of the chain).
     * Deduction and attendance-linking happen together in one transaction
     * so a request is never left half-finalized.
     */
    private function finalizeApproval(LeaveRequest $leaveRequest): void
    {
        DB::transaction(function () use ($leaveRequest): void {
            $this->balances->deductDays(
                $leaveRequest->user_id,
                $leaveRequest->leave_type_id,
                $leaveRequest->start_date->year,
                (float) $leaveRequest->total_days,
            );

            $this->linkApprovedLeaveToAttendance($leaveRequest);
        });
    }

    /**
     * Either stage's approver can reject outright — a manager rejecting a
     * PendingManager request, or HR rejecting a PendingHR one — and it's
     * always final. The rejecting approver is recorded in whichever
     * approver column matches the stage they rejected at, so approver_id
     * and hr_approver_id together still form an accurate trail of who
     * touched the request.
     */
    public function reject(int $leaveRequestId, User $approver, ?string $note = null): void
    {
        $leaveRequest = LeaveRequest::find($leaveRequestId);

        if ($leaveRequest === null) {
            throw new DomainException('Leave request not found.');
        }

        Gate::forUser($approver)->authorize('reject', $leaveRequest);

        if (! in_array($leaveRequest->status, [LeaveRequestStatus::PendingManager, LeaveRequestStatus::PendingHR], true)) {
            throw new DomainException('Only pending requests can be rejected.');
        }

        $isHrStage = $leaveRequest->status === LeaveRequestStatus::PendingHR;

        DB::table('leave_requests')
            ->where('id', $leaveRequest->id)
            ->update([
                'status' => LeaveRequestStatus::Rejected->value,
                $isHrStage ? 'hr_approver_id' : 'approver_id' => $approver->id,
                'decision_note' => $note,
                'decided_at' => now(),
                'updated_at' => now(),
            ]);

        $this->notifyEmployeeOfDecision($leaveRequest, $approver, $note, LeaveRequestStatus::Rejected);
    }

    /**
     * @return array<int, object>
     */
    public function forEmployee(int $userId): array
    {
        return DB::table('leave_requests')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->leftJoin('users as approvers', 'approvers.id', '=', 'leave_requests.approver_id')
            ->leftJoin('users as hr_approvers', 'hr_approvers.id', '=', 'leave_requests.hr_approver_id')
            ->where('leave_requests.user_id', $userId)
            ->select(
                'leave_requests.id',
                'leave_types.name as leave_type_name',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.is_half_day',
                'leave_requests.total_days',
                'leave_requests.status',
                'leave_requests.decision_note',
                'approvers.name as approver_name',
                'hr_approvers.name as hr_approver_name',
            )
            ->orderByDesc('leave_requests.created_at')
            ->get()
            ->all();
    }

    /**
     * PendingManager requests visible to a manager: their direct reports,
     * plus anyone whose department is managed by them but who has no
     * direct manager of their own. An employee's direct manager_id always
     * takes priority over their department's manager — the department
     * manager is a fallback, not a second, parallel approver — so each
     * employee has exactly one assigned manager, never two. This is the
     * first of two approval stages; approving here only forwards the
     * request to HR (see pendingForHr()), it doesn't finalize it.
     *
     * @return array<int, object>
     */
    public function pendingForApprover(int $managerId): array
    {
        return DB::table('leave_requests')
            ->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_requests.status', LeaveRequestStatus::PendingManager->value)
            ->where(function ($query) use ($managerId): void {
                $query->where('users.manager_id', $managerId)
                    ->orWhere(function ($query) use ($managerId): void {
                        $query->whereNull('users.manager_id')
                            ->where('departments.manager_id', $managerId);
                    });
            })
            ->select(
                'leave_requests.id',
                'users.name as employee_name',
                'leave_types.name as leave_type_name',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.is_half_day',
                'leave_requests.total_days',
                'leave_requests.reason',
            )
            ->orderBy('leave_requests.start_date')
            ->get()
            ->all();
    }

    /**
     * Decided requests this manager has personally touched — approver_id
     * alone is the correct scope here, not current team membership. It
     * covers both requests they rejected themselves AND the eventual
     * outcome of anything they forwarded to HR (approver_id is set once,
     * when they act, and never changes afterward regardless of what HR
     * later decides), so a manager can always see what happened to a
     * request after it left their hands.
     */
    public function historyForApprover(int $managerId, int $perPage = 10): LengthAwarePaginator
    {
        return DB::table('leave_requests')
            ->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->leftJoin('users as hr_approvers', 'hr_approvers.id', '=', 'leave_requests.hr_approver_id')
            ->where('leave_requests.approver_id', $managerId)
            ->whereIn('leave_requests.status', [LeaveRequestStatus::Approved->value, LeaveRequestStatus::Rejected->value])
            ->select(
                'leave_requests.id',
                'users.name as employee_name',
                'leave_types.name as leave_type_name',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.is_half_day',
                'leave_requests.total_days',
                'leave_requests.status',
                'leave_requests.decision_note',
                'leave_requests.decided_at',
                'hr_approvers.name as hr_approver_name',
            )
            ->orderByDesc('leave_requests.decided_at')
            ->paginate($perPage);
    }

    /**
     * Company-wide PendingHR requests — every request a manager has
     * already forwarded and that now needs HR's final sign-off. Unlike
     * pendingForApprover(), this isn't scoped to any one manager's team:
     * HR reviews requests for the whole company.
     *
     * @return array<int, object>
     */
    public function pendingForHr(): array
    {
        return DB::table('leave_requests')
            ->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->leftJoin('users as managers', 'managers.id', '=', 'leave_requests.approver_id')
            ->where('leave_requests.status', LeaveRequestStatus::PendingHR->value)
            ->select(
                'leave_requests.id',
                'users.name as employee_name',
                'leave_types.name as leave_type_name',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.is_half_day',
                'leave_requests.total_days',
                'leave_requests.reason',
                'leave_requests.decision_note as manager_note',
                'managers.name as manager_name',
            )
            ->orderBy('leave_requests.start_date')
            ->get()
            ->all();
    }

    /**
     * Company-wide decided requests (approved or rejected), regardless of
     * which HR user gave the final sign-off — consistent with
     * pendingForHr() already being company-wide rather than scoped to a
     * specific HR user's own actions.
     */
    public function historyForHr(int $perPage = 10): LengthAwarePaginator
    {
        return DB::table('leave_requests')
            ->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->leftJoin('users as managers', 'managers.id', '=', 'leave_requests.approver_id')
            ->leftJoin('users as hr_approvers', 'hr_approvers.id', '=', 'leave_requests.hr_approver_id')
            ->whereIn('leave_requests.status', [LeaveRequestStatus::Approved->value, LeaveRequestStatus::Rejected->value])
            ->select(
                'leave_requests.id',
                'users.name as employee_name',
                'leave_types.name as leave_type_name',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.is_half_day',
                'leave_requests.total_days',
                'leave_requests.status',
                'leave_requests.decision_note',
                'leave_requests.decided_at',
                'managers.name as manager_name',
                'hr_approvers.name as hr_approver_name',
            )
            ->orderByDesc('leave_requests.decided_at')
            ->paginate($perPage);
    }

    /**
     * Approved requests for a manager's team (direct reports, plus anyone
     * whose department they manage but who has no direct manager of their
     * own — same priority rule as pendingForApprover(): direct manager_id
     * always wins over the department's manager) that overlap the given
     * date range — the data source for the team calendar. Sourced from
     * leave_requests rather than attendances: it's the one row-per-request
     * record with the leave type, half-day flag, and reason a calendar
     * event needs, and it doesn't depend on the best-effort attendance
     * auto-link having succeeded.
     *
     * @return array<int, object>
     */
    public function approvedForTeamBetween(int $managerId, string $start, string $end): array
    {
        return DB::table('leave_requests')
            ->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_requests.status', LeaveRequestStatus::Approved->value)
            ->where('leave_requests.start_date', '<=', $end)
            ->where('leave_requests.end_date', '>=', $start)
            ->where(function ($query) use ($managerId): void {
                $query->where('users.manager_id', $managerId)
                    ->orWhere(function ($query) use ($managerId): void {
                        $query->whereNull('users.manager_id')
                            ->where('departments.manager_id', $managerId);
                    });
            })
            ->select(
                'leave_requests.id',
                'users.name as employee_name',
                'leave_types.name as leave_type_name',
                'leave_requests.start_date',
                'leave_requests.end_date',
                'leave_requests.is_half_day',
                'leave_requests.reason',
            )
            ->orderBy('leave_requests.start_date')
            ->get()
            ->all();
    }

    /**
     * Notification delivery is a best-effort side effect, not part of the
     * core business action — a mail server being unreachable (e.g. Mailpit
     * not running locally) must never crash an otherwise-successful leave
     * submission or decision. Same "allowed to fail silently, but logged"
     * philosophy this app already applies to the attendance auto-link.
     */
    private function safeNotify(User $notifiable, Notification $notification): void
    {
        try {
            $notifiable->notify($notification);
        } catch (Throwable $exception) {
            Log::warning('Notification delivery failed: '.$notification::class, [
                'notifiable_id' => $notifiable->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Notifies the employee's one assigned manager — their direct manager_id
     * if set, otherwise their department's manager (same priority rule used
     * by LeaveRequestPolicy and pendingForApprover(): the department manager
     * is a fallback, not a second notified manager).
     */
    private function notifyManagersOfNewRequest(
        int $leaveRequestId,
        int $employeeId,
        int $leaveTypeId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        float $totalDays,
        string $reason,
    ): void {
        $assignment = DB::table('users as employees')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->where('employees.id', $employeeId)
            ->select('employees.manager_id as direct_manager_id', 'departments.manager_id as department_manager_id')
            ->first();

        if ($assignment === null) {
            return;
        }

        $managerId = $assignment->direct_manager_id ?? $assignment->department_manager_id;

        if ($managerId === null) {
            return;
        }

        $employeeName = DB::table('users')->where('id', $employeeId)->value('name') ?? 'An employee';
        $leaveTypeName = DB::table('leave_types')->where('id', $leaveTypeId)->value('name') ?? 'Leave';

        $manager = User::find($managerId);

        if ($manager === null) {
            return;
        }

        $this->safeNotify($manager, new NewLeaveRequestNotification(
            leaveRequestId: $leaveRequestId,
            employeeName: $employeeName,
            leaveTypeName: $leaveTypeName,
            startDate: $startDate->toDateString(),
            endDate: $endDate->toDateString(),
            totalDays: $totalDays,
            reason: $reason,
        ));
    }

    /**
     * Notifies every active HR user once a manager has forwarded a request
     * to the final approval stage. Unlike manager notification (one
     * assigned manager per employee), HR has no per-employee assignment,
     * so every active HR user is notified — same recipient set used by
     * the scheduled monthly report.
     */
    private function notifyHrOfPendingApproval(LeaveRequest $leaveRequest, User $manager): void
    {
        $employeeName = DB::table('users')->where('id', $leaveRequest->user_id)->value('name') ?? 'An employee';
        $leaveTypeName = DB::table('leave_types')->where('id', $leaveRequest->leave_type_id)->value('name') ?? 'Leave';

        $hrUsers = User::where('role', UserRole::Hr)->where('is_active', true)->get();
        $isSelfSubmitted = $leaveRequest->user_id === $manager->id;

        foreach ($hrUsers as $hrUser) {
            $this->safeNotify($hrUser, new LeaveRequestAwaitingHrApprovalNotification(
                leaveRequestId: $leaveRequest->id,
                employeeName: $employeeName,
                leaveTypeName: $leaveTypeName,
                startDate: $leaveRequest->start_date->toDateString(),
                endDate: $leaveRequest->end_date->toDateString(),
                totalDays: (float) $leaveRequest->total_days,
                managerName: $manager->name,
                isSelfSubmitted: $isSelfSubmitted,
            ));
        }
    }

    /**
     * Auto-links approved leave into attendance so it's the single source of
     * truth (spec requirement). Only working days get an on_leave record —
     * skipping weekends/holidays means this never collides with a real
     * check-in on a day the employee wasn't on leave for, and it means a
     * half-day request (a single day) still gets attendance coverage as long
     * as that day is a working day. markOnLeave() is idempotent, so calling
     * this more than once for the same request/day is always safe.
     */
    private function linkApprovedLeaveToAttendance(LeaveRequest $leaveRequest): void
    {
        $cursor = CarbonImmutable::parse($leaveRequest->start_date);
        $end = CarbonImmutable::parse($leaveRequest->end_date);

        while ($cursor->lessThanOrEqualTo($end)) {
            if ($this->schedule->isWorkingDay($cursor->toDateString())) {
                $this->attendance->markOnLeave($leaveRequest->user_id, $cursor->toDateString());
            }

            $cursor = $cursor->addDay();
        }
    }

    private function notifyEmployeeOfDecision(
        LeaveRequest $leaveRequest,
        User $approver,
        ?string $note,
        LeaveRequestStatus $status,
    ): void {
        $employee = User::find($leaveRequest->user_id);

        if ($employee === null) {
            return;
        }

        $leaveTypeName = DB::table('leave_types')->where('id', $leaveRequest->leave_type_id)->value('name') ?? 'Leave';

        $this->safeNotify($employee, new LeaveRequestStatusNotification(
            leaveRequestId: $leaveRequest->id,
            leaveTypeName: $leaveTypeName,
            startDate: $leaveRequest->start_date->toDateString(),
            endDate: $leaveRequest->end_date->toDateString(),
            status: $status,
            approverName: $approver->name,
            decisionNote: $note,
        ));
    }
}
