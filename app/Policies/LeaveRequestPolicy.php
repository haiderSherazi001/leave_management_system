<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LeaveRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeaveRequestPolicy
{
    /**
     * Phase 1 scope: only the employee's assigned manager may approve or
     * reject a leave request. Multi-level approval (manager -> HR) is
     * Phase 4, so HR is intentionally denied here regardless of role.
     */
    public function approve(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->isAssignedManagerOf($user, $leaveRequest);
    }

    public function reject(User $user, LeaveRequest $leaveRequest): bool
    {
        return $this->isAssignedManagerOf($user, $leaveRequest);
    }

    /**
     * A manager is "assigned" to an employee either directly (users.manager_id)
     * or as the head of the employee's department (departments.manager_id).
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
                    ->orWhere('departments.manager_id', $user->id);
            })
            ->exists();
    }
}
