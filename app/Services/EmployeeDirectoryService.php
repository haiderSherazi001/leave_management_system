<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class EmployeeDirectoryService
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
    ) {}

    /**
     * @return array<int, object>
     */
    public function list(): array
    {
        return DB::table('users as employees')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->leftJoin('users as managers', 'managers.id', '=', 'employees.manager_id')
            ->select(
                'employees.id',
                'employees.name',
                'employees.email',
                'employees.role',
                'employees.is_active',
                'employees.department_id',
                'departments.name as department_name',
                'employees.manager_id',
                'managers.name as manager_name',
                'employees.joined_at',
            )
            ->orderBy('employees.name')
            ->get()
            ->all();
    }

    /**
     * Active managers/HR for dropdown selection, plus the currently assigned
     * one even if it has since been deactivated (so editing a record never
     * silently drops a valid existing assignment). Enforces the strict
     * top-down hierarchy: HR is never assigned a manager (empty options),
     * a Manager may only be offered HR (never another Manager), and an
     * Employee may be offered either.
     *
     * @return array<int, object>
     */
    public function managerOptions(?int $currentManagerId, ?string $subjectRole = null): array
    {
        if ($subjectRole === UserRole::Hr->value) {
            return [];
        }

        $allowedRoles = $subjectRole === UserRole::Manager->value ? ['hr'] : ['manager', 'hr'];

        $managers = DB::table('users')
            ->whereIn('role', $allowedRoles)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($currentManagerId !== null && ! $managers->contains('id', $currentManagerId)) {
            $current = DB::table('users')->where('id', $currentManagerId)->first();

            if ($current !== null) {
                $managers->push($current);
            }
        }

        return $managers->all();
    }

    public function create(
        string $name,
        string $email,
        string $password,
        string $role,
        ?int $departmentId,
        ?int $managerId,
        string $joinedAt,
    ): User {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => $role,
            'department_id' => $departmentId,
            'manager_id' => $managerId,
            'joined_at' => $joinedAt,
        ]);

        $this->balances->provisionForUser($user->id, (int) date('Y', strtotime($joinedAt)));

        return $user;
    }

    public function update(
        int $userId,
        string $name,
        string $email,
        ?string $password,
        string $role,
        ?int $departmentId,
        ?int $managerId,
        string $joinedAt,
    ): void {
        $data = [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'department_id' => $departmentId,
            'manager_id' => $managerId,
            'joined_at' => $joinedAt,
        ];

        if ($password !== null && $password !== '') {
            $data['password'] = Hash::make($password);
        }

        DB::table('users')->where('id', $userId)->update($data);
    }

    /**
     * Deactivating a user blocks their future logins (enforced at login
     * time). Refuses to deactivate the last remaining active HR account,
     * since that would lock everyone out of this admin area.
     */
    public function setActive(int $userId, bool $active): void
    {
        if (! $active) {
            $user = DB::table('users')->where('id', $userId)->first();

            if ($user !== null && $user->role === 'hr') {
                $remainingActiveHr = DB::table('users')
                    ->where('role', 'hr')
                    ->where('is_active', true)
                    ->where('id', '!=', $userId)
                    ->count();

                if ($remainingActiveHr === 0) {
                    throw new DomainException('Cannot deactivate the last active HR account.');
                }
            }
        }

        DB::table('users')->where('id', $userId)->update(['is_active' => $active]);
    }
}
