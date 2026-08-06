<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeaveTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(int $companyId): void
    {
        $leaveTypes = [
            [
                'name' => 'Annual',
                'code' => 'ANN',
                'yearly_allocation_days' => 20,
                'carry_forward_enabled' => true,
                'carry_forward_max_days' => 5,
                'description' => 'Annual paid vacation leave.',
            ],
            [
                'name' => 'Sick',
                'code' => 'SICK',
                'yearly_allocation_days' => 10,
                'carry_forward_enabled' => false,
                'carry_forward_max_days' => null,
                'description' => 'Paid leave for illness or medical appointments.',
            ],
            [
                'name' => 'Casual',
                'code' => 'CAS',
                'yearly_allocation_days' => 7,
                'carry_forward_enabled' => false,
                'carry_forward_max_days' => null,
                'description' => 'Short-notice personal leave.',
            ],
        ];

        foreach ($leaveTypes as $leaveType) {
            DB::table('leave_types')->updateOrInsert(
                ['code' => $leaveType['code'], 'company_id' => $companyId],
                [...$leaveType, 'company_id' => $companyId, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
