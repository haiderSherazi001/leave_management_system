<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class HolidayService
{
    /**
     * @return array<int, object>
     */
    public function list(?string $search = null, ?int $year = null): array
    {
        return DB::table('holidays')
            ->where('company_id', Tenant::id())
            ->when(
                $search !== null && $search !== '',
                fn ($query) => $query->where('name', 'like', "%{$search}%"),
            )
            ->when($year !== null, fn ($query) => $query->whereYear('date', $year))
            ->orderBy('date')
            ->get()
            ->all();
    }

    /**
     * Distinct years with at least one holiday configured, newest first, for
     * the admin screen's year filter dropdown. Computed in PHP rather than a
     * SQL YEAR()/strftime() extraction so it behaves identically across the
     * MySQL (production) and SQLite (test) drivers this app runs on.
     *
     * @return array<int, int>
     */
    public function years(): array
    {
        return DB::table('holidays')
            ->where('company_id', Tenant::id())
            ->pluck('date')
            ->map(fn ($date) => (int) Carbon::parse($date)->format('Y'))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    public function create(string $date, string $name): int
    {
        return DB::table('holidays')->insertGetId([
            'company_id' => Tenant::id(),
            'date' => $date,
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function update(int $holidayId, string $date, string $name): void
    {
        DB::table('holidays')
            ->where('id', $holidayId)
            ->where('company_id', Tenant::id())
            ->update([
                'date' => $date,
                'name' => $name,
                'updated_at' => now(),
            ]);
    }

    public function delete(int $holidayId): void
    {
        DB::table('holidays')
            ->where('id', $holidayId)
            ->where('company_id', Tenant::id())
            ->delete();
    }

    public function isHoliday(string $date): bool
    {
        return DB::table('holidays')
            ->where('company_id', Tenant::id())
            ->where('date', $date)
            ->exists();
    }

    public function forDate(string $date): ?object
    {
        return DB::table('holidays')
            ->where('company_id', Tenant::id())
            ->where('date', $date)
            ->first();
    }

    /**
     * @return array<int, object>
     */
    public function betweenDates(string $start, string $end): array
    {
        return DB::table('holidays')
            ->where('company_id', Tenant::id())
            ->whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->get()
            ->all();
    }

    /**
     * The next N holidays from today onward, for an "Upcoming Holidays" list.
     *
     * @return array<int, object>
     */
    public function upcoming(int $limit = 5): array
    {
        return DB::table('holidays')
            ->where('company_id', Tenant::id())
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->limit($limit)
            ->get()
            ->all();
    }
}
