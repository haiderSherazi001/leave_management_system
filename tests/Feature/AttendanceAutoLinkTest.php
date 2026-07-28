<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendanceService;
use App\Services\LeaveRequestService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceAutoLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_approving_leave_creates_on_leave_attendance_only_for_working_days(): void
    {
        WorkSchedule::factory()->create(['working_days' => [1, 2, 3, 4, 5]]);

        $manager = User::factory()->manager()->create();
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create(['yearly_allocation_days' => 20]);

        $friday = CarbonImmutable::now()->next(CarbonImmutable::FRIDAY);
        $saturday = $friday->addDay();
        $sunday = $friday->addDays(2);
        $monday = $friday->addDays(3);

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => $friday->year,
            'allocated_days' => 20,
            'used_days' => 0,
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $id = $service->submit(
            userId: $employee->id,
            leaveTypeId: $leaveType->id,
            startDate: $friday,
            endDate: $monday,
            isHalfDay: false,
            reason: 'Long weekend',
        );

        // Only the 2 working days (Fri, Mon) should count toward the balance.
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'total_days' => 2]);

        $service->approve($id, $manager);
        $service->approveByHr($id, $hr);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'date' => $friday->toDateString(),
            'status' => 'on_leave',
        ]);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'date' => $monday->toDateString(),
            'status' => 'on_leave',
        ]);
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id, 'date' => $saturday->toDateString()]);
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id, 'date' => $sunday->toDateString()]);
    }

    public function test_approving_leave_skips_a_holiday_for_both_attendance_and_balance(): void
    {
        WorkSchedule::factory()->create(['working_days' => [1, 2, 3, 4, 5]]);

        $manager = User::factory()->manager()->create();
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $leaveType = LeaveType::factory()->create(['yearly_allocation_days' => 20]);

        $monday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
        $tuesday = $monday->addDay();
        $wednesday = $monday->addDays(2);

        Holiday::factory()->create(['date' => $tuesday->toDateString(), 'name' => 'Test Holiday']);

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => $monday->year,
            'allocated_days' => 20,
            'used_days' => 0,
        ]);

        $service = $this->app->make(LeaveRequestService::class);

        $id = $service->submit(
            userId: $employee->id,
            leaveTypeId: $leaveType->id,
            startDate: $monday,
            endDate: $wednesday,
            isHalfDay: false,
            reason: 'Around the holiday',
        );

        // Monday + Wednesday only — the holiday in between isn't charged.
        $this->assertDatabaseHas('leave_requests', ['id' => $id, 'total_days' => 2]);

        $service->approve($id, $manager);
        $service->approveByHr($id, $hr);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'date' => $monday->toDateString(),
            'status' => 'on_leave',
        ]);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'date' => $wednesday->toDateString(),
            'status' => 'on_leave',
        ]);
        $this->assertDatabaseMissing('attendances', ['user_id' => $employee->id, 'date' => $tuesday->toDateString()]);
    }

    public function test_submit_rejects_a_date_range_with_zero_working_days(): void
    {
        WorkSchedule::factory()->create(['working_days' => [1, 2, 3, 4, 5]]);

        $employee = User::factory()->create();
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $saturday = CarbonImmutable::now()->next(CarbonImmutable::SATURDAY);
        $sunday = $saturday->addDay();

        $service = $this->app->make(LeaveRequestService::class);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('The selected date range does not include any working days.');

        $service->submit(
            userId: $employee->id,
            leaveTypeId: $leaveType->id,
            startDate: $saturday,
            endDate: $sunday,
            isHalfDay: false,
            reason: 'Weekend only',
        );
    }

    public function test_mark_on_leave_is_idempotent(): void
    {
        $employee = User::factory()->create();
        $service = $this->app->make(AttendanceService::class);

        $service->markOnLeave($employee->id, '2026-08-10');
        $service->markOnLeave($employee->id, '2026-08-10');

        $this->assertDatabaseCount('attendances', 1);
        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'date' => '2026-08-10',
            'status' => 'on_leave',
        ]);
    }

    public function test_checking_in_after_being_marked_on_leave_does_not_downgrade_status(): void
    {
        $employee = User::factory()->create();
        $service = $this->app->make(AttendanceService::class);

        $service->markOnLeave($employee->id, '2026-08-10');
        $service->checkIn($employee->id, '2026-08-10', (float) config('attendance.office_latitude'), (float) config('attendance.office_longitude'));

        $record = DB::table('attendances')->where('user_id', $employee->id)->where('date', '2026-08-10')->first();

        $this->assertSame('on_leave', $record->status);
        $this->assertNotNull($record->check_in_at);

        // Checked in but never checked out on a day that's on_leave - this
        // must not report a live, ever-growing "hours worked" for a day
        // that's officially leave (reported as a real user-facing bug: the
        // web page kept showing a "still counting" duration for exactly
        // this case).
        $this->assertNull($service->minutesWorked($record->check_in_at, $record->check_out_at, $record->status));
    }

    public function test_checking_out_after_being_marked_on_leave_reports_the_real_worked_duration(): void
    {
        $employee = User::factory()->create();
        $service = $this->app->make(AttendanceService::class);

        $service->markOnLeave($employee->id, '2026-08-10');
        $service->checkIn($employee->id, '2026-08-10', (float) config('attendance.office_latitude'), (float) config('attendance.office_longitude'));
        $service->checkOut($employee->id, '2026-08-10');

        $record = DB::table('attendances')->where('user_id', $employee->id)->where('date', '2026-08-10')->first();

        // Once actually checked out, the real elapsed time is a completed
        // fact (e.g. a half-day-leave employee who worked part of the day)
        // and should still be reported - only the *live, still-counting*
        // state is suppressed for an on_leave day, not a finished session.
        $this->assertNotNull($service->minutesWorked($record->check_in_at, $record->check_out_at, $record->status));
    }
}
