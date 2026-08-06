<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Department;
use App\Models\User;
use App\Support\Tenant;

class DepartmentObserver
{
    /**
     * Keeps a manager's own users.department_id in sync with the department
     * they head, so the Employees admin screen never shows an empty
     * department for someone who is actually a department's manager. The
     * outgoing manager (if any) is cleared back to no department rather than
     * left pointing at a department they no longer head.
     *
     * User carries no automatic tenant scope (see the model's own
     * docblock), so company_id is filtered explicitly here even though
     * $department->manager_id is only ever set to a same-company user by
     * the write paths that assign it.
     */
    public function saved(Department $department): void
    {
        if ($department->wasRecentlyCreated) {
            if ($department->manager_id !== null) {
                User::where('company_id', Tenant::id())->where('id', $department->manager_id)->update(['department_id' => $department->id]);
            }

            return;
        }

        if (! $department->wasChanged('manager_id')) {
            return;
        }

        $previousManagerId = $department->getOriginal('manager_id');

        if ($previousManagerId !== null) {
            User::where('company_id', Tenant::id())
                ->where('id', $previousManagerId)
                ->where('department_id', $department->id)
                ->update(['department_id' => null]);
        }

        if ($department->manager_id !== null) {
            User::where('company_id', Tenant::id())->where('id', $department->manager_id)->update(['department_id' => $department->id]);
        }
    }
}
