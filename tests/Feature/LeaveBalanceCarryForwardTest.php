<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveBalanceCarryForwardTest extends TestCase
{
    use RefreshDatabase;

    public function test_carry_forward_applies_leftover_days_when_under_the_cap(): void
    {
        $user = User::factory()->create();
        $leaveType = LeaveType::factory()->create([
            'yearly_allocation_days' => 20,
            'carry_forward_enabled' => true,
            'carry_forward_max_days' => 10,
        ]);

        LeaveBalance::factory()->create([
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2025,
            'allocated_days' => 20,
            'carried_forward_days' => 0,
            'used_days' => 15,
        ]);

        $service = $this->app->make(LeaveBalanceService::class);
        $service->ensureBalanceForYear($user->id, $leaveType->id, 2026);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'carried_forward_days' => 5,
        ]);
    }

    public function test_carry_forward_is_capped_at_the_leave_types_max(): void
    {
        $user = User::factory()->create();
        $leaveType = LeaveType::factory()->create([
            'yearly_allocation_days' => 20,
            'carry_forward_enabled' => true,
            'carry_forward_max_days' => 3,
        ]);

        LeaveBalance::factory()->create([
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2025,
            'allocated_days' => 20,
            'carried_forward_days' => 0,
            'used_days' => 5,
        ]);

        $service = $this->app->make(LeaveBalanceService::class);
        $service->ensureBalanceForYear($user->id, $leaveType->id, 2026);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'carried_forward_days' => 3,
        ]);
    }

    public function test_carry_forward_is_zero_when_disabled_on_the_leave_type(): void
    {
        $user = User::factory()->create();
        $leaveType = LeaveType::factory()->create([
            'yearly_allocation_days' => 20,
            'carry_forward_enabled' => false,
        ]);

        LeaveBalance::factory()->create([
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2025,
            'allocated_days' => 20,
            'carried_forward_days' => 0,
            'used_days' => 5,
        ]);

        $service = $this->app->make(LeaveBalanceService::class);
        $service->ensureBalanceForYear($user->id, $leaveType->id, 2026);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'carried_forward_days' => 0,
        ]);
    }

    public function test_carry_forward_is_zero_when_there_is_no_prior_year_balance(): void
    {
        $user = User::factory()->create();
        $leaveType = LeaveType::factory()->create([
            'yearly_allocation_days' => 20,
            'carry_forward_enabled' => true,
            'carry_forward_max_days' => 10,
        ]);

        $service = $this->app->make(LeaveBalanceService::class);
        $service->ensureBalanceForYear($user->id, $leaveType->id, 2026);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'carried_forward_days' => 0,
        ]);
    }

    public function test_carry_forward_is_uncapped_when_max_days_is_not_set(): void
    {
        $user = User::factory()->create();
        $leaveType = LeaveType::factory()->create([
            'yearly_allocation_days' => 20,
            'carry_forward_enabled' => true,
            'carry_forward_max_days' => null,
        ]);

        LeaveBalance::factory()->create([
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2025,
            'allocated_days' => 20,
            'carried_forward_days' => 0,
            'used_days' => 2,
        ]);

        $service = $this->app->make(LeaveBalanceService::class);
        $service->ensureBalanceForYear($user->id, $leaveType->id, 2026);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => 2026,
            'carried_forward_days' => 18,
        ]);
    }
}
