<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LeaveRequestStatus;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Notifications\LeaveRequestAwaitingHrApprovalNotification;
use App\Notifications\LeaveRequestStatusNotification;
use App\Notifications\NewLeaveRequestNotification;
use App\Services\LeaveRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeaveRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_manager_is_notified_when_an_employee_submits_a_leave_request(): void
    {
        Notification::fake();

        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create(['yearly_allocation_days' => 10]);

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
            startDate: now()->addDays(5)->toImmutable(),
            endDate: now()->addDays(6)->toImmutable(),
            isHalfDay: false,
            reason: 'Family trip',
        );

        Notification::assertSentTo(
            $manager,
            NewLeaveRequestNotification::class,
            fn ($notification) => $notification->toDatabase($manager)['leave_request_id'] === $leaveRequestId,
        );
    }

    /**
     * An employee's direct manager_id always takes priority over their
     * department's manager — the department manager is a fallback for
     * employees with no direct manager, never a second, parallel approver.
     * So when both are set and differ, only the direct manager is notified.
     */
    public function test_only_direct_manager_is_notified_when_department_manager_differs(): void
    {
        Notification::fake();

        $directManager = User::factory()->manager()->create();
        $departmentManager = User::factory()->manager()->create();
        $department = Department::factory()->create(['manager_id' => $departmentManager->id]);
        $employee = User::factory()->create([
            'manager_id' => $directManager->id,
            'department_id' => $department->id,
        ]);
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $service->submit(
            userId: $employee->id,
            leaveTypeId: $leaveType->id,
            startDate: now()->addDays(5)->toImmutable(),
            endDate: now()->addDays(6)->toImmutable(),
            isHalfDay: false,
            reason: 'Trip',
        );

        Notification::assertSentTo($directManager, NewLeaveRequestNotification::class);
        Notification::assertNotSentTo($departmentManager, NewLeaveRequestNotification::class);
    }

    /**
     * With no direct manager assigned, the department's manager is the
     * fallback approver and receives the notification.
     */
    public function test_department_manager_is_notified_when_employee_has_no_direct_manager(): void
    {
        Notification::fake();

        $departmentManager = User::factory()->manager()->create();
        $department = Department::factory()->create(['manager_id' => $departmentManager->id]);
        $employee = User::factory()->create([
            'manager_id' => null,
            'department_id' => $department->id,
        ]);
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $service->submit(
            userId: $employee->id,
            leaveTypeId: $leaveType->id,
            startDate: now()->addDays(5)->toImmutable(),
            endDate: now()->addDays(6)->toImmutable(),
            isHalfDay: false,
            reason: 'Trip',
        );

        Notification::assertSentTo($departmentManager, NewLeaveRequestNotification::class);
    }

    public function test_no_notification_sent_when_employee_has_no_assigned_manager(): void
    {
        Notification::fake();

        $employee = User::factory()->create(['manager_id' => null, 'department_id' => null]);
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $service->submit(
            userId: $employee->id,
            leaveTypeId: $leaveType->id,
            startDate: now()->addDays(5)->toImmutable(),
            endDate: now()->addDays(6)->toImmutable(),
            isHalfDay: false,
            reason: 'Trip',
        );

        Notification::assertNothingSent();
    }

    public function test_hr_is_notified_when_a_manager_forwards_a_request(): void
    {
        Notification::fake();

        $manager = User::factory()->manager()->create();
        $hr = User::factory()->hr()->create();
        $inactiveHr = User::factory()->hr()->inactive()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_manager',
        ]);

        $service = $this->app->make(LeaveRequestService::class);
        $service->approve($leaveRequest->id, $manager);

        Notification::assertSentTo(
            $hr,
            LeaveRequestAwaitingHrApprovalNotification::class,
            fn ($notification) => $notification->toDatabase($hr)['leave_request_id'] === $leaveRequest->id,
        );
        Notification::assertNotSentTo($inactiveHr, LeaveRequestAwaitingHrApprovalNotification::class);
    }

    public function test_employee_is_notified_when_request_is_approved_by_hr(): void
    {
        Notification::fake();

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
            'end_date' => now()->addDays(4)->toDateString(),
            'total_days' => 2,
        ]);

        $service = $this->app->make(LeaveRequestService::class);
        $service->approveByHr($leaveRequest->id, $hr, 'Enjoy!');

        Notification::assertSentTo(
            $employee,
            LeaveRequestStatusNotification::class,
            fn ($notification) => $notification->toDatabase($employee)['status'] === LeaveRequestStatus::Approved->value,
        );
    }

    public function test_employee_is_notified_when_request_is_rejected_by_manager(): void
    {
        Notification::fake();

        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'status' => 'pending_manager',
        ]);

        $service = $this->app->make(LeaveRequestService::class);
        $service->reject($leaveRequest->id, $manager, 'Not enough coverage.');

        Notification::assertSentTo(
            $employee,
            LeaveRequestStatusNotification::class,
            fn ($notification) => $notification->toDatabase($employee)['status'] === LeaveRequestStatus::Rejected->value,
        );
    }

    public function test_employee_is_notified_when_request_is_rejected_by_hr(): void
    {
        Notification::fake();

        $manager = User::factory()->manager()->create();
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create();

        $leaveRequest = LeaveRequest::factory()->pendingHr()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'approver_id' => $manager->id,
        ]);

        $service = $this->app->make(LeaveRequestService::class);
        $service->reject($leaveRequest->id, $hr, 'Insufficient coverage.');

        Notification::assertSentTo(
            $employee,
            LeaveRequestStatusNotification::class,
            fn ($notification) => $notification->toDatabase($employee)['status'] === LeaveRequestStatus::Rejected->value,
        );
    }

    public function test_no_notification_is_sent_when_a_manager_submits_their_own_leave_request(): void
    {
        Notification::fake();

        $topManager = User::factory()->manager()->create();
        $manager = User::factory()->manager()->create(['manager_id' => $topManager->id]);
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $manager->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $service->submit(
            userId: $manager->id,
            leaveTypeId: $leaveType->id,
            startDate: now()->addDays(5)->toImmutable(),
            endDate: now()->addDays(6)->toImmutable(),
            isHalfDay: false,
            reason: 'Manager leave',
        );

        Notification::assertNothingSent();
    }

    public function test_no_notification_is_sent_when_hr_submits_their_own_leave_request(): void
    {
        Notification::fake();

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

        $service->submit(
            userId: $hr->id,
            leaveTypeId: $leaveType->id,
            startDate: now()->addDays(5)->toImmutable(),
            endDate: now()->addDays(6)->toImmutable(),
            isHalfDay: false,
            reason: 'HR leave',
        );

        Notification::assertNothingSent();
    }
}
