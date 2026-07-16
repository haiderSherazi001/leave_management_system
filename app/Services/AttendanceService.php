<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AttendanceStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

final class AttendanceService
{
    public function checkIn(int $userId, string $date): void
    {
        $existing = DB::table('attendances')->where('user_id', $userId)->where('date', $date)->first();

        if ($existing !== null && $existing->check_in_at !== null) {
            throw new DomainException('You have already checked in today.');
        }

        if ($existing === null) {
            DB::table('attendances')->insert([
                'user_id' => $userId,
                'date' => $date,
                'check_in_at' => now(),
                'status' => AttendanceStatus::Present->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('attendances')->where('id', $existing->id)->update([
            'check_in_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function checkOut(int $userId, string $date): void
    {
        $existing = DB::table('attendances')->where('user_id', $userId)->where('date', $date)->first();

        if ($existing === null || $existing->check_in_at === null) {
            throw new DomainException('You must check in before checking out.');
        }

        if ($existing->check_out_at !== null) {
            throw new DomainException('You have already checked out today.');
        }

        DB::table('attendances')->where('id', $existing->id)->update([
            'check_out_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findForDate(int $userId, string $date): ?object
    {
        return DB::table('attendances')
            ->where('user_id', $userId)
            ->where('date', $date)
            ->first();
    }

    /**
     * @return array<int, object>
     */
    public function historyForUser(int $userId, int $limit = 30): array
    {
        return DB::table('attendances')
            ->where('user_id', $userId)
            ->orderByDesc('date')
            ->limit($limit)
            ->get()
            ->all();
    }
}
