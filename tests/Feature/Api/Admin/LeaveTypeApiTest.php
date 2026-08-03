<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirrors the leave-type-related coverage already established in
 * tests/Feature/Admin/AdminManagementTest.php for the Livewire admin screen.
 */
class LeaveTypeApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile-app-token')->plainTextToken];
    }

    public function test_non_hr_cannot_access_any_leave_type_admin_endpoint(): void
    {
        $employee = User::factory()->create();

        $this->withHeaders($this->authHeader($employee))->getJson('/api/v1/admin/leave-types')->assertForbidden();
        $this->withHeaders($this->authHeader($employee))->postJson('/api/v1/admin/leave-types', [])->assertForbidden();
    }

    public function test_index_returns_all_leave_types_active_and_inactive(): void
    {
        $hr = User::factory()->hr()->create();
        LeaveType::factory()->create(['name' => 'Annual', 'is_active' => true]);
        LeaveType::factory()->create(['name' => 'Retired Type', 'is_active' => false]);

        $response = $this->withHeaders($this->authHeader($hr))
            ->getJson('/api/v1/admin/leave-types')
            ->assertOk();

        $names = array_column($response->json('data'), 'name');
        $this->assertContains('Annual', $names);
        $this->assertContains('Retired Type', $names);
    }

    public function test_creating_a_leave_type_provisions_balances_for_existing_active_employees(): void
    {
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create(['is_active' => true]);

        $response = $this->withHeaders($this->authHeader($hr))
            ->postJson('/api/v1/admin/leave-types', [
                'name' => 'Sabbatical',
                'code' => 'sabbatical',
                'yearly_allocation_days' => 10,
                'carry_forward_enabled' => false,
            ])
            ->assertOk();

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $response->json('data.id'),
            'allocated_days' => 10,
        ]);
    }

    public function test_duplicate_name_and_code_are_rejected(): void
    {
        $hr = User::factory()->hr()->create();
        LeaveType::factory()->create(['name' => 'Annual', 'code' => 'annual']);

        $this->withHeaders($this->authHeader($hr))
            ->postJson('/api/v1/admin/leave-types', [
                'name' => 'Annual',
                'code' => 'annual-2',
                'yearly_allocation_days' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->withHeaders($this->authHeader($hr))
            ->postJson('/api/v1/admin/leave-types', [
                'name' => 'Annual 2',
                'code' => 'annual',
                'yearly_allocation_days' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_carry_forward_max_days_is_dropped_when_carry_forward_is_disabled(): void
    {
        $hr = User::factory()->hr()->create();

        $response = $this->withHeaders($this->authHeader($hr))
            ->postJson('/api/v1/admin/leave-types', [
                'name' => 'Annual',
                'code' => 'annual',
                'yearly_allocation_days' => 15,
                'carry_forward_enabled' => false,
                'carry_forward_max_days' => 5,
            ])
            ->assertOk();

        $this->assertDatabaseHas('leave_types', [
            'id' => $response->json('data.id'),
            'carry_forward_enabled' => false,
            'carry_forward_max_days' => null,
        ]);
    }

    public function test_hr_can_edit_a_leave_type(): void
    {
        $hr = User::factory()->hr()->create();
        $leaveType = LeaveType::factory()->create(['name' => 'Old Name']);

        $this->withHeaders($this->authHeader($hr))
            ->putJson("/api/v1/admin/leave-types/{$leaveType->id}", [
                'name' => 'New Name',
                'code' => $leaveType->code,
                'yearly_allocation_days' => 12,
            ])
            ->assertOk();

        $this->assertDatabaseHas('leave_types', ['id' => $leaveType->id, 'name' => 'New Name']);
    }

    public function test_reactivating_a_leave_type_provisions_balances_for_employees_who_were_missing_them(): void
    {
        $hr = User::factory()->hr()->create();
        $leaveType = LeaveType::factory()->create(['is_active' => false]);
        $employee = User::factory()->create(['is_active' => true]);

        $this->withHeaders($this->authHeader($hr))
            ->postJson("/api/v1/admin/leave-types/{$leaveType->id}/toggle-active")
            ->assertOk();

        $this->assertDatabaseHas('leave_types', ['id' => $leaveType->id, 'is_active' => true]);
        $this->assertDatabaseHas('leave_balances', ['user_id' => $employee->id, 'leave_type_id' => $leaveType->id]);
    }
}
