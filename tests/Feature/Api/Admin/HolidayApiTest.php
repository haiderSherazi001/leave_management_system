<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Admin;

use App\Models\Holiday;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirrors the holiday-related coverage already established in
 * tests/Feature/Admin/WorkScheduleHolidayTest.php for the Livewire admin screen.
 */
class HolidayApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile-app-token')->plainTextToken];
    }

    public function test_non_hr_cannot_access_any_holiday_admin_endpoint(): void
    {
        $employee = User::factory()->create();

        $this->withHeaders($this->authHeader($employee))->getJson('/api/v1/admin/holidays')->assertForbidden();
        $this->withHeaders($this->authHeader($employee))->postJson('/api/v1/admin/holidays', [])->assertForbidden();
    }

    public function test_index_returns_holidays_ordered_by_date(): void
    {
        $hr = User::factory()->hr()->create();
        Holiday::factory()->create(['date' => '2026-12-25', 'name' => 'Christmas']);
        Holiday::factory()->create(['date' => '2026-01-01', 'name' => 'New Year']);

        $response = $this->withHeaders($this->authHeader($hr))
            ->getJson('/api/v1/admin/holidays')
            ->assertOk();

        $this->assertSame(['New Year', 'Christmas'], array_column($response->json('data'), 'name'));
    }

    public function test_hr_can_create_a_holiday(): void
    {
        $hr = User::factory()->hr()->create();

        $response = $this->withHeaders($this->authHeader($hr))
            ->postJson('/api/v1/admin/holidays', [
                'date' => '2026-11-01',
                'name' => 'Founders Day',
            ])
            ->assertOk();

        $this->assertDatabaseHas('holidays', ['id' => $response->json('data.id'), 'date' => '2026-11-01', 'name' => 'Founders Day']);
    }

    public function test_duplicate_date_is_rejected(): void
    {
        $hr = User::factory()->hr()->create();
        Holiday::factory()->create(['date' => '2026-11-01']);

        $this->withHeaders($this->authHeader($hr))
            ->postJson('/api/v1/admin/holidays', [
                'date' => '2026-11-01',
                'name' => 'Duplicate',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');
    }

    public function test_hr_can_edit_a_holiday(): void
    {
        $hr = User::factory()->hr()->create();
        $holiday = Holiday::factory()->create(['name' => 'Old Name']);

        $this->withHeaders($this->authHeader($hr))
            ->putJson("/api/v1/admin/holidays/{$holiday->id}", [
                'date' => $holiday->date,
                'name' => 'New Name',
            ])
            ->assertOk();

        $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'name' => 'New Name']);
    }

    public function test_editing_a_holiday_can_keep_its_own_date(): void
    {
        $hr = User::factory()->hr()->create();
        $holiday = Holiday::factory()->create(['date' => '2026-11-01']);

        $this->withHeaders($this->authHeader($hr))
            ->putJson("/api/v1/admin/holidays/{$holiday->id}", [
                'date' => '2026-11-01',
                'name' => 'Renamed',
            ])
            ->assertOk();

        $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'date' => '2026-11-01', 'name' => 'Renamed']);
    }

    public function test_hr_can_delete_a_holiday(): void
    {
        $hr = User::factory()->hr()->create();
        $holiday = Holiday::factory()->create();

        $this->withHeaders($this->authHeader($hr))
            ->deleteJson("/api/v1/admin/holidays/{$holiday->id}")
            ->assertOk();

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }
}
