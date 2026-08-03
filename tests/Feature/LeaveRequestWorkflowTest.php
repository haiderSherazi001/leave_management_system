<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\LeaveApprovals;
use App\Livewire\Leave\ApprovalQueue;
use App\Livewire\Leave\MyRequests;
use App\Livewire\Leave\RequestForm;
use App\Models\Holiday;
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
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);

        $this->actingAs($employee)->get(route('leave.apply'))->assertOk();
        $this->actingAs($employee)->get(route('leave.my-requests'))->assertOk();
        $this->actingAs($manager)->get(route('leave.approvals'))->assertOk();
        $this->actingAs($hr)->get(route('admin.leave-approvals'))->assertOk();
    }

    public function test_upcoming_holidays_are_shown_on_the_apply_for_leave_page(): void
    {
        $employee = User::factory()->create();
        Holiday::factory()->create(['date' => now()->addDays(4)->toDateString(), 'name' => 'Founders Day']);
        Holiday::factory()->create(['date' => now()->subMonths(2)->toDateString(), 'name' => 'Past Holiday']);

        $this->actingAs($employee);

        Livewire::test(RequestForm::class)
            ->assertSee('Upcoming Holidays')
            ->assertSee('Founders Day')
            ->assertDontSee('Past Holiday');
    }

    public function test_upcoming_holidays_card_is_hidden_when_there_are_none(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee);

        Livewire::test(RequestForm::class)->assertDontSee('Upcoming Holidays');
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
            'status' => 'pending_manager',
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

    public function test_manager_approval_forwards_to_hr_without_deducting_balance(): void
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
            'status' => 'pending_manager',
        ]);

        $this->actingAs($manager);

        Livewire::test(ApprovalQueue::class)
            ->call('approve', $leaveRequest->id)
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'pending_hr',
            'approver_id' => $manager->id,
            'hr_approver_id' => null,
            'decided_at' => null,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'used_days' => 0,
        ]);
    }

    public function test_hr_final_approval_deducts_balance_and_links_attendance(): void
    {
        $manager = User::factory()->manager()->create();
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $leaveRequest = LeaveRequest::factory()->pendingHr()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $manager->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'total_days' => 3,
        ]);

        $this->actingAs($hr);

        Livewire::test(LeaveApprovals::class)
            ->call('approve', $leaveRequest->id)
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'approved',
            'approver_id' => $manager->id,
            'hr_approver_id' => $hr->id,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'used_days' => 3,
        ]);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'date' => now()->addDays(3)->toDateString(),
        ]);
    }

    public function test_employee_cannot_access_the_approval_queue(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->get(route('leave.approvals'))
            ->assertForbidden();
    }

    public function test_employee_cannot_access_the_hr_approval_inbox(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)
            ->get(route('admin.leave-approvals'))
            ->assertForbidden();
    }

    public function test_manager_cannot_access_the_hr_approval_inbox(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('admin.leave-approvals'))
            ->assertForbidden();
    }

    /**
     * HR only has a say once a request reaches the PendingHR stage — while
     * it's still PendingManager, only the assigned manager can act on it.
     */
    public function test_hr_cannot_approve_or_reject_a_request_still_pending_manager_approval(): void
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

        $service = $this->app->make(LeaveRequestService::class);

        $this->expectException(AuthorizationException::class);

        $service->approve($leaveRequest->id, $hr);
    }

    /**
     * Symmetric to the HR case above: once a request has been forwarded to
     * HR, the manager who forwarded it (or any other manager) can no longer
     * act on it — only HR can.
     */
    public function test_manager_cannot_approve_a_request_pending_hr_approval(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->pendingHr()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $manager->id,
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $this->expectException(AuthorizationException::class);

        $service->approve($leaveRequest->id, $manager);
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
            'status' => 'pending_manager',
        ]);

        LeaveRequest::factory()->create([
            'user_id' => $employeeUnderB->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_manager',
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

    public function test_half_day_request_on_a_holiday_is_rejected(): void
    {
        $leaveType = LeaveType::factory()->create(['yearly_allocation_days' => 10]);
        $employee = User::factory()->create();
        $holidayDate = now()->addDays(5)->toDateString();
        Holiday::factory()->create(['date' => $holidayDate, 'name' => 'Founders Day']);

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
            ->set('startDate', $holidayDate)
            ->set('isHalfDay', true)
            ->set('reason', 'Doctor appointment')
            ->call('submit')
            ->assertHasErrors('form');

        $this->assertDatabaseCount('leave_requests', 0);
        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'used_days' => 0,
        ]);
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
            'status' => 'pending_manager',
        ]);

        $this->actingAs($managerA);

        Livewire::test(ApprovalQueue::class)
            ->call('approve', $leaveRequest->id)
            ->assertSet('errorMessage', 'You are not authorized to act on this leave request.');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'pending_manager',
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

    /**
     * A Manager applying for their own leave can no longer approve
     * themselves — payroll compliance requires HR's sign-off regardless of
     * who's asking — so their own request skips straight to PendingHR,
     * exactly as if they'd manually forwarded it via approve(). Balance is
     * not touched until HR actually finalizes it.
     */
    public function test_manager_submitting_a_leave_request_skips_straight_to_pending_hr(): void
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
            'status' => 'pending_hr',
            'approver_id' => $manager->id,
            'hr_approver_id' => null,
            'decided_at' => null,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $manager->id,
            'leave_type_id' => $leaveType->id,
            'used_days' => 0,
        ]);
    }

    public function test_hr_can_finalize_a_manager_self_submitted_request(): void
    {
        $manager = User::factory()->manager()->create();
        $hr = User::factory()->hr()->create();
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

        $service->approveByHr($leaveRequestId, $hr);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequestId,
            'status' => 'approved',
            'approver_id' => $manager->id,
            'hr_approver_id' => $hr->id,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $manager->id,
            'leave_type_id' => $leaveType->id,
            'used_days' => 2,
        ]);
    }

    /**
     * HR has no one above them in the approval chain, so their own request
     * is still auto-approved immediately — but it must now also deduct
     * balance and link attendance right away (previously the leadership
     * bypass deducted balance but never linked attendance for either role,
     * an existing gap this fix closes for the HR case).
     */
    public function test_hr_submitting_a_leave_request_is_auto_approved_with_balance_and_attendance(): void
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
            'approver_id' => null,
            'hr_approver_id' => $hr->id,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $hr->id,
            'leave_type_id' => $leaveType->id,
            'used_days' => 1,
        ]);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $hr->id,
            'date' => today()->addDays(5)->toDateString(),
            'status' => 'on_leave',
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
            'status' => 'pending_manager',
            'approver_id' => null,
        ]);

        $this->assertDatabaseHas('leave_balances', [
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'used_days' => 0,
        ]);
    }

    public function test_hr_inbox_shows_pending_hr_requests_company_wide_regardless_of_manager(): void
    {
        $managerA = User::factory()->manager()->create();
        $managerB = User::factory()->manager()->create();
        $hr = User::factory()->hr()->create();

        $employeeUnderA = User::factory()->create(['manager_id' => $managerA->id]);
        $employeeUnderB = User::factory()->create(['manager_id' => $managerB->id]);
        $leaveType = LeaveType::factory()->create();

        LeaveRequest::factory()->pendingHr()->create([
            'user_id' => $employeeUnderA->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $managerA->id,
        ]);

        LeaveRequest::factory()->pendingHr()->create([
            'user_id' => $employeeUnderB->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $managerB->id,
        ]);

        // Still at the manager stage — must not appear in the HR inbox.
        LeaveRequest::factory()->create([
            'user_id' => $employeeUnderA->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_manager',
        ]);

        $this->actingAs($hr);

        Livewire::test(LeaveApprovals::class)
            ->assertSee($employeeUnderA->name)
            ->assertSee($employeeUnderB->name)
            ->assertSee($managerA->name)
            ->assertSee($managerB->name);
    }

    public function test_manager_rejecting_a_request_is_final_and_records_the_manager_as_approver(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_manager',
        ]);

        $this->actingAs($manager);

        Livewire::test(ApprovalQueue::class)
            ->call('reject', $leaveRequest->id)
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'rejected',
            'approver_id' => $manager->id,
            'hr_approver_id' => null,
        ]);
    }

    public function test_hr_rejecting_a_request_records_hr_as_the_rejecting_approver(): void
    {
        $manager = User::factory()->manager()->create();
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->pendingHr()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $manager->id,
        ]);

        $this->actingAs($hr);

        Livewire::test(LeaveApprovals::class)
            ->call('reject', $leaveRequest->id)
            ->assertSet('errorMessage', null);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'rejected',
            'approver_id' => $manager->id,
            'hr_approver_id' => $hr->id,
        ]);
    }

    /**
     * An already-approved request is no longer at either pending stage, so
     * LeaveRequestPolicy denies it outright (defense in depth) before the
     * service even reaches its own status check.
     */
    public function test_a_request_cannot_be_hr_approved_twice(): void
    {
        $hr = User::factory()->hr()->create();
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->approved()->create([
            'leave_type_id' => $leaveType->id,
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $this->expectException(AuthorizationException::class);

        $service->approveByHr($leaveRequest->id, $hr);
    }

    public function test_manager_history_shows_only_their_own_teams_decided_requests(): void
    {
        $managerA = User::factory()->manager()->create();
        $managerB = User::factory()->manager()->create();
        $leaveType = LeaveType::factory()->create();

        $employeeUnderA = User::factory()->create(['name' => 'Under Manager A', 'manager_id' => $managerA->id]);
        $employeeUnderB = User::factory()->create(['name' => 'Under Manager B', 'manager_id' => $managerB->id]);

        LeaveRequest::factory()->approved()->create([
            'user_id' => $employeeUnderA->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $managerA->id,
        ]);

        LeaveRequest::factory()->rejected()->create([
            'user_id' => $employeeUnderB->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $managerB->id,
        ]);

        $this->actingAs($managerA);

        Livewire::test(ApprovalQueue::class)
            ->call('setTab', 'history')
            ->assertSee('Under Manager A')
            ->assertDontSee('Under Manager B');
    }

    /**
     * approver_id never changes once the manager acts, regardless of what
     * HR later decides — so a manager's history must include the eventual
     * outcome of requests they forwarded, not just ones they personally
     * rejected.
     */
    public function test_manager_history_includes_requests_forwarded_to_hr_regardless_of_who_finalized(): void
    {
        $manager = User::factory()->manager()->create();
        $hr = User::factory()->hr()->create(['name' => 'Finalizing HR']);
        $employee = User::factory()->create(['name' => 'Forwarded Employee']);
        $leaveType = LeaveType::factory()->create();

        LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'approved',
            'approver_id' => $manager->id,
            'hr_approver_id' => $hr->id,
            'decided_at' => now(),
        ]);

        $this->actingAs($manager);

        Livewire::test(ApprovalQueue::class)
            ->call('setTab', 'history')
            ->assertSee('Forwarded Employee')
            ->assertSee('Finalizing HR');
    }

    public function test_hr_history_is_company_wide_regardless_of_which_hr_user_finalized(): void
    {
        $hrA = User::factory()->hr()->create();
        $hrB = User::factory()->hr()->create();
        $hrViewing = User::factory()->hr()->create();
        $leaveType = LeaveType::factory()->create();

        $employeeA = User::factory()->create(['name' => 'Finalized By A']);
        $employeeB = User::factory()->create(['name' => 'Finalized By B']);

        LeaveRequest::factory()->create([
            'user_id' => $employeeA->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'approved',
            'hr_approver_id' => $hrA->id,
            'decided_at' => now(),
        ]);

        LeaveRequest::factory()->create([
            'user_id' => $employeeB->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'rejected',
            'hr_approver_id' => $hrB->id,
            'decided_at' => now(),
        ]);

        $this->actingAs($hrViewing);

        Livewire::test(LeaveApprovals::class)
            ->call('setTab', 'history')
            ->assertSee('Finalized By A')
            ->assertSee('Finalized By B');
    }

    public function test_pending_tab_is_unchanged_by_the_new_history_tab(): void
    {
        $manager = User::factory()->manager()->create();
        $leaveType = LeaveType::factory()->create();

        $pendingEmployee = User::factory()->create(['name' => 'Still Pending', 'manager_id' => $manager->id]);
        $decidedEmployee = User::factory()->create(['name' => 'Already Decided']);

        LeaveRequest::factory()->create([
            'user_id' => $pendingEmployee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_manager',
        ]);

        LeaveRequest::factory()->approved()->create([
            'user_id' => $decidedEmployee->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $manager->id,
        ]);

        $this->actingAs($manager);

        Livewire::test(ApprovalQueue::class)
            ->assertSee('Still Pending')
            ->assertDontSee('Already Decided');
    }

    public function test_manager_can_switch_between_pending_and_history_tabs(): void
    {
        $manager = User::factory()->manager()->create();
        $leaveType = LeaveType::factory()->create();

        $pendingEmployee = User::factory()->create(['name' => 'Manager Pending Case', 'manager_id' => $manager->id]);
        $decidedEmployee = User::factory()->create(['name' => 'Manager Decided Case']);

        LeaveRequest::factory()->create([
            'user_id' => $pendingEmployee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_manager',
        ]);

        LeaveRequest::factory()->approved()->create([
            'user_id' => $decidedEmployee->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $manager->id,
        ]);

        $this->actingAs($manager);

        Livewire::test(ApprovalQueue::class)
            ->assertSee('Manager Pending Case')
            ->assertDontSee('Manager Decided Case')
            ->call('setTab', 'history')
            ->assertDontSee('Manager Pending Case')
            ->assertSee('Manager Decided Case');
    }

    public function test_hr_can_switch_between_pending_and_history_tabs(): void
    {
        $hr = User::factory()->hr()->create();
        $leaveType = LeaveType::factory()->create();

        $pendingEmployee = User::factory()->create(['name' => 'HR Pending Case']);
        $decidedEmployee = User::factory()->create(['name' => 'HR Decided Case']);

        LeaveRequest::factory()->pendingHr()->create([
            'user_id' => $pendingEmployee->id,
            'leave_type_id' => $leaveType->id,
        ]);

        LeaveRequest::factory()->approved()->create([
            'user_id' => $decidedEmployee->id,
            'leave_type_id' => $leaveType->id,
        ]);

        $this->actingAs($hr);

        Livewire::test(LeaveApprovals::class)
            ->assertSee('HR Pending Case')
            ->assertDontSee('HR Decided Case')
            ->call('setTab', 'history')
            ->assertDontSee('HR Pending Case')
            ->assertSee('HR Decided Case');
    }

    public function test_hr_can_view_company_wide_pending_manager_requests_read_only(): void
    {
        $manager = User::factory()->manager()->create(['name' => 'Direct Manager']);
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create(['name' => 'Still With Manager', 'manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_manager',
        ]);

        $this->actingAs($hr);

        Livewire::test(LeaveApprovals::class)
            ->call('setTab', 'awaiting_manager')
            ->assertSee('Still With Manager')
            ->assertSee('Direct Manager')
            // Read-only — HR has no approve/reject action available on this tab.
            ->assertDontSee('Approve')
            ->assertDontSee('Reject')
            ->assertSuccessful();

        // A raw call('approve', ...) must still be rejected server-side even
        // though the button isn't rendered, since it's still at the manager
        // stage — see test_hr_cannot_approve_or_reject_a_request_still_pending_manager_approval.
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'status' => 'pending_manager',
        ]);
    }

    public function test_manager_history_is_paginated(): void
    {
        $manager = User::factory()->manager()->create();
        $leaveType = LeaveType::factory()->create();

        foreach (range(1, 12) as $i) {
            LeaveRequest::factory()->approved()->create([
                'user_id' => User::factory()->create(['name' => sprintf('History Row %02d', $i)])->id,
                'leave_type_id' => $leaveType->id,
                'approver_id' => $manager->id,
                'decided_at' => now()->subDays($i),
            ]);
        }

        $this->actingAs($manager);

        // Ordered newest-decided-first: rows 01-10 (most recent) on page 1, 11-12 on page 2.
        // Zero-padded so e.g. "Row 01" can never accidentally substring-match "Row 11".
        Livewire::test(ApprovalQueue::class)
            ->call('setTab', 'history')
            ->assertSee('History Row 01')
            ->assertSee('History Row 10')
            ->assertDontSee('History Row 11')
            ->assertDontSee('History Row 12')
            ->call('gotoPage', 2)
            ->assertSee('History Row 11')
            ->assertSee('History Row 12')
            ->assertDontSee('History Row 01');
    }

    public function test_hr_history_is_paginated(): void
    {
        $hr = User::factory()->hr()->create();
        $leaveType = LeaveType::factory()->create();

        foreach (range(1, 12) as $i) {
            LeaveRequest::factory()->approved()->create([
                'user_id' => User::factory()->create(['name' => sprintf('HR History Row %02d', $i)])->id,
                'leave_type_id' => $leaveType->id,
                'decided_at' => now()->subDays($i),
            ]);
        }

        $this->actingAs($hr);

        Livewire::test(LeaveApprovals::class)
            ->call('setTab', 'history')
            ->assertSee('HR History Row 01')
            ->assertSee('HR History Row 10')
            ->assertDontSee('HR History Row 11')
            ->call('gotoPage', 2)
            ->assertSee('HR History Row 11')
            ->assertSee('HR History Row 12');
    }

    public function test_switching_tabs_resets_pagination_to_page_one(): void
    {
        $manager = User::factory()->manager()->create();
        $leaveType = LeaveType::factory()->create();

        foreach (range(1, 12) as $i) {
            LeaveRequest::factory()->approved()->create([
                'user_id' => User::factory()->create(['name' => sprintf('Reset Row %02d', $i)])->id,
                'leave_type_id' => $leaveType->id,
                'approver_id' => $manager->id,
                'decided_at' => now()->subDays($i),
            ]);
        }

        $this->actingAs($manager);

        Livewire::test(ApprovalQueue::class)
            ->call('setTab', 'history')
            ->call('gotoPage', 2)
            ->assertSee('Reset Row 11')
            ->call('setTab', 'pending')
            ->call('setTab', 'history')
            ->assertSee('Reset Row 01')
            ->assertDontSee('Reset Row 11');
    }

    public function test_hr_history_can_be_searched_filtered_by_leave_type_and_status(): void
    {
        $hr = User::factory()->hr()->create();
        $sick = LeaveType::factory()->create(['name' => 'Sick']);
        $annual = LeaveType::factory()->create(['name' => 'Annual']);

        $approvedSick = User::factory()->create(['name' => 'Approved Sick Person']);
        $rejectedAnnual = User::factory()->create(['name' => 'Rejected Annual Person']);

        LeaveRequest::factory()->approved()->create([
            'user_id' => $approvedSick->id,
            'leave_type_id' => $sick->id,
        ]);

        LeaveRequest::factory()->rejected()->create([
            'user_id' => $rejectedAnnual->id,
            'leave_type_id' => $annual->id,
        ]);

        $this->actingAs($hr);

        Livewire::test(LeaveApprovals::class)
            ->call('setTab', 'history')
            ->set('historySearch', 'Approved Sick')
            ->assertSee('Approved Sick Person')
            ->assertDontSee('Rejected Annual Person');

        Livewire::test(LeaveApprovals::class)
            ->call('setTab', 'history')
            ->set('historyLeaveType', $annual->id)
            ->assertSee('Rejected Annual Person')
            ->assertDontSee('Approved Sick Person');

        Livewire::test(LeaveApprovals::class)
            ->call('setTab', 'history')
            ->set('historyStatus', 'rejected')
            ->assertSee('Rejected Annual Person')
            ->assertDontSee('Approved Sick Person');
    }

    public function test_manager_history_can_be_searched_and_filtered_by_leave_type(): void
    {
        $manager = User::factory()->manager()->create();
        $sick = LeaveType::factory()->create(['name' => 'Sick']);
        $annual = LeaveType::factory()->create(['name' => 'Annual']);

        $sickEmployee = User::factory()->create(['name' => 'Sick Employee']);
        $annualEmployee = User::factory()->create(['name' => 'Annual Employee']);

        LeaveRequest::factory()->approved()->create([
            'user_id' => $sickEmployee->id,
            'leave_type_id' => $sick->id,
            'approver_id' => $manager->id,
        ]);

        LeaveRequest::factory()->approved()->create([
            'user_id' => $annualEmployee->id,
            'leave_type_id' => $annual->id,
            'approver_id' => $manager->id,
        ]);

        $this->actingAs($manager);

        Livewire::test(ApprovalQueue::class)
            ->call('setTab', 'history')
            ->set('historySearch', 'Sick Employee')
            ->assertSee('Sick Employee')
            ->assertDontSee('Annual Employee');

        Livewire::test(ApprovalQueue::class)
            ->call('setTab', 'history')
            ->set('historyLeaveType', $annual->id)
            ->assertSee('Annual Employee')
            ->assertDontSee('Sick Employee');
    }

    public function test_my_requests_can_be_filtered_by_leave_type_and_status(): void
    {
        // Distinguished by decision_note (unique per row) rather than leave
        // type name, since the leave-type filter <select> always lists every
        // type as an option regardless of which one is currently selected —
        // asserting on the type name itself would always "see" both.
        $employee = User::factory()->create();
        $sick = LeaveType::factory()->create(['name' => 'Sick']);
        $annual = LeaveType::factory()->create(['name' => 'Annual']);

        LeaveRequest::factory()->approved()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $sick->id,
            'decision_note' => 'Sick request note',
        ]);

        LeaveRequest::factory()->rejected()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $annual->id,
            'decision_note' => 'Annual request note',
        ]);

        $this->actingAs($employee);

        Livewire::test(MyRequests::class)
            ->set('leaveTypeFilter', $annual->id)
            ->assertSee('Annual request note')
            ->assertDontSee('Sick request note');

        Livewire::test(MyRequests::class)
            ->set('statusFilter', 'rejected')
            ->assertSee('Annual request note')
            ->assertDontSee('Sick request note');
    }
}
