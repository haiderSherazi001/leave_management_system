<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Leave\ApprovalQueue;
use App\Livewire\Leave\RequestForm;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveRequestService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LeaveRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Livewire::test() mounts components directly and skips full-page layout
     * resolution, so it can't catch a missing/mismatched Livewire page layout.
     * Hitting the real routes closes that gap.
     */
    public function test_leave_pages_render_successfully_over_http(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);

        $this->actingAs($employee)->get(route('leave.apply'))->assertOk();
        $this->actingAs($employee)->get(route('leave.my-requests'))->assertOk();
        $this->actingAs($manager)->get(route('leave.approvals'))->assertOk();
    }

    public function test_employee_can_submit_a_leave_request_within_balance(): void
    {
        $leaveType = LeaveType::factory()->create(['yearly_allocation_days' => 10]);
        $employee = User::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $this->actingAs($employee);

        Livewire::test(RequestForm::class)
            ->set('leaveTypeId', $leaveType->id)
            ->set('startDate', now()->addDays(5)->toDateString())
            ->set('endDate', now()->addDays(6)->toDateString())
            ->set('reason', 'Family trip')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending',
            'total_days' => 2,
        ]);
    }

    public function test_employee_cannot_submit_a_leave_request_exceeding_balance(): void
    {
        $leaveType = LeaveType::factory()->create(['yearly_allocation_days' => 2]);
        $employee = User::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 2,
            'used_days' => 0,
        ]);

        $this->actingAs($employee);

        Livewire::test(RequestForm::class)
            ->set('leaveTypeId', $leaveType->id)
            ->set('startDate', now()->addDays(5)->toDateString())
            ->set('endDate', now()->addDays(9)->toDateString())
            ->set('reason', 'Long trip')
            ->call('submit')
            ->assertHasErrors('form');

        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_manager_can_approve_a_pending_request_and_balance_is_deducted(): void
    {
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
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'total_days' => 3,
            'status' => 'pending',
        ]);

        $this->actingAs($manager);

        Livewire::test(ApprovalQueue::class)
            ->call('approve', $leaveRequest->id)
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'approver_id' => $manager->id,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'used_days' => 3,
        ]);
    }

    public function test_employee_cannot_access_the_approval_queue(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->get(route('leave.approvals'))
            ->assertForbidden();
    }

    /**
     * Multi-level approval (manager -> HR) is Phase 4 scope. In Phase 1, HR
     * has no role in the leave approval workflow at all.
     */
    public function test_hr_cannot_access_the_approval_queue(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)
            ->get(route('leave.approvals'))
            ->assertForbidden();
    }

    public function test_hr_cannot_approve_or_reject_a_leave_request_even_by_calling_the_service_directly(): void
    {
        $hr = User::factory()->hr()->create();
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending',
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $this->expectException(AuthorizationException::class);

        $service->approve($leaveRequest->id, $hr);
    }

    public function test_manager_only_sees_pending_requests_from_their_own_team(): void
    {
        $managerA = User::factory()->manager()->create();
        $managerB = User::factory()->manager()->create();

        $employeeUnderA = User::factory()->create(['manager_id' => $managerA->id]);
        $employeeUnderB = User::factory()->create(['manager_id' => $managerB->id]);

        $leaveType = LeaveType::factory()->create();

        LeaveRequest::factory()->create([
            'user_id' => $employeeUnderA->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending',
        ]);

        LeaveRequest::factory()->create([
            'user_id' => $employeeUnderB->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending',
        ]);

        $this->actingAs($managerA);

        Livewire::test(ApprovalQueue::class)
            ->assertSee($employeeUnderA->name)
            ->assertDontSee($employeeUnderB->name);
    }

    public function test_employee_cannot_submit_a_leave_request_that_overlaps_an_approved_one(): void
    {
        $leaveType = LeaveType::factory()->create(['yearly_allocation_days' => 20]);
        $employee = User::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 20,
            'used_days' => 0,
        ]);

        LeaveRequest::factory()->approved()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
            'total_days' => 3,
        ]);

        $this->actingAs($employee);

        // Overlaps the middle of the already-approved range.
        Livewire::test(RequestForm::class)
            ->set('leaveTypeId', $leaveType->id)
            ->set('startDate', now()->addDays(11)->toDateString())
            ->set('endDate', now()->addDays(13)->toDateString())
            ->set('reason', 'Conflicting request')
            ->call('submit')
            ->assertHasErrors('form');

        $this->assertDatabaseCount('leave_requests', 1);
    }

    public function test_half_day_request_must_have_matching_start_and_end_date(): void
    {
        $leaveType = LeaveType::factory()->create(['yearly_allocation_days' => 10]);
        $employee = User::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $this->actingAs($employee);

        Livewire::test(RequestForm::class)
            ->set('leaveTypeId', $leaveType->id)
            ->set('startDate', now()->addDays(5)->toDateString())
            ->set('isHalfDay', true)
            // Overrides the date the isHalfDay hook just auto-synced, to exercise the service-level guard directly.
            ->set('endDate', now()->addDays(6)->toDateString())
            ->set('reason', 'Doctor appointment')
            ->call('submit')
            ->assertHasErrors('form');

        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_manager_cannot_approve_a_request_outside_their_own_team(): void
    {
        $managerA = User::factory()->manager()->create();
        $managerB = User::factory()->manager()->create();
        $employeeUnderB = User::factory()->create(['manager_id' => $managerB->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $employeeUnderB->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending',
        ]);

        $this->actingAs($managerA);

        Livewire::test(ApprovalQueue::class)
            ->call('approve', $leaveRequest->id)
            ->assertSet('errorMessage', 'You are not authorized to act on this leave request.');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'pending',
        ]);
    }

    public function test_inactive_employee_cannot_submit_a_leave_request(): void
    {
        $leaveType = LeaveType::factory()->create();
        $employee = User::factory()->inactive()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Inactive employees cannot submit leave requests.');

        $service->submit(
            userId: $employee->id,
            leaveTypeId: $leaveType->id,
            startDate: now()->addDays(5)->toImmutable(),
            endDate: now()->addDays(6)->toImmutable(),
            isHalfDay: false,
            reason: 'Should be blocked',
        );
    }

    public function test_inactive_leave_type_does_not_appear_in_the_apply_form_and_cannot_be_used(): void
    {
        $activeType = LeaveType::factory()->create();
        $inactiveType = LeaveType::factory()->inactive()->create(['name' => 'Retired Leave']);
        $employee = User::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $inactiveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $this->actingAs($employee);

        // The balance history table legitimately still shows "Retired Leave" (past
        // balances stay visible); it must not appear as a selectable dropdown option.
        Livewire::test(RequestForm::class)
            ->assertDontSee('<option value="'.$inactiveType->id.'">Retired Leave</option>', false);

        $service = $this->app->make(LeaveRequestService::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('This leave type is no longer active.');

        $service->submit(
            userId: $employee->id,
            leaveTypeId: $inactiveType->id,
            startDate: now()->addDays(5)->toImmutable(),
            endDate: now()->addDays(6)->toImmutable(),
            isHalfDay: false,
            reason: 'Should be blocked',
        );
    }

    public function test_manager_submitting_a_leave_request_is_auto_approved_and_balance_deducted(): void
    {
        $manager = User::factory()->manager()->create();
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $manager->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $leaveRequestId = $service->submit(
            userId: $manager->id,
            leaveTypeId: $leaveType->id,
            startDate: today()->addDays(5)->toImmutable(),
            endDate: today()->addDays(6)->toImmutable(),
            isHalfDay: false,
            reason: 'Manager leave',
        );

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequestId,
            'status' => 'approved',
            'approver_id' => $manager->id,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $manager->id,
            'leave_type_id' => $leaveType->id,
            'used_days' => 2,
        ]);
    }

    public function test_hr_submitting_a_leave_request_is_auto_approved_and_balance_deducted(): void
    {
        $hr = User::factory()->hr()->create();
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $hr->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $leaveRequestId = $service->submit(
            userId: $hr->id,
            leaveTypeId: $leaveType->id,
            startDate: today()->addDays(5)->toImmutable(),
            endDate: today()->addDays(5)->toImmutable(),
            isHalfDay: false,
            reason: 'HR leave',
        );

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequestId,
            'status' => 'approved',
            'approver_id' => $hr->id,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $hr->id,
            'leave_type_id' => $leaveType->id,
            'used_days' => 1,
        ]);
    }

    public function test_employee_submission_is_still_pending_and_unaffected_by_the_leadership_bypass(): void
    {
        $leaveType = LeaveType::factory()->create();
        $employee = User::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $leaveRequestId = $service->submit(
            userId: $employee->id,
            leaveTypeId: $leaveType->id,
            startDate: today()->addDays(5)->toImmutable(),
            endDate: today()->addDays(6)->toImmutable(),
            isHalfDay: false,
            reason: 'Employee leave',
        );

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequestId,
            'status' => 'pending',
            'approver_id' => null,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'used_days' => 0,
        ]);
    }
}
