<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Holidays;
use App\Livewire\Admin\WorkSchedule;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkScheduleHolidayTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_render_successfully_over_http(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get(route('admin.work-schedule'))->assertOk();
        $this->actingAs($hr)->get(route('admin.holidays'))->assertOk();
    }

    public function test_non_hr_users_cannot_access_work_schedule_or_holidays(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();

        foreach ([$employee, $manager] as $user) {
            $this->actingAs($user)->get(route('admin.work-schedule'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.holidays'))->assertForbidden();
        }
    }

    public function test_hr_can_save_the_work_schedule(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr);

        Livewire::test(WorkSchedule::class)
            ->set('workingDays', [1, 2, 3, 4, 5])
            ->set('startTime', '09:00')
            ->set('endTime', '17:30')
            ->set('graceMinutes', 15)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('work_schedule', [
            'start_time' => '09:00:00',
            'end_time' => '17:30:00',
            'grace_minutes' => 15,
        ]);
    }

    public function test_work_schedule_end_time_must_be_after_start_time(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr);

        Livewire::test(WorkSchedule::class)
            ->set('workingDays', [1, 2, 3, 4, 5])
            ->set('startTime', '17:00')
            ->set('endTime', '09:00')
            ->set('graceMinutes', 0)
            ->call('save')
            ->assertHasErrors('endTime');
    }

    public function test_hr_can_create_edit_and_delete_a_holiday(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr);

        Livewire::test(Holidays::class)
            ->call('startCreate')
            ->set('date', '2026-12-25')
            ->set('name', 'Christmas Day')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('holidays', [
            'date' => '2026-12-25',
            'name' => 'Christmas Day',
        ]);

        $holiday = Holiday::where('date', '2026-12-25')->firstOrFail();

        Livewire::test(Holidays::class)
            ->call('edit', $holiday->id)
            ->set('name', 'Christmas')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'name' => 'Christmas']);

        Livewire::test(Holidays::class)->call('delete', $holiday->id);

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    public function test_holiday_dates_must_be_unique(): void
    {
        $hr = User::factory()->hr()->create();
        Holiday::factory()->create(['date' => '2026-12-25']);

        $this->actingAs($hr);

        Livewire::test(Holidays::class)
            ->call('startCreate')
            ->set('date', '2026-12-25')
            ->set('name', 'Duplicate')
            ->call('save')
            ->assertHasErrors('date');
    }

    /**
     * The frontend listens for this event to scroll the form into view and
     * focus its first field — without it, editing a holiday further down a
     * long list leaves the form open off-screen above.
     */
    public function test_opening_the_holiday_form_dispatches_a_focus_event(): void
    {
        $hr = User::factory()->hr()->create();
        $holiday = Holiday::factory()->create();

        $this->actingAs($hr);

        Livewire::test(Holidays::class)->call('startCreate')->assertDispatched('form-opened');
        Livewire::test(Holidays::class)->call('edit', $holiday->id)->assertDispatched('form-opened');
    }

    public function test_holidays_can_be_searched_by_name(): void
    {
        $hr = User::factory()->hr()->create();
        Holiday::factory()->create(['name' => 'Independence Day', 'date' => '2026-08-14']);
        Holiday::factory()->create(['name' => 'Eid', 'date' => '2026-06-16']);

        $this->actingAs($hr);

        Livewire::test(Holidays::class)
            ->set('search', 'independence')
            ->assertSee('Independence Day')
            ->assertDontSee('Eid');
    }

    public function test_holidays_can_be_filtered_by_year(): void
    {
        $hr = User::factory()->hr()->create();
        Holiday::factory()->create(['name' => 'Last Year Holiday', 'date' => '2025-01-01']);
        Holiday::factory()->create(['name' => 'This Year Holiday', 'date' => '2026-01-01']);

        $this->actingAs($hr);

        Livewire::test(Holidays::class)
            ->set('yearFilter', 2025)
            ->assertSee('Last Year Holiday')
            ->assertDontSee('This Year Holiday');
    }
}
