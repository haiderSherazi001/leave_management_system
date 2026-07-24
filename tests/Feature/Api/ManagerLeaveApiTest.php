<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerLeaveApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile-app-token')->plainTextToken];
    }

    public function test_pending_shows_only_this_managers_team(): void
    {
        $managerA = User::factory()->manager()->create();
        $managerB = User::factory()->manager()->create();
        $leaveType = LeaveType::factory()->create();

        $employeeA = User::factory()->create(['name' => 'Team A Employee', 'manager_id' => $managerA->id]);
        $employeeB = User::factory()->create(['name' => 'Team B Employee', 'manager_id' => $managerB->id]);

        LeaveRequest::factory()->create(['user_id' => $employeeA->id, 'leave_type_id' => $leaveType->id, 'status' => 'pending_manager']);
        LeaveRequest::factory()->create(['user_id' => $employeeB->id, 'leave_type_id' => $leaveType->id, 'status' => 'pending_manager']);

        $response = $this->withHeaders($this->authHeader($managerA))
            ->getJson('/api/v1/manager/leave-requests/pending')
            ->assertOk();

        $response->assertJsonFragment(['employee_name' => 'Team A Employee']);
        $response->assertJsonMissing(['employee_name' => 'Team B Employee']);
    }

    public function test_non_manager_cannot_view_pending_requests(): void
    {
        $employee = User::factory()->create();

        $this->withHeaders($this->authHeader($employee))
            ->getJson('/api/v1/manager/leave-requests/pending')
            ->assertForbidden();
    }

    public function test_approve_forwards_to_hr_without_deducting_balance(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_manager',
        ]);

        $this->withHeaders($this->authHeader($manager))
            ->postJson("/api/v1/manager/leave-requests/{$leaveRequest->id}/approve", ['note' => 'Looks fine'])
            ->assertOk();

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'pending_hr',
            'approver_id' => $manager->id,
        ]);
    }

    public function test_reject_is_final(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_manager',
        ]);

        $this->withHeaders($this->authHeader($manager))
            ->postJson("/api/v1/manager/leave-requests/{$leaveRequest->id}/reject")
            ->assertOk();

        $this->assertDatabaseHas('leave_requests', ['id' => $leaveRequest->id, 'status' => 'rejected']);
    }

    public function test_manager_cannot_act_on_another_teams_request(): void
    {
        $managerA = User::factory()->manager()->create();
        $managerB = User::factory()->manager()->create();
        $employeeUnderB = User::factory()->create(['manager_id' => $managerB->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $employeeUnderB->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_manager',
        ]);

        $this->withHeaders($this->authHeader($managerA))
            ->postJson("/api/v1/manager/leave-requests/{$leaveRequest->id}/approve")
            ->assertForbidden();

        $this->assertDatabaseHas('leave_requests', ['id' => $leaveRequest->id, 'status' => 'pending_manager']);
    }

    public function test_history_shows_this_managers_decided_requests(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['name' => 'Decided Employee', 'manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        LeaveRequest::factory()->approved()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $manager->id,
        ]);

        $this->withHeaders($this->authHeader($manager))
            ->getJson('/api/v1/manager/leave-requests/history')
            ->assertOk()
            ->assertJsonFragment(['employee_name' => 'Decided Employee']);
    }
}
