<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrLeaveApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile-app-token')->plainTextToken];
    }

    public function test_pending_is_company_wide_regardless_of_which_managers_team(): void
    {
        $hr = User::factory()->hr()->create();
        $managerA = User::factory()->manager()->create();
        $managerB = User::factory()->manager()->create();
        $leaveType = LeaveType::factory()->create();

        $employeeA = User::factory()->create(['name' => 'Team A Employee', 'manager_id' => $managerA->id]);
        $employeeB = User::factory()->create(['name' => 'Team B Employee', 'manager_id' => $managerB->id]);

        LeaveRequest::factory()->create([
            'user_id' => $employeeA->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_hr',
            'approver_id' => $managerA->id,
        ]);
        LeaveRequest::factory()->create([
            'user_id' => $employeeB->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_hr',
            'approver_id' => $managerB->id,
        ]);

        $response = $this->withHeaders($this->authHeader($hr))
            ->getJson('/api/v1/hr/leave-requests/pending')
            ->assertOk();

        $response->assertJsonFragment(['employee_name' => 'Team A Employee']);
        $response->assertJsonFragment(['employee_name' => 'Team B Employee']);
    }

    public function test_non_hr_cannot_view_pending_requests(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();

        foreach ([$employee, $manager] as $user) {
            $this->withHeaders($this->authHeader($user))
                ->getJson('/api/v1/hr/leave-requests/pending')
                ->assertForbidden();
        }
    }

    public function test_approve_finalizes_the_request_and_deducts_balance(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_hr',
            'approver_id' => $manager->id,
            'total_days' => 2,
        ]);

        $this->withHeaders($this->authHeader($hr))
            ->postJson("/api/v1/hr/leave-requests/{$leaveRequest->id}/approve", ['note' => 'Approved'])
            ->assertOk();

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'hr_approver_id' => $hr->id,
        ]);
        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'used_days' => 2,
        ]);
    }

    public function test_reject_is_final(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_hr',
            'approver_id' => $manager->id,
        ]);

        $this->withHeaders($this->authHeader($hr))
            ->postJson("/api/v1/hr/leave-requests/{$leaveRequest->id}/reject")
            ->assertOk();

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'rejected',
            'hr_approver_id' => $hr->id,
        ]);
    }

    public function test_hr_cannot_act_on_a_request_still_pending_manager_approval(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_manager',
        ]);

        $this->withHeaders($this->authHeader($hr))
            ->postJson("/api/v1/hr/leave-requests/{$leaveRequest->id}/approve")
            ->assertForbidden();

        $this->assertDatabaseHas('leave_requests', ['id' => $leaveRequest->id, 'status' => 'pending_manager']);
    }

    public function test_history_is_company_wide_regardless_of_which_hr_user_finalized(): void
    {
        $hrA = User::factory()->hr()->create();
        $hrB = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['name' => 'Decided Employee', 'manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        LeaveRequest::factory()->approved()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $manager->id,
            'hr_approver_id' => $hrB->id,
        ]);

        $this->withHeaders($this->authHeader($hrA))
            ->getJson('/api/v1/hr/leave-requests/history')
            ->assertOk()
            ->assertJsonFragment(['employee_name' => 'Decided Employee']);
    }
}
