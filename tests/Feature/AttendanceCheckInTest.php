<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Attendance\CheckIn;
use App\Models\Attendance;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AttendanceCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_page_is_accessible_to_any_authenticated_role(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();
        $hr = User::factory()->hr()->create();

        foreach ([$employee, $manager, $hr] as $user) {
            $this->actingAs($user)->get(route('attendance.check-in'))->assertOk();
        }
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
}
