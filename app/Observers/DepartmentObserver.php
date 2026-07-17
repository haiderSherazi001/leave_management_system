<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Department;
use App\Models\User;

class DepartmentObserver
{
    /**
     * Keeps a manager's own users.department_id in sync with the department
     * they head, so the Employees admin screen never shows an empty
     * department for someone who is actually a department's manager. The
     * outgoing manager (if any) is cleared back to no department rather than
     * left pointing at a department they no longer head.
     */
    public function saved(Department $department): void
    {
        if ($department->wasRecentlyCreated) {
            if ($department->manager_id !== null) {
                User::where('id', $department->manager_id)->update(['department_id' => $department->id]);
            }

            return;
        }

        if (! $department->wasChanged('manager_id')) {
            return;
        }

        $previousManagerId = $department->getOriginal('manager_id');

        if ($previousManagerId !== null) {
            User::where('id', $previousManagerId)
                ->where('department_id', $department->id)
                ->update(['department_id' => null]);
        }

        if ($department->manager_id !== null) {
            User::where('id', $department->manager_id)->update(['department_id' => $department->id]);
        }
    }
}
