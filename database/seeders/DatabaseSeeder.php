<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Support\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database. WithoutModelEvents mutes
     * BelongsToCompany's creating-time auto-stamp, so company_id is passed
     * explicitly on every row created here.
     */
    public function run(LeaveBalanceService $balances): void
    {
        $company = Company::query()->firstOrCreate(['name' => 'Demo Company']);
        Tenant::set($company->id);

        $this->call(LeaveTypeSeeder::class, parameters: ['companyId' => $company->id]);

        $department = Department::query()->firstOrCreate(['name' => 'Engineering', 'company_id' => $company->id]);

        $manager = User::factory()->manager()->create([
            'name' => 'Morgan Manager',
            'email' => 'manager@leavedesk.test',
            'department_id' => $department->id,
            'company_id' => $company->id,
        ]);

        $department->update(['manager_id' => $manager->id]);

        $hr = User::factory()->hr()->create([
            'name' => 'Harper HR',
            'email' => 'hr@leavedesk.test',
            'company_id' => $company->id,
        ]);

        $employees = User::factory(3)->create([
            'department_id' => $department->id,
            'manager_id' => $manager->id,
            'company_id' => $company->id,
        ]);

        $year = (int) date('Y');
        $leaveTypeIds = LeaveType::query()->where('company_id', $company->id)->pluck('id');

        foreach ([$manager, $hr, ...$employees] as $user) {
            foreach ($leaveTypeIds as $leaveTypeId) {
                $balances->ensureBalanceForYear($user->id, $leaveTypeId, $year);
            }
        }

        Tenant::clear();
    }
}
