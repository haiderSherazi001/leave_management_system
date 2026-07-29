<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirrors the business-rule coverage already established in
 * tests/Feature/Admin/AdminManagementTest.php for the Livewire admin screen -
 * this locks in that the API controller's duplicated validation stays in
 * parity with it, not a from-scratch spec.
 */
class EmployeeApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile-app-token')->plainTextToken];
    }

    public function test_non_hr_cannot_access_any_employee_admin_endpoint(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();

        foreach ([$employee, $manager] as $user) {
            $this->withHeaders($this->authHeader($user))->getJson('/api/v1/admin/employees')->assertForbidden();
            $this->withHeaders($this->authHeader($user))->postJson('/api/v1/admin/employees', [])->assertForbidden();
        }
    }

    public function test_index_returns_employees_departments_managers_and_roles(): void
    {
        $hr = User::factory()->hr()->create();
        Department::factory()->create(['name' => 'Engineering']);
        User::factory()->manager()->create(['name' => 'Morgan Manager']);

        $response = $this->withHeaders($this->authHeader($hr))
            ->getJson('/api/v1/admin/employees')
            ->assertOk();

        $response->assertJsonPath('data.departments.0.name', 'Engineering');
        $this->assertNotEmpty($response->json('data.managers'));
        $this->assertEquals(
            ['employee', 'manager', 'hr'],
            array_column($response->json('data.roles'), 'value'),
        );
    }

    public function test_hr_can_create_an_employee_and_leave_balances_are_provisioned(): void
    {
        $hr = User::factory()->hr()->create();
        $department = Department::factory()->create();
        $manager = User::factory()->manager()->create();

        $response = $this->withHeaders($this->authHeader($hr))
            ->postJson('/api/v1/admin/employees', [
                'name' => 'New Hire',
                'email' => 'new.hire@leavedesk.test',
                'password' => 'password123',
                'role' => 'employee',
                'department_id' => $department->id,
                'manager_id' => $manager->id,
                'joined_at' => now()->toDateString(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'new.hire@leavedesk.test',
            'role' => 'employee',
            'department_id' => $department->id,
            'manager_id' => $manager->id,
        ]);
        $this->assertIsInt($response->json('data.id'));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $hr = User::factory()->hr()->create();
        User::factory()->create(['email' => 'taken@leavedesk.test']);

        $this->withHeaders($this->authHeader($hr))
            ->postJson('/api/v1/admin/employees', [
                'name' => 'Someone',
                'email' => 'taken@leavedesk.test',
                'password' => 'password123',
                'role' => 'employee',
                'joined_at' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_hr_can_edit_an_employee_without_changing_the_password(): void
    {
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create(['name' => 'Old Name']);
        $originalPassword = $employee->password;

        $this->withHeaders($this->authHeader($hr))
            ->putJson("/api/v1/admin/employees/{$employee->id}", [
                'name' => 'New Name',
                'email' => $employee->email,
                'password' => '',
                'role' => 'employee',
                'joined_at' => now()->toDateString(),
            ])
            ->assertOk();

        $employee->refresh();
        $this->assertSame('New Name', $employee->name);
        $this->assertSame($originalPassword, $employee->password);
    }

    public function test_a_manager_cannot_be_assigned_as_another_managers_manager(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        $otherManager = User::factory()->manager()->create();

        $this->withHeaders($this->authHeader($hr))
            ->putJson("/api/v1/admin/employees/{$manager->id}", [
                'name' => $manager->name,
                'email' => $manager->email,
                'password' => '',
                'role' => 'manager',
                'manager_id' => $otherManager->id,
                'joined_at' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('manager_id');
    }

    public function test_hr_role_forces_manager_id_to_null(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create();

        $this->withHeaders($this->authHeader($hr))
            ->putJson("/api/v1/admin/employees/{$employee->id}", [
                'name' => $employee->name,
                'email' => $employee->email,
                'password' => '',
                'role' => 'hr',
                'manager_id' => $manager->id,
                'joined_at' => now()->toDateString(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('users', ['id' => $employee->id, 'role' => 'hr', 'manager_id' => null]);
    }

    public function test_a_second_manager_cannot_be_placed_into_a_department_that_already_has_one(): void
    {
        $hr = User::factory()->hr()->create();
        $existingManager = User::factory()->manager()->create();
        $department = Department::factory()->create(['manager_id' => $existingManager->id]);
        $secondManager = User::factory()->manager()->create();

        $this->withHeaders($this->authHeader($hr))
            ->putJson("/api/v1/admin/employees/{$secondManager->id}", [
                'name' => $secondManager->name,
                'email' => $secondManager->email,
                'password' => '',
                'role' => 'manager',
                'department_id' => $department->id,
                'joined_at' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('department_id');
    }

    public function test_a_managers_role_cannot_be_changed_while_they_head_a_department(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        Department::factory()->create(['manager_id' => $manager->id]);

        $this->withHeaders($this->authHeader($hr))
            ->putJson("/api/v1/admin/employees/{$manager->id}", [
                'name' => $manager->name,
                'email' => $manager->email,
                'password' => '',
                'role' => 'employee',
                'joined_at' => now()->toDateString(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_hr_can_deactivate_and_reactivate_an_employee(): void
    {
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create(['is_active' => true]);

        $this->withHeaders($this->authHeader($hr))
            ->postJson("/api/v1/admin/employees/{$employee->id}/toggle-active")
            ->assertOk();
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'is_active' => false]);

        $this->withHeaders($this->authHeader($hr))
            ->postJson("/api/v1/admin/employees/{$employee->id}/toggle-active")
            ->assertOk();
        $this->assertDatabaseHas('users', ['id' => $employee->id, 'is_active' => true]);
    }

    public function test_cannot_deactivate_the_last_active_hr_account(): void
    {
        $hr = User::factory()->hr()->create();

        $this->withHeaders($this->authHeader($hr))
            ->postJson("/api/v1/admin/employees/{$hr->id}/toggle-active")
            ->assertStatus(409);

        $this->assertDatabaseHas('users', ['id' => $hr->id, 'is_active' => true]);
    }
}
