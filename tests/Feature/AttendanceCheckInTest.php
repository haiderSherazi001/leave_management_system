<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Attendance\CheckIn;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_attendance_page_is_accessible_to_any_authenticated_role(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();
        $hr = User::factory()->hr()->create();

        foreach ([$employee, $manager, $hr] as $user) {
            $this->actingAs($user)->get(route('attendance.check-in'))->assertOk();
        }
    }

    public function test_check_in_buttons_are_hidden_and_a_holiday_notice_is_shown_on_a_holiday(): void
    {
        Holiday::factory()->create(['date' => now()->toDateString(), 'name' => 'Independence Day']);
        $employee = User::factory()->create();

        $this->actingAs($employee);

        Livewire::test(CheckIn::class)
            ->assertSee('Independence Day')
            ->assertSee('no check-in is required')
            ->assertDontSee('Check In')
            ->assertDontSee('Check Out');
    }

    public function test_check_in_buttons_are_shown_on_a_regular_working_day(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee);

        Livewire::test(CheckIn::class)
            ->assertSee('Check In')
            ->assertSee('Check Out')
            ->assertDontSee('no check-in is required');
    }

    public function test_employee_can_check_in(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee);

        Livewire::test(CheckIn::class)->call('checkIn');

        $this->assertDatabaseHas('attendances', [
            'user_id' => $employee->id,
            'date' => now()->toDateString(),
            'status' => 'present',
        ]);

        $record = Attendance::where('user_id', $employee->id)->firstOrFail();
        $this->assertNotNull($record->check_in_at);
        $this->assertNull($record->check_out_at);
    }

    public function test_employee_cannot_check_in_twice(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee);

        Livewire::test(CheckIn::class)->call('checkIn');

        Livewire::test(CheckIn::class)
            ->call('checkIn')
            ->assertSet('errorMessage', 'You have already checked in today.');

        $this->assertDatabaseCount('attendances', 1);
    }

    public function test_employee_can_check_out_after_checking_in(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee);

        Livewire::test(CheckIn::class)->call('checkIn');
        Livewire::test(CheckIn::class)->call('checkOut');

        $record = Attendance::where('user_id', $employee->id)->firstOrFail();
        $this->assertNotNull($record->check_out_at);
    }

    public function test_employee_cannot_check_out_before_checking_in(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee);

        Livewire::test(CheckIn::class)
            ->call('checkOut')
            ->assertSet('errorMessage', 'You must check in before checking out.');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_employee_cannot_check_out_twice(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee);

        Livewire::test(CheckIn::class)->call('checkIn');
        Livewire::test(CheckIn::class)->call('checkOut');

        Livewire::test(CheckIn::class)
            ->call('checkOut')
            ->assertSet('errorMessage', 'You have already checked out today.');
    }

    public function test_attendance_records_are_scoped_per_user(): void
    {
        $employeeA = User::factory()->create();
        $employeeB = User::factory()->create();

        $service = $this->app->make(AttendanceService::class);
        $service->checkIn($employeeA->id, now()->toDateString());

        $this->assertNotNull($service->findForDate($employeeA->id, now()->toDateString()));
        $this->assertNull($service->findForDate($employeeB->id, now()->toDateString()));
    }

    public function test_application_operates_in_pakistan_time(): void
    {
        $this->assertSame('Asia/Karachi', config('app.timezone'));
        $this->assertSame('Asia/Karachi', now()->timezone->getName());
    }

    public function test_check_in_before_start_time_is_present(): void
    {
        WorkSchedule::factory()->create(['start_time' => '09:00:00', 'grace_minutes' => 10]);
        $employee = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-08-10 08:55:00'));

        $service = $this->app->make(AttendanceService::class);
        $service->checkIn($employee->id, '2026-08-10');

        $this->assertDatabaseHas('attendances', ['user_id' => $employee->id, 'status' => 'present']);
    }

    public function test_check_in_within_the_grace_period_is_present(): void
    {
        WorkSchedule::factory()->create(['start_time' => '09:00:00', 'grace_minutes' => 10]);
        $employee = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-08-10 09:09:00'));

        $service = $this->app->make(AttendanceService::class);
        $service->checkIn($employee->id, '2026-08-10');

        $this->assertDatabaseHas('attendances', ['user_id' => $employee->id, 'status' => 'present']);
    }

    public function test_check_in_past_the_grace_period_is_late(): void
    {
        WorkSchedule::factory()->create(['start_time' => '09:00:00', 'grace_minutes' => 10]);
        $employee = User::factory()->create();

        Carbon::setTestNow(Carbon::parse('2026-08-10 09:15:00'));

        $service = $this->app->make(AttendanceService::class);
        $service->checkIn($employee->id, '2026-08-10');

        $this->assertDatabaseHas('attendances', ['user_id' => $employee->id, 'status' => 'late']);
    }

    public function test_check_in_without_a_configured_schedule_defaults_to_present(): void
    {
        $employee = User::factory()->create();

        $service = $this->app->make(AttendanceService::class);
        $service->checkIn($employee->id, now()->toDateString());

        $this->assertDatabaseHas('attendances', ['user_id' => $employee->id, 'status' => 'present']);
    }
}
