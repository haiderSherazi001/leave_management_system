<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Leave\TeamCalendar;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\CalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeamCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_calendar_page_is_accessible_to_managers_only(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create();

        $this->actingAs($manager)->get(route('leave.team-calendar'))->assertOk();
        $this->actingAs($employee)->get(route('leave.team-calendar'))->assertForbidden();
    }

    public function test_manager_sees_only_their_direct_reports_approved_leave(): void
    {
        $managerA = User::factory()->manager()->create();
        $managerB = User::factory()->manager()->create();
        $employeeA = User::factory()->create(['manager_id' => $managerA->id]);
        $employeeB = User::factory()->create(['manager_id' => $managerB->id]);

        $start = CarbonImmutable::now()->startOfMonth();
        $end = CarbonImmutable::now()->endOfMonth();

        LeaveRequest::factory()->approved()->create([
            'user_id' => $employeeA->id,
            'start_date' => $start->addDays(2)->toDateString(),
            'end_date' => $start->addDays(2)->toDateString(),
        ]);
        LeaveRequest::factory()->approved()->create([
            'user_id' => $employeeB->id,
            'start_date' => $start->addDays(2)->toDateString(),
            'end_date' => $start->addDays(2)->toDateString(),
        ]);

        $service = $this->app->make(CalendarService::class);
        $events = $service->teamEventsBetween($managerA->id, $start->toDateString(), $end->toDateString());

        $leaveEvents = array_filter($events, fn (array $event) => $event['extendedProps']['type'] === 'leave');
        $this->assertCount(1, $leaveEvents);
        $this->assertStringContainsString($employeeA->name, array_values($leaveEvents)[0]['title']);
    }

    public function test_manager_sees_approved_leave_for_employees_in_a_department_they_head(): void
    {
        $manager = User::factory()->manager()->create();
        $department = Department::factory()->create(['manager_id' => $manager->id]);
        $employee = User::factory()->create(['department_id' => $department->id]);

        $start = CarbonImmutable::now()->startOfMonth();
        $end = CarbonImmutable::now()->endOfMonth();

        LeaveRequest::factory()->approved()->create([
            'user_id' => $employee->id,
            'start_date' => $start->addDays(3)->toDateString(),
            'end_date' => $start->addDays(3)->toDateString(),
        ]);

        $service = $this->app->make(CalendarService::class);
        $events = $service->teamEventsBetween($manager->id, $start->toDateString(), $end->toDateString());

        $leaveEvents = array_filter($events, fn (array $event) => $event['extendedProps']['type'] === 'leave');
        $this->assertCount(1, $leaveEvents);
    }

    /**
     * Regression test for the reported data leak: an employee whose direct
     * manager differs from their department's manager must appear on only
     * the direct manager's calendar — the department manager is a fallback
     * for employees with no direct manager, never a second visible approver.
     */
    public function test_department_manager_does_not_see_leave_for_an_employee_with_a_different_direct_manager(): void
    {
        $directManager = User::factory()->manager()->create();
        $departmentManager = User::factory()->manager()->create();
        $department = Department::factory()->create(['manager_id' => $departmentManager->id]);
        $employee = User::factory()->create([
            'manager_id' => $directManager->id,
            'department_id' => $department->id,
        ]);

        $start = CarbonImmutable::now()->startOfMonth();
        $end = CarbonImmutable::now()->endOfMonth();

        LeaveRequest::factory()->approved()->create([
            'user_id' => $employee->id,
            'start_date' => $start->addDays(2)->toDateString(),
            'end_date' => $start->addDays(2)->toDateString(),
        ]);

        $service = $this->app->make(CalendarService::class);

        $directEvents = $service->teamEventsBetween($directManager->id, $start->toDateString(), $end->toDateString());
        $directLeaveEvents = array_filter($directEvents, fn (array $event) => $event['extendedProps']['type'] === 'leave');
        $this->assertCount(1, $directLeaveEvents, 'Direct manager should see the request');

        $departmentEvents = $service->teamEventsBetween($departmentManager->id, $start->toDateString(), $end->toDateString());
        $departmentLeaveEvents = array_filter($departmentEvents, fn (array $event) => $event['extendedProps']['type'] === 'leave');
        $this->assertCount(0, $departmentLeaveEvents, 'Department manager must not see it — the employee has a direct manager');
    }

    public function test_pending_leave_requests_are_not_shown_on_the_calendar(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);

        $start = CarbonImmutable::now()->startOfMonth();
        $end = CarbonImmutable::now()->endOfMonth();

        LeaveRequest::factory()->create([
            'user_id' => $employee->id,
            'start_date' => $start->addDays(1)->toDateString(),
            'end_date' => $start->addDays(1)->toDateString(),
        ]);

        $service = $this->app->make(CalendarService::class);
        $events = $service->teamEventsBetween($manager->id, $start->toDateString(), $end->toDateString());

        $leaveEvents = array_filter($events, fn (array $event) => $event['extendedProps']['type'] === 'leave');
        $this->assertCount(0, $leaveEvents);
    }

    public function test_holidays_are_included_regardless_of_team(): void
    {
        $manager = User::factory()->manager()->create();

        $start = CarbonImmutable::now()->startOfMonth();
        $end = CarbonImmutable::now()->endOfMonth();

        Holiday::factory()->create(['date' => $start->addDays(5)->toDateString(), 'name' => 'Company Holiday']);

        $service = $this->app->make(CalendarService::class);
        $events = $service->teamEventsBetween($manager->id, $start->toDateString(), $end->toDateString());

        $holidayEvents = array_filter($events, fn (array $event) => $event['extendedProps']['type'] === 'holiday');
        $this->assertCount(1, $holidayEvents);
        $this->assertSame('Company Holiday', array_values($holidayEvents)[0]['title']);
    }

    public function test_leave_event_end_date_is_exclusive_for_full_calendar(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);

        $start = CarbonImmutable::now()->startOfMonth();

        LeaveRequest::factory()->approved()->create([
            'user_id' => $employee->id,
            'start_date' => $start->addDays(2)->toDateString(),
            'end_date' => $start->addDays(4)->toDateString(),
        ]);

        $service = $this->app->make(CalendarService::class);
        $events = $service->teamEventsBetween(
            $manager->id,
            $start->toDateString(),
            $start->endOfMonth()->toDateString(),
        );

        $leaveEvents = array_values(array_filter($events, fn (array $event) => $event['extendedProps']['type'] === 'leave'));
        $this->assertCount(1, $leaveEvents);
        // Normalize: LeaveRequest::factory() writes through Eloquent's 'date' cast,
        // which (unlike the production DB::table() insert path) serializes to a full
        // datetime string in SQLite — a pre-existing test-data artifact, not something
        // CalendarService needs to guard against for real, DB::table()-written rows.
        $this->assertSame($start->addDays(2)->toDateString(), CarbonImmutable::parse($leaveEvents[0]['start'])->toDateString());
        $this->assertSame($start->addDays(5)->toDateString(), CarbonImmutable::parse($leaveEvents[0]['end'])->toDateString());
    }

    public function test_load_events_for_range_dispatches_team_scoped_events(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);

        $start = CarbonImmutable::now()->addMonth()->startOfMonth();
        $end = CarbonImmutable::now()->addMonth()->endOfMonth();

        LeaveRequest::factory()->approved()->create([
            'user_id' => $employee->id,
            'start_date' => $start->addDays(1)->toDateString(),
            'end_date' => $start->addDays(1)->toDateString(),
        ]);

        $this->actingAs($manager);

        Livewire::test(TeamCalendar::class)
            ->call('loadEventsForRange', $start->toDateString(), $end->toDateString())
            ->assertDispatched('calendar-events-updated');
    }
}
