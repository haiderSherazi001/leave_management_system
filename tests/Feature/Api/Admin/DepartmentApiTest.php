<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirrors the department-related coverage already established in
 * tests/Feature/Admin/AdminManagementTest.php for the Livewire admin screen.
 */
class DepartmentApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile-app-token')->plainTextToken];
    }

    public function test_non_hr_cannot_access_any_department_admin_endpoint(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();

        foreach ([$employee, $manager] as $user) {
            $this->withHeaders($this->authHeader($user))->getJson('/api/v1/admin/departments')->assertForbidden();
            $this->withHeaders($this->authHeader($user))->postJson('/api/v1/admin/departments', [])->assertForbidden();
        }
    }

    public function test_index_returns_departments_and_managers_without_leaking_sensitive_columns(): void
    {
        $hr = User::factory()->hr()->create();
        Department::factory()->create(['name' => 'Engineering']);
        User::factory()->manager()->create();

        $response = $this->withHeaders($this->authHeader($hr))
            ->getJson('/api/v1/admin/departments')
            ->assertOk();

        $response->assertJsonPath('data.departments.0.name', 'Engineering');
        $this->assertNotEmpty($response->json('data.managers'));
        $this->assertEqualsCanonicalizing(['id', 'name', 'role'], array_keys($response->json('data.managers')[0]));
    }

    public function test_hr_can_create_a_department_and_the_manager_sync_fires(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();

        $response = $this->withHeaders($this->authHeader($hr))
            ->postJson('/api/v1/admin/departments', ['name' => 'Sales', 'manager_id' => $manager->id])
            ->assertOk();

        $this->assertDatabaseHas('departments', ['name' => 'Sales', 'manager_id' => $manager->id]);
        // DepartmentObserver only fires on Eloquent writes - this locks in
        // that the API went through DepartmentService::create() (which uses
        // the Eloquent model), not a raw DB::table() insert that would
        // bypass it.
        $this->assertDatabaseHas('users', ['id' => $manager->id, 'department_id' => $response->json('data.id')]);
    }

    public function test_duplicate_department_name_is_rejected(): void
    {
        $hr = User::factory()->hr()->create();
        Department::factory()->create(['name' => 'Engineering']);

        $this->withHeaders($this->authHeader($hr))
            ->postJson('/api/v1/admin/departments', ['name' => 'Engineering'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_a_manager_cannot_be_assigned_to_head_two_departments(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        Department::factory()->create(['manager_id' => $manager->id]);

        $this->withHeaders($this->authHeader($hr))
            ->postJson('/api/v1/admin/departments', ['name' => 'Sales', 'manager_id' => $manager->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('manager_id');
    }

    public function test_editing_a_department_can_keep_its_own_manager(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        $department = Department::factory()->create(['name' => 'Engineering', 'manager_id' => $manager->id]);

        $this->withHeaders($this->authHeader($hr))
            ->putJson("/api/v1/admin/departments/{$department->id}", [
                'name' => 'Engineering',
                'manager_id' => $manager->id,
            ])
            ->assertOk();
    }

    public function test_multiple_departments_can_have_no_manager_assigned(): void
    {
        $hr = User::factory()->hr()->create();
        Department::factory()->create(['name' => 'Engineering', 'manager_id' => null]);

        $this->withHeaders($this->authHeader($hr))
            ->postJson('/api/v1/admin/departments', ['name' => 'Sales'])
            ->assertOk();

        $this->assertDatabaseHas('departments', ['name' => 'Sales', 'manager_id' => null]);
    }

    public function test_hr_can_deactivate_and_reactivate_a_department(): void
    {
        $hr = User::factory()->hr()->create();
        $department = Department::factory()->create(['is_active' => true]);

        $this->withHeaders($this->authHeader($hr))
            ->postJson("/api/v1/admin/departments/{$department->id}/toggle-active")
            ->assertOk();
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'is_active' => false]);

        $this->withHeaders($this->authHeader($hr))
            ->postJson("/api/v1/admin/departments/{$department->id}/toggle-active")
            ->assertOk();
        $this->assertDatabaseHas('departments', ['id' => $department->id, 'is_active' => true]);
    }
}
