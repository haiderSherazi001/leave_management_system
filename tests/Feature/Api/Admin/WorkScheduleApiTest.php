<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mirrors the work-schedule-related coverage already established in
 * tests/Feature/Admin/WorkScheduleHolidayTest.php for the Livewire admin
 * screen. There is only ever one schedule (a singleton row), so this is a
 * GET/PUT pair rather than the usual index/store/update/{id} shape.
 */
class WorkScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile-app-token')->plainTextToken];
    }

    public function test_non_hr_cannot_access_the_work_schedule_admin_endpoint(): void
    {
        $employee = User::factory()->create();

        $this->withHeaders($this->authHeader($employee))->getJson('/api/v1/admin/work-schedule')->assertForbidden();
        $this->withHeaders($this->authHeader($employee))->putJson('/api/v1/admin/work-schedule', [])->assertForbidden();
    }

    public function test_show_returns_defaults_when_no_schedule_exists_yet(): void
    {
        $hr = User::factory()->hr()->create();

        $response = $this->withHeaders($this->authHeader($hr))
            ->getJson('/api/v1/admin/work-schedule')
            ->assertOk();

        $response->assertJson(['data' => [
            'working_days' => [1, 2, 3, 4, 5],
            'start_time' => '09:00',
            'end_time' => '17:00',
            'grace_minutes' => 0,
        ]]);
    }

    public function test_hr_can_save_the_work_schedule(): void
    {
        $hr = User::factory()->hr()->create();

        $this->withHeaders($this->authHeader($hr))
            ->putJson('/api/v1/admin/work-schedule', [
                'working_days' => [1, 2, 3, 4],
                'start_time' => '08:30',
                'end_time' => '16:30',
                'grace_minutes' => 15,
            ])
            ->assertOk();

        $this->assertDatabaseHas('work_schedule', [
            'start_time' => '08:30:00',
            'end_time' => '16:30:00',
            'grace_minutes' => 15,
        ]);
        $this->assertSame([1, 2, 3, 4], json_decode(DB::table('work_schedule')->value('working_days'), true));
    }

    public function test_saving_again_updates_the_same_row_instead_of_inserting_a_new_one(): void
    {
        $hr = User::factory()->hr()->create();

        $this->withHeaders($this->authHeader($hr))->putJson('/api/v1/admin/work-schedule', [
            'working_days' => [1, 2, 3, 4, 5],
            'start_time' => '09:00',
            'end_time' => '17:00',
            'grace_minutes' => 0,
        ])->assertOk();

        $this->withHeaders($this->authHeader($hr))->putJson('/api/v1/admin/work-schedule', [
            'working_days' => [1, 2, 3, 4, 5, 6],
            'start_time' => '10:00',
            'end_time' => '18:00',
            'grace_minutes' => 5,
        ])->assertOk();

        $this->assertSame(1, DB::table('work_schedule')->count());
        $this->assertDatabaseHas('work_schedule', ['start_time' => '10:00:00', 'grace_minutes' => 5]);
    }

    public function test_end_time_must_be_after_start_time(): void
    {
        $hr = User::factory()->hr()->create();

        $this->withHeaders($this->authHeader($hr))
            ->putJson('/api/v1/admin/work-schedule', [
                'working_days' => [1, 2, 3, 4, 5],
                'start_time' => '17:00',
                'end_time' => '09:00',
                'grace_minutes' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_time');
    }

    public function test_at_least_one_working_day_is_required(): void
    {
        $hr = User::factory()->hr()->create();

        $this->withHeaders($this->authHeader($hr))
            ->putJson('/api/v1/admin/work-schedule', [
                'working_days' => [],
                'start_time' => '09:00',
                'end_time' => '17:00',
                'grace_minutes' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('working_days');
    }
}
