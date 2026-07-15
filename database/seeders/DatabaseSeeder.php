<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(LeaveBalanceService $balances): void
    {
        $this->call(LeaveTypeSeeder::class);

        $department = Department::query()->firstOrCreate(['name' => 'Engineering']);

        $manager = User::factory()->manager()->create([
            'name' => 'Morgan Manager',
            'email' => 'manager@leavedesk.test',
            'department_id' => $department->id,
        ]);

        $department->update(['manager_id' => $manager->id]);

        $hr = User::factory()->hr()->create([
            'name' => 'Harper HR',
            'email' => 'hr@leavedesk.test',
        ]);

        $employees = User::factory(3)->create([
            'department_id' => $department->id,
            'manager_id' => $manager->id,
        ]);

        $year = (int) date('Y');
        $leaveTypeIds = LeaveType::query()->pluck('id');

        foreach ([$manager, $hr, ...$employees] as $user) {
            foreach ($leaveTypeIds as $leaveTypeId) {
                $balances->ensureBalanceForYear($user->id, $leaveTypeId, $year);
            }
        }
    }
}
