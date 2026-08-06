<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Departments;
use App\Livewire\Admin\Employees;
use App\Livewire\Admin\Holidays;
use App\Livewire\Admin\LeaveTypes;
use App\Models\Company;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\AttendanceExportService;
use App\Services\DashboardService;
use App\Services\DepartmentService;
use App\Services\EmployeeDirectoryService;
use App\Services\HolidayService;
use App\Services\LeaveRequestService;
use App\Services\LeaveTypeService;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The main safety net for the multi-tenancy rollout: every one of these
 * asserts that Company A's HR/manager cannot see or act on Company B's data,
 * even by ID. Each of these paths had a raw DB::table() query or a
 * Rule::exists()/unique() check hand-fixed to add a company_id filter - this
 * is what actually verifies those edits, rather than trusting the diff.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Company $companyA;

    private Company $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        // TestCase's own setUp() already created and pinned one ambient
        // company - reused here as Company A rather than creating a third.
        $this->companyA = Company::find(Tenant::id());
        $this->companyB = Company::factory()->create();
    }

    private function inCompanyB(callable $callback): mixed
    {
        Tenant::set($this->companyB->id);
        $result = $callback();
        Tenant::set($this->companyA->id);

        return $result;
    }

    public function test_employee_list_never_includes_another_companys_employees(): void
    {
        $ourEmployee = User::factory()->create(['name' => 'Alice A']);
        $theirEmployee = $this->inCompanyB(fn () => User::factory()->create(['name' => 'Bob B']));

        $results = app(EmployeeDirectoryService::class)->list();

        $names = array_map(fn ($row) => $row->name, $results);

        $this->assertContains('Alice A', $names);
        $this->assertNotContains('Bob B', $names);
    }

    public function test_editing_an_employee_by_a_guessed_id_from_another_company_silently_fails(): void
    {
        $hr = User::factory()->hr()->create();
        $theirEmployee = $this->inCompanyB(fn () => User::factory()->create());

        Livewire::actingAs($hr)
            ->test(Employees::class)
            ->call('edit', $theirEmployee->id)
            ->assertSet('editingId', null)
            ->assertSet('name', '');
    }

    public function test_deactivating_an_employee_by_a_guessed_id_from_another_company_has_no_effect(): void
    {
        $hr = User::factory()->hr()->create();
        $theirEmployee = $this->inCompanyB(fn () => User::factory()->create(['is_active' => true]));

        Livewire::actingAs($hr)
            ->test(Employees::class)
            ->call('toggleActive', $theirEmployee->id);

        $this->assertTrue($theirEmployee->fresh()->is_active);
    }

    public function test_department_name_uniqueness_is_scoped_per_company(): void
    {
        $this->inCompanyB(fn () => Department::factory()->create(['name' => 'Engineering']));

        // Same name, different company - must NOT collide with company B's
        // department now that the unique constraint is (company_id, name).
        $id = app(DepartmentService::class)->create('Engineering', null);

        $this->assertDatabaseHas('departments', ['id' => $id, 'company_id' => $this->companyA->id, 'name' => 'Engineering']);
    }

    public function test_editing_a_department_by_a_guessed_id_from_another_company_silently_fails(): void
    {
        $hr = User::factory()->hr()->create();
        $theirDepartment = $this->inCompanyB(fn () => Department::factory()->create(['name' => 'Their Dept']));

        Livewire::actingAs($hr)
            ->test(Departments::class)
            ->call('edit', $theirDepartment->id)
            ->assertSet('editingId', null)
            ->assertSet('name', '');
    }

    public function test_leave_type_code_uniqueness_is_scoped_per_company(): void
    {
        $this->inCompanyB(fn () => LeaveType::factory()->create(['name' => 'Annual', 'code' => 'ANN']));

        $id = app(LeaveTypeService::class)->create(
            name: 'Annual',
            code: 'ANN',
            yearlyAllocationDays: 10,
            carryForwardEnabled: false,
            carryForwardMaxDays: null,
            description: null,
        );

        $this->assertDatabaseHas('leave_types', ['id' => $id, 'company_id' => $this->companyA->id, 'code' => 'ANN']);
    }

    public function test_editing_a_leave_type_by_a_guessed_id_from_another_company_silently_fails(): void
    {
        $hr = User::factory()->hr()->create();
        $theirLeaveType = $this->inCompanyB(fn () => LeaveType::factory()->create());

        Livewire::actingAs($hr)
            ->test(LeaveTypes::class)
            ->call('edit', $theirLeaveType->id)
            ->assertSet('editingId', null)
            ->assertSet('name', '');
    }

    public function test_holiday_date_uniqueness_is_scoped_per_company(): void
    {
        $this->inCompanyB(fn () => Holiday::factory()->create(['date' => '2026-12-25']));

        $id = app(HolidayService::class)->create('2026-12-25', 'Christmas');

        $this->assertDatabaseHas('holidays', ['id' => $id, 'company_id' => $this->companyA->id, 'date' => '2026-12-25']);
    }

    public function test_editing_a_holiday_by_a_guessed_id_from_another_company_silently_fails(): void
    {
        $hr = User::factory()->hr()->create();
        $theirHoliday = $this->inCompanyB(fn () => Holiday::factory()->create());

        Livewire::actingAs($hr)
            ->test(Holidays::class)
            ->call('edit', $theirHoliday->id)
            ->assertSet('editingId', null)
            ->assertSet('name', '');
    }

    public function test_hr_cannot_approve_a_leave_request_belonging_to_another_company(): void
    {
        $hr = User::factory()->hr()->create();

        $theirRequest = $this->inCompanyB(function () {
            $employee = User::factory()->create();

            return LeaveRequest::factory()->pendingHr()->create(['user_id' => $employee->id]);
        });

        $this->expectExceptionMessage('Leave request not found.');

        app(LeaveRequestService::class)->approveByHr($theirRequest->id, $hr);
    }

    public function test_hr_pending_queue_never_includes_another_companys_requests(): void
    {
        $ourEmployee = User::factory()->create();
        $ourRequest = LeaveRequest::factory()->pendingHr()->create(['user_id' => $ourEmployee->id]);

        $this->inCompanyB(function () {
            $employee = User::factory()->create();
            LeaveRequest::factory()->pendingHr()->create(['user_id' => $employee->id]);
        });

        $ids = array_map(fn ($row) => $row->id, app(LeaveRequestService::class)->pendingForHr());

        $this->assertContains($ourRequest->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_dashboard_counts_are_scoped_to_the_current_company(): void
    {
        $this->inCompanyB(function () {
            $employee = User::factory()->create();
            LeaveRequest::factory()->create(['user_id' => $employee->id]);
        });

        $stats = app(DashboardService::class)->attendanceOverview();

        $this->assertSame(0, $stats['pendingManager']);
    }

    public function test_payroll_export_never_includes_another_companys_employees(): void
    {
        $ourEmployee = User::factory()->create(['name' => 'Alice A', 'is_active' => true]);
        $this->inCompanyB(fn () => User::factory()->create(['name' => 'Bob B', 'is_active' => true]));

        $rows = app(AttendanceExportService::class)->summaryBetween(now()->toDateString(), now()->toDateString());

        $names = $rows->pluck('name')->all();

        $this->assertContains('Alice A', $names);
        $this->assertNotContains('Bob B', $names);
    }

    public function test_mobile_api_employee_list_never_includes_another_companys_employees(): void
    {
        $hr = User::factory()->hr()->create();
        $theirEmployee = $this->inCompanyB(fn () => User::factory()->create(['name' => 'Bob B']));

        $response = $this->actingAs($hr, 'sanctum')->getJson('/api/v1/admin/employees');

        $response->assertOk();
        $names = array_map(fn ($row) => $row['name'], $response->json('data.employees'));
        $this->assertNotContains('Bob B', $names);
    }

    public function test_mobile_api_cannot_toggle_active_on_another_companys_employee(): void
    {
        $hr = User::factory()->hr()->create();
        $theirEmployee = $this->inCompanyB(fn () => User::factory()->create());

        $response = $this->actingAs($hr, 'sanctum')->postJson("/api/v1/admin/employees/{$theirEmployee->id}/toggle-active");

        $response->assertNotFound();
    }
}
