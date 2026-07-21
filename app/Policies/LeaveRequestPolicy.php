<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\LeaveRequestStatus;
use App\Enums\UserRole;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaveRequestPolicy
{
    /**
     * Who may act on a request depends on which stage it's currently in:
     * the employee's assigned manager decides the PendingManager stage,
     * and any HR user decides the final PendingHR stage. Approve and
     * reject share the same rule — whoever can approve a request at its
     * current stage can also reject it there.
     */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->canActAtCurrentStage($user, $leaveRequest);
    }

    public function reject(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->canActAtCurrentStage($user, $leaveRequest);
    }

    private function canActAtCurrentStage(User $user, LeaveRequest $leaveRequest): bool
    {
        return match ($leaveRequest->status) {
            LeaveRequestStatus::PendingManager => $this->isAssignedManagerOf($user, $leaveRequest),
            LeaveRequestStatus::PendingHR => $user->role === UserRole::Hr,
            default => false,
        };
    }

    /**
     * A manager is "assigned" to an employee via the employee's direct
     * manager_id; the employee's department manager only applies as a
     * fallback when the employee has no direct manager of their own — an
     * employee always has exactly one assigned manager, never two.
     */
    private function isAssignedManagerOf(User $user, LeaveRequest $leaveRequest): bool
    {
        if ($user->role !== UserRole::Manager) {
            return false;
        }

        return DB::table('users as employees')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->where('employees.id', $leaveRequest->user_id)
            ->where(function ($query) use ($user): void {
                $query->where('employees.manager_id', $user->id)
                    ->orWhere(function ($query) use ($user): void {
                        $query->whereNull('employees.manager_id')
                            ->where('departments.manager_id', $user->id);
                    });
            })
            ->exists();
    }
}
