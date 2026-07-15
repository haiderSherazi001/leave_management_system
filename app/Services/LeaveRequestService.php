<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Notifications\LeaveRequestStatusNotification;
use App\Notifications\NewLeaveRequestNotification;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class LeaveRequestService
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
    ) {}

    public function calculateTotalDays(CarbonImmutable $startDate, CarbonImmutable $endDate, bool $isHalfDay): float
    {
        if ($isHalfDay) {
            return 0.5;
        }

        return (float) $startDate->diffInDays($endDate) + 1;
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
        $year = $startDate->year;
        $remaining = $this->balances->remainingDays($userId, $leaveTypeId, $year);

        if ($totalDays > $remaining) {
            throw new DomainException("Requested {$totalDays} day(s) exceeds the remaining balance of {$remaining} day(s).");
        }

        // Leadership (manager/HR) requests bypass the approval queue entirely: they're
        // recorded as already-approved and their balance is deducted immediately, so
        // attendance reporting reflects their leave without waiting on an approver who,
        // for a manager or HR applying for their own leave, may not meaningfully exist.
        $isLeadership = in_array($role, [UserRole::Manager->value, UserRole::Hr->value], true);

        $leaveRequestId = DB::table('leave_requests')->insertGetId([
            'user_id' => $userId,
            'leave_type_id' => $leaveTypeId,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'is_half_day' => $isHalfDay,
            'total_days' => $totalDays,
            'reason' => $reason,
            'status' => $isLeadership ? LeaveRequestStatus::Approved->value : LeaveRequestStatus::Pending->value,
            'approver_id' => $isLeadership ? $userId : null,
            'decision_note' => $isLeadership ? 'Auto-approved' : null,
            'decided_at' => $isLeadership ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($isLeadership) {
            $this->balances->deductDays($userId, $leaveTypeId, $year, $totalDays);
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
            ->whereIn('status', [LeaveRequestStatus::Pending->value, LeaveRequestStatus::Approved->value])
            ->where('start_date', '<=', $endDate->toDateString())
            ->where('end_date', '>=', $startDate->toDateString());

        if ($excludingRequestId !== null) {
            $query->where('id', '!=', $excludingRequestId);
        }

        return $query->exists();
    }

    /**
     * Authorization is enforced here (via LeaveRequestPolicy), not just in the
     * calling UI, so it can't be bypassed by any future caller of this method.
     */
    public function approve(int $leaveRequestId, User $approver, ?string $note = null): void
    {
        $leaveRequest = LeaveRequest::find($leaveRequestId);

        if ($leaveRequest === null) {
            throw new DomainException('Leave request not found.');
        }

        Gate::forUser($approver)->authorize('approve', $leaveRequest);

        if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
            throw new DomainException('Only pending requests can be approved.');
        }

        $year = $leaveRequest->start_date->year;
        $remaining = $this->balances->remainingDays($leaveRequest->user_id, $leaveRequest->leave_type_id, $year);

        if ((float) $leaveRequest->total_days > $remaining) {
            throw new DomainException('Employee no longer has sufficient balance for this request.');
        }

        DB::transaction(function () use ($leaveRequest, $approver, $note, $year): void {
            DB::table('leave_requests')
                ->where('id', $leaveRequest->id)
                ->update([
                    'status' => LeaveRequestStatus::Approved->value,
                    'approver_id' => $approver->id,
                    'decision_note' => $note,
                    'decided_at' => now(),
                    'updated_at' => now(),
                ]);

            $this->balances->deductDays(
                $leaveRequest->user_id,
                $leaveRequest->leave_type_id,
                $year,
                (float) $leaveRequest->total_days,
            );
        });

        // Outside the transaction: a notification failure must never roll back a real approval.
        $this->notifyEmployeeOfDecision($leaveRequest, $approver, $note, LeaveRequestStatus::Approved);
    }

    public function reject(int $leaveRequestId, User $approver, ?string $note = null): void
    {
        $leaveRequest = LeaveRequest::find($leaveRequestId);

        if ($leaveRequest === null) {
            throw new DomainException('Leave request not found.');
        }

        Gate::forUser($approver)->authorize('reject', $leaveRequest);

        if ($leaveRequest->status !== LeaveRequestStatus::Pending) {
            throw new DomainException('Only pending requests can be rejected.');
        }

        DB::table('leave_requests')
            ->where('id', $leaveRequest->id)
            ->update([
                'status' => LeaveRequestStatus::Rejected->value,
                'approver_id' => $approver->id,
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
            )
            ->orderByDesc('leave_requests.created_at')
            ->get()
            ->all();
    }

    /**
     * Pending requests visible to a manager: their direct reports, plus anyone
     * in a department they head. Phase 1 scope is manager-only — HR does not
     * see this queue (multi-level approval is Phase 4).
     *
     * @return array<int, object>
     */
    public function pendingForApprover(int $managerId): array
    {
        return DB::table('leave_requests')
            ->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->leftJoin('departments', 'departments.id', '=', 'users.department_id')
            ->join('leave_types', 'leave_types.id', '=', 'leave_requests.leave_type_id')
            ->where('leave_requests.status', LeaveRequestStatus::Pending->value)
            ->where(function ($query) use ($managerId): void {
                $query->where('users.manager_id', $managerId)
                    ->orWhere('departments.manager_id', $managerId);
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
     * Notifies the employee's direct manager and/or department head (same
     * "assigned manager" join used by LeaveRequestPolicy and pendingForApprover).
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

        $managerIds = array_unique(array_filter([
            $assignment->direct_manager_id,
            $assignment->department_manager_id,
        ]));

        if ($managerIds === []) {
            return;
        }

        $employeeName = DB::table('users')->where('id', $employeeId)->value('name') ?? 'An employee';
        $leaveTypeName = DB::table('leave_types')->where('id', $leaveTypeId)->value('name') ?? 'Leave';

        foreach ($managerIds as $managerId) {
            $manager = User::find($managerId);

            $manager?->notify(new NewLeaveRequestNotification(
                leaveRequestId: $leaveRequestId,
                employeeName: $employeeName,
                leaveTypeName: $leaveTypeName,
                startDate: $startDate->toDateString(),
                endDate: $endDate->toDateString(),
                totalDays: $totalDays,
                reason: $reason,
            ));
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

        $employee->notify(new LeaveRequestStatusNotification(
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
