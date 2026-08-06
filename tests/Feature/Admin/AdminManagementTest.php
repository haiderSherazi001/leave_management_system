<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Departments;
use App\Livewire\Admin\Employees;
use App\Livewire\Admin\LeaveTypes;
use App\Models\Department;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\WelcomeNewEmployeeNotification;
use App\Services\EmployeeDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Livewire::test() mounts components directly and skips full-page layout
     * resolution, so it can't catch a missing/mismatched Livewire page layout.
     * Hitting the real routes closes that gap (bit us once already on the
     * leave pages).
     */
    public function test_admin_pages_render_successfully_over_http(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get(route('admin.employees'))->assertOk();
        $this->actingAs($hr)->get(route('admin.departments'))->assertOk();
        $this->actingAs($hr)->get(route('admin.leave-types'))->assertOk();
    }

    public function test_non_hr_users_cannot_access_any_admin_screen(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();

        foreach ([$employee, $manager] as $user) {
            $this->actingAs($user)->get(route('admin.employees'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.departments'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.leave-types'))->assertForbidden();
        }
    }

    public function test_hr_can_create_an_employee_and_leave_balances_are_provisioned(): void
    {
        $hr = User::factory()->hr()->create();
        $department = Department::factory()->create();
        $manager = User::factory()->manager()->create();
        $leaveType = LeaveType::factory()->create(['yearly_allocation_days' => 15]);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'New Hire')
            ->set('email', 'new.hire@leavedesk.test')
            ->set('password', 'password123')
            ->set('role', 'employee')
            ->set('departmentId', $department->id)
            ->set('managerId', $manager->id)
            ->set('joinedAt', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'new.hire@leavedesk.test',
            'role' => 'employee',
            'department_id' => $department->id,
            'manager_id' => $manager->id,
        ]);

        $newEmployee = User::where('email', 'new.hire@leavedesk.test')->firstOrFail();

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $newEmployee->id,
            'leave_type_id' => $leaveType->id,
            'allocated_days' => 15,
            'year' => now()->year,
        ]);
    }

    public function test_creating_an_employee_sends_a_welcome_invite_instead_of_setting_a_password(): void
    {
        Notification::fake();

        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'New Hire')
            ->set('email', 'invited@leavedesk.test')
            ->set('role', 'employee')
            ->set('joinedAt', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $newEmployee = User::where('email', 'invited@leavedesk.test')->firstOrFail();

        Notification::assertSentTo($newEmployee, WelcomeNewEmployeeNotification::class);
    }

    public function test_the_welcome_invite_link_lets_the_new_employee_set_a_password_and_log_in(): void
    {
        Notification::fake();

        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'New Hire')
            ->set('email', 'invited@leavedesk.test')
            ->set('role', 'employee')
            ->set('joinedAt', now()->toDateString())
            ->call('save');

        $newEmployee = User::where('email', 'invited@leavedesk.test')->firstOrFail();

        $setPasswordUrl = null;
        Notification::assertSentTo(
            $newEmployee,
            WelcomeNewEmployeeNotification::class,
            function (WelcomeNewEmployeeNotification $notification) use ($newEmployee, &$setPasswordUrl) {
                $setPasswordUrl = $notification->toMail($newEmployee)->actionUrl;

                return true;
            }
        );
        $this->assertNotNull($setPasswordUrl);

        // Actually follow the link and complete the flow, rather than just
        // asserting a notification was queued - matching this project's
        // habit of verifying against the real thing.
        $this->post('/logout');

        $this->get($setPasswordUrl)->assertOk();

        $query = [];
        parse_str(parse_url($setPasswordUrl, PHP_URL_QUERY), $query);
        $token = basename(parse_url($setPasswordUrl, PHP_URL_PATH));

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $query['email'],
            'password' => 'a-new-password',
            'password_confirmation' => 'a-new-password',
        ])->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $newEmployee->email,
            'password' => 'a-new-password',
        ]);

        $this->assertAuthenticatedAs($newEmployee->fresh());
    }

    public function test_hr_cannot_create_an_employee_with_a_duplicate_email(): void
    {
        $hr = User::factory()->hr()->create();
        $existing = User::factory()->create(['email' => 'taken@leavedesk.test']);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'Someone')
            ->set('email', 'taken@leavedesk.test')
            ->set('password', 'password123')
            ->set('role', 'employee')
            ->set('joinedAt', now()->toDateString())
            ->call('save')
            ->assertHasErrors('email');
    }

    public function test_hr_can_edit_an_employee_without_changing_the_password(): void
    {
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create(['name' => 'Old Name']);
        $originalPassword = $employee->password;

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('edit', $employee->id)
            ->set('name', 'Updated Name')
            ->call('save')
            ->assertHasNoErrors();

        $employee->refresh();

        $this->assertSame('Updated Name', $employee->name);
        $this->assertSame($originalPassword, $employee->password);
    }

    public function test_a_managers_role_cannot_be_changed_while_they_head_a_department(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        Department::factory()->create(['manager_id' => $manager->id]);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('edit', $manager->id)
            ->set('role', 'employee')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertDatabaseHas('users', ['id' => $manager->id, 'role' => 'manager']);
    }

    public function test_a_managers_role_cannot_be_changed_while_they_have_direct_reports(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        User::factory()->create(['manager_id' => $manager->id]);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('edit', $manager->id)
            ->set('role', 'employee')
            ->call('save')
            ->assertHasErrors('role');

        $this->assertDatabaseHas('users', ['id' => $manager->id, 'role' => 'manager']);
    }

    public function test_a_managers_role_can_be_changed_after_reassigning_their_department_and_reports(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        $replacement = User::factory()->manager()->create();
        $department = Department::factory()->create(['manager_id' => $manager->id]);
        $report = User::factory()->create(['manager_id' => $manager->id]);

        DB::table('departments')->where('id', $department->id)->update(['manager_id' => $replacement->id]);
        DB::table('users')->where('id', $report->id)->update(['manager_id' => $replacement->id]);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('edit', $manager->id)
            ->set('role', 'employee')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $manager->id, 'role' => 'employee']);
    }

    public function test_a_manager_can_switch_to_hr_while_still_heading_a_department(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        Department::factory()->create(['manager_id' => $manager->id]);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('edit', $manager->id)
            ->set('role', 'hr')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $manager->id, 'role' => 'hr']);
    }

    public function test_employee_manager_field_only_accepts_manager_or_hr_users(): void
    {
        $hr = User::factory()->hr()->create();
        $notAManager = User::factory()->create();

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'Someone')
            ->set('email', 'someone@leavedesk.test')
            ->set('password', 'password123')
            ->set('role', 'employee')
            ->set('managerId', $notAManager->id)
            ->set('joinedAt', now()->toDateString())
            ->call('save')
            ->assertHasErrors('managerId');
    }

    public function test_a_manager_cannot_be_assigned_as_another_managers_manager(): void
    {
        $hr = User::factory()->hr()->create();
        $otherManager = User::factory()->manager()->create();

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'New Manager')
            ->set('email', 'new.manager@leavedesk.test')
            ->set('password', 'password123')
            ->set('role', 'manager')
            ->set('managerId', $otherManager->id)
            ->set('joinedAt', now()->toDateString())
            ->call('save')
            ->assertHasErrors('managerId');

        $this->assertDatabaseMissing('users', ['email' => 'new.manager@leavedesk.test']);
    }

    public function test_a_manager_can_be_assigned_hr_as_their_manager(): void
    {
        $hr = User::factory()->hr()->create();
        $secondHr = User::factory()->hr()->create();

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'New Manager')
            ->set('email', 'new.manager@leavedesk.test')
            ->set('password', 'password123')
            ->set('role', 'manager')
            ->set('managerId', $secondHr->id)
            ->set('joinedAt', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'new.manager@leavedesk.test', 'manager_id' => $secondHr->id]);
    }

    public function test_an_employee_can_still_be_assigned_a_manager_as_their_manager(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'New Employee')
            ->set('email', 'new.employee@leavedesk.test')
            ->set('password', 'password123')
            ->set('role', 'employee')
            ->set('managerId', $manager->id)
            ->set('joinedAt', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'new.employee@leavedesk.test', 'manager_id' => $manager->id]);
    }

    public function test_creating_an_hr_user_with_a_manager_selected_is_forced_to_null(): void
    {
        $hr = User::factory()->hr()->create();
        $someManager = User::factory()->manager()->create();

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'New HR')
            ->set('email', 'new.hr@leavedesk.test')
            ->set('password', 'password123')
            ->set('role', 'employee')
            ->set('managerId', $someManager->id)
            ->set('joinedAt', now()->toDateString())
            // Switching role to hr after a manager was already picked (e.g.
            // the admin changed their mind mid-form) must clear it, not
            // just block the save.
            ->set('role', 'hr')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'new.hr@leavedesk.test', 'manager_id' => null]);
    }

    public function test_an_existing_hr_records_stale_manager_id_is_cleared_on_edit(): void
    {
        $hr = User::factory()->hr()->create();
        $someManager = User::factory()->manager()->create();
        $staleHr = User::factory()->hr()->create();
        DB::table('users')->where('id', $staleHr->id)->update(['manager_id' => $someManager->id]);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('edit', $staleHr->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $staleHr->id, 'manager_id' => null]);
    }

    public function test_manager_options_are_empty_for_an_hr_subject(): void
    {
        User::factory()->manager()->create(['name' => 'Some Manager']);
        User::factory()->hr()->create(['name' => 'Some HR']);

        $service = $this->app->make(EmployeeDirectoryService::class);
        $names = collect($service->managerOptions(null, 'hr'))->pluck('name')->all();

        $this->assertSame([], $names);
    }

    public function test_manager_options_exclude_other_managers_for_a_manager_subject(): void
    {
        User::factory()->manager()->create(['name' => 'Other Manager']);
        User::factory()->hr()->create(['name' => 'Some HR']);

        $service = $this->app->make(EmployeeDirectoryService::class);
        $names = collect($service->managerOptions(null, 'manager'))->pluck('name')->all();

        $this->assertNotContains('Other Manager', $names);
        $this->assertContains('Some HR', $names);
    }

    public function test_manager_options_include_other_managers_for_an_employee_subject(): void
    {
        User::factory()->manager()->create(['name' => 'Other Manager']);

        $service = $this->app->make(EmployeeDirectoryService::class);
        $names = collect($service->managerOptions(null, 'employee'))->pluck('name')->all();

        $this->assertContains('Other Manager', $names);
    }

    public function test_a_second_manager_cannot_be_placed_into_a_department_that_already_has_one(): void
    {
        $hr = User::factory()->hr()->create();
        $headOfEngineering = User::factory()->manager()->create();
        $department = Department::factory()->create(['name' => 'Engineering', 'manager_id' => $headOfEngineering->id]);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'Second Manager')
            ->set('email', 'second.manager@leavedesk.test')
            ->set('password', 'password123')
            ->set('role', 'manager')
            ->set('departmentId', $department->id)
            ->set('joinedAt', now()->toDateString())
            ->call('save')
            ->assertHasErrors('departmentId');

        $this->assertDatabaseMissing('users', ['email' => 'second.manager@leavedesk.test']);
    }

    public function test_a_manager_can_be_placed_into_a_department_with_no_manager_yet(): void
    {
        $hr = User::factory()->hr()->create();
        $department = Department::factory()->create(['name' => 'Sales', 'manager_id' => null]);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'Sales Manager')
            ->set('email', 'sales.manager@leavedesk.test')
            ->set('password', 'password123')
            ->set('role', 'manager')
            ->set('departmentId', $department->id)
            ->set('joinedAt', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'sales.manager@leavedesk.test', 'department_id' => $department->id]);
    }

    public function test_a_manager_can_keep_their_own_department_assignment_when_editing(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        $department = Department::factory()->create(['name' => 'Engineering', 'manager_id' => $manager->id]);
        DB::table('users')->where('id', $manager->id)->update(['department_id' => $department->id]);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('edit', $manager->id)
            ->set('name', 'Renamed Manager')
            ->set('departmentId', $department->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $manager->id, 'name' => 'Renamed Manager', 'department_id' => $department->id]);
    }

    /**
     * Non-manager roles (employee/hr) are unaffected — this rule only
     * guards against a second manager landing in an already-headed department.
     */
    public function test_a_regular_employee_can_still_be_placed_into_an_already_managed_department(): void
    {
        $hr = User::factory()->hr()->create();
        $headOfEngineering = User::factory()->manager()->create();
        $department = Department::factory()->create(['name' => 'Engineering', 'manager_id' => $headOfEngineering->id]);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'Regular Employee')
            ->set('email', 'regular.employee@leavedesk.test')
            ->set('password', 'password123')
            ->set('role', 'employee')
            ->set('departmentId', $department->id)
            ->set('joinedAt', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'regular.employee@leavedesk.test', 'department_id' => $department->id]);
    }

    public function test_hr_can_create_a_department(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();

        $this->actingAs($hr);

        Livewire::test(Departments::class)
            ->call('startCreate')
            ->set('name', 'Engineering')
            ->set('managerId', $manager->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('departments', [
            'name' => 'Engineering',
            'manager_id' => $manager->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $manager->id,
            'department_id' => Department::where('name', 'Engineering')->value('id'),
        ]);
    }

    public function test_assigning_a_manager_to_an_existing_department_syncs_their_department_id(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create(['department_id' => null]);
        $department = Department::factory()->create(['manager_id' => null]);

        $this->actingAs($hr);

        Livewire::test(Departments::class)
            ->call('edit', $department->id)
            ->set('managerId', $manager->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $manager->id, 'department_id' => $department->id]);
    }

    public function test_reassigning_a_departments_manager_clears_the_previous_managers_department_id(): void
    {
        $hr = User::factory()->hr()->create();
        $oldManager = User::factory()->manager()->create();
        $newManager = User::factory()->manager()->create(['department_id' => null]);
        $department = Department::factory()->create(['manager_id' => $oldManager->id]);

        $this->actingAs($hr);

        Livewire::test(Departments::class)
            ->call('edit', $department->id)
            ->set('managerId', $newManager->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $newManager->id, 'department_id' => $department->id]);
        $this->assertDatabaseHas('users', ['id' => $oldManager->id, 'department_id' => null]);
    }

    public function test_removing_a_departments_manager_clears_their_department_id(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        $department = Department::factory()->create(['manager_id' => $manager->id]);

        $this->actingAs($hr);

        Livewire::test(Departments::class)
            ->call('edit', $department->id)
            ->set('managerId', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['id' => $manager->id, 'department_id' => null]);
    }

    public function test_a_manager_cannot_be_assigned_to_head_two_departments(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        Department::factory()->create(['name' => 'Engineering', 'manager_id' => $manager->id]);

        $this->actingAs($hr);

        Livewire::test(Departments::class)
            ->call('startCreate')
            ->set('name', 'Sales')
            ->set('managerId', $manager->id)
            ->call('save')
            ->assertHasErrors('managerId');

        $this->assertDatabaseMissing('departments', ['name' => 'Sales']);
    }

    public function test_editing_a_department_can_keep_its_own_manager(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        $department = Department::factory()->create(['name' => 'Engineering', 'manager_id' => $manager->id]);

        $this->actingAs($hr);

        Livewire::test(Departments::class)
            ->call('edit', $department->id)
            ->set('name', 'Engineering Renamed')
            ->set('managerId', $manager->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('departments', [
            'id' => $department->id,
            'name' => 'Engineering Renamed',
            'manager_id' => $manager->id,
        ]);
    }

    public function test_multiple_departments_can_have_no_manager_assigned(): void
    {
        $hr = User::factory()->hr()->create();
        Department::factory()->create(['name' => 'Engineering', 'manager_id' => null]);

        $this->actingAs($hr);

        Livewire::test(Departments::class)
            ->call('startCreate')
            ->set('name', 'Sales')
            ->set('managerId', null)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('departments', ['name' => 'Sales', 'manager_id' => null]);
    }

    public function test_hr_can_create_a_leave_type(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr);

        Livewire::test(LeaveTypes::class)
            ->call('startCreate')
            ->set('name', 'Bereavement')
            ->set('code', 'BRV')
            ->set('yearlyAllocationDays', 5)
            ->set('carryForwardEnabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leave_types', [
            'name' => 'Bereavement',
            'code' => 'BRV',
            'yearly_allocation_days' => 5,
            'carry_forward_enabled' => false,
        ]);
    }

    public function test_creating_a_leave_type_provisions_balances_for_existing_active_employees(): void
    {
        $hr = User::factory()->hr()->create();
        $activeEmployee = User::factory()->create();
        $inactiveEmployee = User::factory()->inactive()->create();

        $this->actingAs($hr);

        Livewire::test(LeaveTypes::class)
            ->call('startCreate')
            ->set('name', 'Bereavement')
            ->set('code', 'BRV')
            ->set('yearlyAllocationDays', 5)
            ->set('carryForwardEnabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $leaveType = LeaveType::where('code', 'BRV')->firstOrFail();

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $activeEmployee->id,
            'leave_type_id' => $leaveType->id,
            'allocated_days' => 5,
            'year' => now()->year,
        ]);
        $this->assertDatabaseMissing('leave_balances', [
            'user_id' => $inactiveEmployee->id,
            'leave_type_id' => $leaveType->id,
        ]);
    }

    public function test_reactivating_a_leave_type_provisions_balances_for_employees_who_were_missing_them(): void
    {
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create();
        $leaveType = LeaveType::factory()->inactive()->create();

        // Simulates the exact bug reported: the leave type existed with no
        // balance row for this employee (e.g. it was created before this fix).
        $this->assertDatabaseMissing('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
        ]);

        $this->actingAs($hr);

        Livewire::test(LeaveTypes::class)->call('toggleActive', $leaveType->id);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
        ]);
    }

    public function test_sync_leave_balances_command_backfills_missing_balances(): void
    {
        $employee = User::factory()->create();
        $leaveType = LeaveType::factory()->create(['yearly_allocation_days' => 12]);

        $this->assertDatabaseMissing('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
        ]);

        $this->artisan('leave:sync-balances')->assertSuccessful();

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'allocated_days' => 12,
            'year' => now()->year,
        ]);
    }

    public function test_hr_can_deactivate_and_reactivate_an_employee(): void
    {
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create();

        $this->actingAs($hr);

        Livewire::test(Employees::class)->call('toggleActive', $employee->id);
        $this->assertFalse($employee->fresh()->is_active);

        Livewire::test(Employees::class)->call('toggleActive', $employee->id);
        $this->assertTrue($employee->fresh()->is_active);
    }

    public function test_employees_list_flags_a_deactivated_department_and_manager(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create(['name' => 'Deactivated Manager', 'is_active' => false]);
        $department = Department::factory()->create(['name' => 'Deactivated Department', 'is_active' => false]);
        User::factory()->create([
            'name' => 'Some Employee',
            'manager_id' => $manager->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->assertSeeInOrder(['Deactivated Manager', 'Deactivated'])
            ->assertSeeInOrder(['Deactivated Department', 'Deactivated']);
    }

    public function test_employees_list_does_not_flag_an_active_department_and_manager(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create(['name' => 'Active Manager']);
        $department = Department::factory()->create(['name' => 'Active Department']);
        User::factory()->create([
            'name' => 'Some Employee',
            'manager_id' => $manager->id,
            'department_id' => $department->id,
        ]);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->assertSee('Active Manager')
            ->assertSee('Active Department')
            ->assertDontSee('Deactivated');
    }

    public function test_hr_cannot_deactivate_the_last_active_hr_account(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('toggleActive', $hr->id)
            ->assertSet('errorMessage', 'Cannot deactivate the last active HR account.');

        $this->assertTrue($hr->fresh()->is_active);
    }

    public function test_hr_can_deactivate_themselves_if_another_active_hr_remains(): void
    {
        $hr = User::factory()->hr()->create();
        $otherHr = User::factory()->hr()->create();

        $this->actingAs($hr);

        Livewire::test(Employees::class)->call('toggleActive', $hr->id);

        $this->assertFalse($hr->fresh()->is_active);
        $this->assertTrue($otherHr->fresh()->is_active);
    }

    public function test_new_employees_only_get_balances_for_active_leave_types(): void
    {
        $hr = User::factory()->hr()->create();
        $activeType = LeaveType::factory()->create();
        $inactiveType = LeaveType::factory()->inactive()->create();

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->call('startCreate')
            ->set('name', 'New Hire')
            ->set('email', 'new.hire2@leavedesk.test')
            ->set('password', 'password123')
            ->set('role', 'employee')
            ->set('joinedAt', now()->toDateString())
            ->call('save')
            ->assertHasNoErrors();

        $newEmployee = User::where('email', 'new.hire2@leavedesk.test')->firstOrFail();

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $newEmployee->id,
            'leave_type_id' => $activeType->id,
        ]);
        $this->assertDatabaseMissing('leave_balances', [
            'user_id' => $newEmployee->id,
            'leave_type_id' => $inactiveType->id,
        ]);
    }

    public function test_hr_can_deactivate_and_reactivate_a_department(): void
    {
        $hr = User::factory()->hr()->create();
        $department = Department::factory()->create();

        $this->actingAs($hr);

        Livewire::test(Departments::class)->call('toggleActive', $department->id);
        $this->assertFalse($department->fresh()->is_active);

        Livewire::test(Departments::class)->call('toggleActive', $department->id);
        $this->assertTrue($department->fresh()->is_active);
    }

    public function test_departments_list_flags_a_deactivated_manager(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create(['name' => 'Deactivated Manager', 'is_active' => false]);
        Department::factory()->create(['name' => 'Engineering', 'manager_id' => $manager->id]);

        $this->actingAs($hr);

        Livewire::test(Departments::class)->assertSeeInOrder(['Deactivated Manager', 'Deactivated']);
    }

    public function test_departments_list_does_not_flag_an_active_manager(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create(['name' => 'Active Manager']);
        Department::factory()->create(['name' => 'Engineering', 'manager_id' => $manager->id]);

        $this->actingAs($hr);

        Livewire::test(Departments::class)
            ->assertSee('Active Manager')
            ->assertDontSee('Deactivated');
    }

    public function test_hr_can_deactivate_and_reactivate_a_leave_type(): void
    {
        $hr = User::factory()->hr()->create();
        $leaveType = LeaveType::factory()->create();

        $this->actingAs($hr);

        Livewire::test(LeaveTypes::class)->call('toggleActive', $leaveType->id);
        $this->assertFalse($leaveType->fresh()->is_active);

        Livewire::test(LeaveTypes::class)->call('toggleActive', $leaveType->id);
        $this->assertTrue($leaveType->fresh()->is_active);
    }

    public function test_manager_dropdown_excludes_inactive_managers_but_keeps_the_currently_assigned_one(): void
    {
        $hr = User::factory()->hr()->create();
        $activeManager = User::factory()->manager()->create(['name' => 'Active Manager']);
        $inactiveManager = User::factory()->manager()->inactive()->create(['name' => 'Retired Manager']);
        $employee = User::factory()->create(['manager_id' => $inactiveManager->id]);

        $this->actingAs($hr);

        // Editing an employee whose current manager is now inactive should still show that manager.
        Livewire::test(Employees::class)
            ->call('edit', $employee->id)
            ->assertSee('Active Manager')
            ->assertSee('Retired Manager (inactive)');
    }

    /**
     * The frontend listens for this event to scroll the (long-page-relative)
     * form into view and focus its first field — without it, editing a row
     * further down a long list leaves the form open off-screen above.
     */
    public function test_opening_the_employee_form_dispatches_a_focus_event(): void
    {
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create();

        $this->actingAs($hr);

        Livewire::test(Employees::class)->call('startCreate')->assertDispatched('form-opened');
        Livewire::test(Employees::class)->call('edit', $employee->id)->assertDispatched('form-opened');
    }

    public function test_opening_the_department_form_dispatches_a_focus_event(): void
    {
        $hr = User::factory()->hr()->create();
        $department = Department::factory()->create();

        $this->actingAs($hr);

        Livewire::test(Departments::class)->call('startCreate')->assertDispatched('form-opened');
        Livewire::test(Departments::class)->call('edit', $department->id)->assertDispatched('form-opened');
    }

    public function test_opening_the_leave_type_form_dispatches_a_focus_event(): void
    {
        $hr = User::factory()->hr()->create();
        $leaveType = LeaveType::factory()->create();

        $this->actingAs($hr);

        Livewire::test(LeaveTypes::class)->call('startCreate')->assertDispatched('form-opened');
        Livewire::test(LeaveTypes::class)->call('edit', $leaveType->id)->assertDispatched('form-opened');
    }

    public function test_employees_can_be_searched_by_name_or_email(): void
    {
        $hr = User::factory()->hr()->create();
        User::factory()->create(['name' => 'Jamie Rivera', 'email' => 'jamie@example.com']);
        User::factory()->create(['name' => 'Someone Else', 'email' => 'findme@example.com']);
        User::factory()->create(['name' => 'Not Matching', 'email' => 'nope@example.com']);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->set('search', 'jamie')
            ->assertSee('Jamie Rivera')
            ->assertDontSee('Not Matching');

        Livewire::test(Employees::class)
            ->set('search', 'findme@example.com')
            ->assertSee('Someone Else')
            ->assertDontSee('Not Matching');
    }

    public function test_employees_can_be_filtered_by_role_department_and_status(): void
    {
        $hr = User::factory()->hr()->create();
        $department = Department::factory()->create(['name' => 'Engineering']);
        $manager = User::factory()->manager()->create(['name' => 'Team Lead']);
        User::factory()->create(['name' => 'Dept Employee', 'department_id' => $department->id]);
        User::factory()->inactive()->create(['name' => 'Inactive Employee']);

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->set('roleFilter', 'manager')
            ->assertSee('Team Lead')
            ->assertDontSee('Dept Employee');

        Livewire::test(Employees::class)
            ->set('departmentFilter', $department->id)
            ->assertSee('Dept Employee')
            ->assertDontSee('Team Lead');

        Livewire::test(Employees::class)
            ->set('statusFilter', 'inactive')
            ->assertSee('Inactive Employee')
            ->assertDontSee('Team Lead');
    }

    public function test_clearing_employee_filters_resets_search_and_all_dropdowns(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr);

        Livewire::test(Employees::class)
            ->set('search', 'something')
            ->set('roleFilter', 'manager')
            ->set('departmentFilter', 1)
            ->set('statusFilter', 'inactive')
            ->call('clearFilters')
            ->assertSet('search', '')
            ->assertSet('roleFilter', '')
            ->assertSet('departmentFilter', null)
            ->assertSet('statusFilter', '');
    }

    public function test_departments_can_be_searched_and_filtered_by_status(): void
    {
        $hr = User::factory()->hr()->create();
        Department::factory()->create(['name' => 'Marketing']);
        Department::factory()->create(['name' => 'Sales', 'is_active' => false]);

        $this->actingAs($hr);

        Livewire::test(Departments::class)
            ->set('search', 'market')
            ->assertSee('Marketing')
            ->assertDontSee('Sales');

        Livewire::test(Departments::class)
            ->set('statusFilter', 'inactive')
            ->assertSee('Sales')
            ->assertDontSee('Marketing');
    }

    public function test_leave_types_can_be_searched_by_name_or_code_and_filtered_by_status(): void
    {
        $hr = User::factory()->hr()->create();
        LeaveType::factory()->create(['name' => 'Sick Leave', 'code' => 'SICK']);
        LeaveType::factory()->create(['name' => 'Annual Leave', 'code' => 'ANNUAL', 'is_active' => false]);

        $this->actingAs($hr);

        Livewire::test(LeaveTypes::class)
            ->set('search', 'SICK')
            ->assertSee('Sick Leave')
            ->assertDontSee('Annual Leave');

        Livewire::test(LeaveTypes::class)
            ->set('statusFilter', 'inactive')
            ->assertSee('Annual Leave')
            ->assertDontSee('Sick Leave');
    }
}
