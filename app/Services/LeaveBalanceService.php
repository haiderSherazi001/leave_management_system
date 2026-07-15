<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class LeaveBalanceService
{
    public function find(int $userId, int $leaveTypeId, int $year): ?object
    {
        return DB::table('leave_balances')
            ->where('user_id', $userId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();
    }

    public function remainingDays(int $userId, int $leaveTypeId, int $year): float
    {
        $balance = $this->find($userId, $leaveTypeId, $year);

        if ($balance === null) {
            return 0.0;
        }

        return (float) $balance->allocated_days + (float) $balance->carried_forward_days - (float) $balance->used_days;
    }

    public function deductDays(int $userId, int $leaveTypeId, int $year, float $days): void
    {
        DB::table('leave_balances')
            ->where('user_id', $userId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->increment('used_days', $days);
    }

    public function restoreDays(int $userId, int $leaveTypeId, int $year, float $days): void
    {
        DB::table('leave_balances')
            ->where('user_id', $userId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->decrement('used_days', $days);
    }

    /**
     * @return array<int, object>
     */
    public function forUser(int $userId, int $year): array
    {
        return DB::table('leave_balances')
            ->join('leave_types', 'leave_types.id', '=', 'leave_balances.leave_type_id')
            ->where('leave_balances.user_id', $userId)
            ->where('leave_balances.year', $year)
            ->select(
                'leave_balances.id',
                'leave_balances.leave_type_id',
                'leave_types.name as leave_type_name',
                'leave_balances.allocated_days',
                'leave_balances.carried_forward_days',
                'leave_balances.used_days',
            )
            ->orderBy('leave_types.name')
            ->get()
            ->all();
    }

    public function ensureBalanceForYear(int $userId, int $leaveTypeId, int $year): void
    {
        $exists = DB::table('leave_balances')
            ->where('user_id', $userId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->exists();

        if ($exists) {
            return;
        }

        $leaveType = DB::table('leave_types')->where('id', $leaveTypeId)->first();

        if ($leaveType === null) {
            return;
        }

        DB::table('leave_balances')->insert([
            'user_id' => $userId,
            'leave_type_id' => $leaveTypeId,
            'year' => $year,
            'allocated_days' => $leaveType->yearly_allocation_days,
            'carried_forward_days' => 0,
            'used_days' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Ensures the given user has a balance row for every currently-active
     * leave type in the given year. Used when a new employee is created.
     */
    public function provisionForUser(int $userId, int $year): void
    {
        $leaveTypeIds = DB::table('leave_types')->where('is_active', true)->pluck('id');

        foreach ($leaveTypeIds as $leaveTypeId) {
            $this->ensureBalanceForYear($userId, $leaveTypeId, $year);
        }
    }

    /**
     * Ensures every currently-active user has a balance row for the given
     * leave type in the given year. Used when a leave type is created or
     * reactivated, so existing employees aren't left without a balance for it.
     */
    public function provisionForLeaveType(int $leaveTypeId, int $year): void
    {
        $userIds = DB::table('users')->where('is_active', true)->pluck('id');

        foreach ($userIds as $userId) {
            $this->ensureBalanceForYear($userId, $leaveTypeId, $year);
        }
    }
}
