<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\User;
use App\Notifications\WelcomeNewEmployeeNotification;
use App\Support\Tenant;
use DomainException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

final class EmployeeDirectoryService
{
    public function __construct(
        private readonly LeaveBalanceService $balances,
    ) {}

    /**
     * @return array<int, object>
     */
    public function list(
        ?string $search = null,
        ?string $role = null,
        ?int $departmentId = null,
        ?string $status = null,
    ): array {
        return DB::table('users as employees')
            ->leftJoin('departments', 'departments.id', '=', 'employees.department_id')
            ->leftJoin('users as managers', 'managers.id', '=', 'employees.manager_id')
            ->where('employees.company_id', Tenant::id())
            ->select(
                'employees.id',
                'employees.name',
                'employees.email',
                'employees.role',
                'employees.is_active',
                'employees.department_id',
                'departments.name as department_name',
                'departments.is_active as department_is_active',
                'employees.manager_id',
                'managers.name as manager_name',
                'managers.is_active as manager_is_active',
                'employees.joined_at',
            )
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('employees.name', 'like', "%{$search}%")
                        ->orWhere('employees.email', 'like', "%{$search}%");
                });
            })
            ->when($role !== null && $role !== '', fn ($query) => $query->where('employees.role', $role))
            ->when($departmentId !== null, fn ($query) => $query->where('employees.department_id', $departmentId))
            ->when($status === 'active', fn ($query) => $query->where('employees.is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('employees.is_active', false))
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
            ->where('company_id', Tenant::id())
            ->whereIn('role', $allowedRoles)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($currentManagerId !== null && ! $managers->contains('id', $currentManagerId)) {
            $current = DB::table('users')->where('company_id', Tenant::id())->where('id', $currentManagerId)->first();

            if ($current !== null) {
                $managers->push($current);
            }
        }

        return $managers->all();
    }

    /**
     * HR never sets a password here — a random one is generated and never
     * surfaced anywhere, then a "set your password" link (the same signed
     * token this app's existing forgot-password flow already uses) is
     * emailed to the new employee. HR only ever hands out a manual password
     * via update() below, as a fallback if the invite email never arrives.
     */
    public function create(
        string $name,
        string $email,
        string $role,
        ?int $departmentId,
        ?int $managerId,
        string $joinedAt,
    ): User {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'role' => $role,
            'department_id' => $departmentId,
            'manager_id' => $managerId,
            'joined_at' => $joinedAt,
        ]);

        $this->balances->provisionForUser($user->id, (int) date('Y', strtotime($joinedAt)));
        $this->syncDepartmentHeadship($user->id, $role, $departmentId);

        $token = Password::createToken($user);
        $setPasswordUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);

        $this->safeNotify($user, new WelcomeNewEmployeeNotification($setPasswordUrl));

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

        DB::table('users')->where('id', $userId)->where('company_id', Tenant::id())->update($data);
        $this->syncDepartmentHeadship($userId, $role, $departmentId);
    }

    /**
     * Keeps departments.manager_id consistent with what the Employees screen
     * itself validates (departmentConflictRule already treats a manager-role
     * employee's department assignment as headship, blocking conflicts) - so
     * the write path needs to actually apply it, not just validate it.
     * Uses the Department model (not DB::table()) so DepartmentObserver's
     * existing manager <-> department_id sync fires and stays the single
     * source of truth for that half of the relationship.
     */
    private function syncDepartmentHeadship(int $userId, string $role, ?int $departmentId): void
    {
        $currentlyHeaded = Department::where('manager_id', $userId)->first();

        if ($currentlyHeaded !== null && ($role !== UserRole::Manager->value || $currentlyHeaded->id !== $departmentId)) {
            $currentlyHeaded->update(['manager_id' => null]);
        }

        if ($role === UserRole::Manager->value && $departmentId !== null) {
            $department = Department::find($departmentId);

            if ($department !== null && $department->manager_id !== $userId) {
                $department->update(['manager_id' => $userId]);
            }
        }
    }

    /**
     * Deactivating a user blocks their future logins (enforced at login
     * time). Refuses to deactivate the last remaining active HR account,
     * since that would lock everyone out of this admin area.
     */
    public function setActive(int $userId, bool $active): void
    {
        if (! $active) {
            $user = DB::table('users')->where('company_id', Tenant::id())->where('id', $userId)->first();

            if ($user !== null && $user->role === 'hr') {
                $remainingActiveHr = DB::table('users')
                    ->where('company_id', Tenant::id())
                    ->where('role', 'hr')
                    ->where('is_active', true)
                    ->where('id', '!=', $userId)
                    ->count();

                if ($remainingActiveHr === 0) {
                    throw new DomainException('Cannot deactivate the last active HR account.');
                }
            }
        }

        DB::table('users')->where('id', $userId)->where('company_id', Tenant::id())->update(['is_active' => $active]);
    }

    /**
     * A mail hiccup (e.g. Mailpit not running locally) must never block
     * creating the employee record itself - same "best-effort, log don't
     * crash" philosophy already applied to notifications in
     * LeaveRequestService.
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
}
