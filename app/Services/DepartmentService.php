<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Department;
use Illuminate\Support\Facades\DB;

final class DepartmentService
{
    /**
     * @return array<int, object>
     */
    public function list(): array
    {
        return DB::table('departments')
            ->leftJoin('users as managers', 'managers.id', '=', 'departments.manager_id')
            ->select(
                'departments.id',
                'departments.name',
                'departments.manager_id',
                'managers.name as manager_name',
                'managers.is_active as manager_is_active',
                'departments.is_active',
            )
            ->orderBy('departments.name')
            ->get()
            ->all();
    }

    /**
     * Active departments for dropdown selection, plus the currently assigned
     * one even if it has since been deactivated.
     *
     * @return array<int, object>
     */
    public function options(?int $currentDepartmentId): array
    {
        $departments = DB::table('departments')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($currentDepartmentId !== null && ! $departments->contains('id', $currentDepartmentId)) {
            $current = DB::table('departments')->where('id', $currentDepartmentId)->first();

            if ($current !== null) {
                $departments->push($current);
            }
        }

        return $departments->all();
    }

    /**
     * Uses the Eloquent model (not the query builder) so DepartmentObserver's
     * manager <-> department_id sync fires — that observer only listens for
     * Eloquent model events, which a raw DB::table() write would bypass.
     */
    public function create(string $name, ?int $managerId): int
    {
        return Department::create([
            'name' => $name,
            'manager_id' => $managerId,
        ])->id;
    }

    public function update(int $departmentId, string $name, ?int $managerId): void
    {
        Department::findOrFail($departmentId)->update([
            'name' => $name,
            'manager_id' => $managerId,
        ]);
    }

    public function setActive(int $departmentId, bool $active): void
    {
        DB::table('departments')->where('id', $departmentId)->update(['is_active' => $active]);
    }
}
