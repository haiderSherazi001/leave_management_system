<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class HolidayService
{
    /**
     * @return array<int, object>
     */
    public function list(): array
    {
        return DB::table('holidays')
            ->orderBy('date')
            ->get()
            ->all();
    }

    public function create(string $date, string $name): int
    {
        return DB::table('holidays')->insertGetId([
            'date' => $date,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function update(int $holidayId, string $date, string $name): void
    {
        DB::table('holidays')->where('id', $holidayId)->update([
            'date' => $date,
            'name' => $name,
            'updated_at' => now(),
        ]);
    }

    public function delete(int $holidayId): void
    {
        DB::table('holidays')->where('id', $holidayId)->delete();
    }

    public function isHoliday(string $date): bool
    {
        return DB::table('holidays')->where('date', $date)->exists();
    }
}
