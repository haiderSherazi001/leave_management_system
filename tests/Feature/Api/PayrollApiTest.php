<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_without_a_token_is_unauthorized(): void
    {
        $this->getJson('/api/v1/payroll/summary?start_date=2026-08-01&end_date=2026-08-31')
            ->assertUnauthorized();
    }

    public function test_request_with_an_invalid_token_is_unauthorized(): void
    {
        $this->withHeader('Authorization', 'Bearer this-is-not-a-real-token')
            ->getJson('/api/v1/payroll/summary?start_date=2026-08-01&end_date=2026-08-31')
            ->assertUnauthorized();
    }

    public function test_non_hr_users_cannot_access_the_payroll_summary(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();

        foreach ([$employee, $manager] as $user) {
            $token = $user->createToken('mobile-app-token')->plainTextToken;

            $this->withHeader('Authorization', "Bearer {$token}")
                ->getJson('/api/v1/payroll/summary?start_date=2026-08-01&end_date=2026-08-31')
                ->assertForbidden();
        }
    }

    public function test_request_missing_date_parameters_is_unprocessable(): void
    {
        $hr = User::factory()->hr()->create();
        $token = $hr->createToken('payroll-integration-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/payroll/summary')
            ->assertUnprocessable();
    }

    public function test_a_valid_token_returns_the_consolidated_payroll_summary(): void
    {
        WorkSchedule::factory()->create(['working_days' => [1, 2, 3, 4, 5]]);

        $hr = User::factory()->hr()->create();
        $token = $hr->createToken('payroll-integration-token')->plainTextToken;

        $employee = User::factory()->create(['name' => 'Jane Employee']);
        $leaveType = LeaveType::factory()->create();

        $monday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
        $tuesday = $monday->addDay();
        $wednesday = $monday->addDays(2);
        $thursday = $monday->addDays(3);
        $friday = $monday->addDays(4);
        $sunday = $monday->addDays(6);

        Attendance::factory()->create([
            'user_id' => $employee->id,
            'date' => $monday->toDateString(),
            'status' => 'present',
        ]);
        Attendance::factory()->late()->create([
            'user_id' => $employee->id,
            'date' => $tuesday->toDateString(),
        ]);
        // Wednesday: deliberately no attendance row and no leave -> Absent.

        LeaveRequest::factory()->approved()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $employee->id,
            'hr_approver_id' => $employee->id,
            'start_date' => $thursday->toDateString(),
            'end_date' => $friday->toDateString(),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/payroll/summary?start_date={$monday->toDateString()}&end_date={$sunday->toDateString()}")
            ->assertOk()
            ->assertJsonStructure([
                'start_date',
                'end_date',
                'employees' => [
                    '*' => ['user_id', 'name', 'days_present', 'days_absent_or_late', 'approved_leave_days', 'total_hours_worked'],
                ],
            ]);

        $response->assertJsonFragment([
            'user_id' => $employee->id,
            'name' => 'Jane Employee',
            'days_present' => 1,
            'days_absent_or_late' => 2,
            'approved_leave_days' => 2,
        ]);
    }

    public function test_inactive_employees_are_excluded_from_the_summary(): void
    {
        $hr = User::factory()->hr()->create();
        $token = $hr->createToken('payroll-integration-token')->plainTextToken;

        $inactiveEmployee = User::factory()->inactive()->create(['name' => 'Old Employee']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/payroll/summary?start_date=2026-08-01&end_date=2026-08-07')
            ->assertOk();

        $response->assertJsonMissing(['name' => 'Old Employee']);
    }
}
