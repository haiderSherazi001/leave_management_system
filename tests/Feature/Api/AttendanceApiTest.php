<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttendanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Seeds a real office_location row (idempotent - only the first call per
     * test actually inserts) since check-in now requires one configured,
     * rather than falling back to an env default.
     *
     * @return array{0: float, 1: float}
     */
    private function officeCoordinates(): array
    {
        $lat = 31.411751;
        $lon = 73.117245;

        if (! DB::table('office_location')->exists()) {
            DB::table('office_location')->insert([
                'company_id' => Tenant::id(),
                'latitude' => $lat,
                'longitude' => $lon,
                'radius_meters' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [$lat, $lon];
    }

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile-app-token')->plainTextToken];
    }

    public function test_check_in_is_a_conflict_until_hr_has_configured_an_office_location(): void
    {
        $user = User::factory()->create();

        // Deliberately not calling officeCoordinates() - no office_location
        // row exists at all yet, simulating a fresh install.
        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/attendance/check-in', ['latitude' => 31.5204, 'longitude' => 74.3587])
            ->assertStatus(409)
            ->assertJson(['message' => 'Office location has not been configured yet. Please contact HR.']);
    }

    public function test_today_reflects_no_attendance_before_checking_in(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJson(['data' => ['record' => null, 'has_checked_in' => false, 'has_checked_out' => false]]);
    }

    public function test_check_in_at_the_office_succeeds(): void
    {
        $user = User::factory()->create();
        [$lat, $lon] = $this->officeCoordinates();

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/attendance/check-in', ['latitude' => $lat, 'longitude' => $lon])
            ->assertOk()
            ->assertJsonPath('data.status', 'present');

        $this->assertDatabaseHas('attendances', ['user_id' => $user->id, 'date' => now()->toDateString()]);
    }

    public function test_check_in_twice_the_same_day_is_a_conflict(): void
    {
        $user = User::factory()->create();
        [$lat, $lon] = $this->officeCoordinates();
        $headers = $this->authHeader($user);

        $this->withHeaders($headers)->postJson('/api/v1/attendance/check-in', ['latitude' => $lat, 'longitude' => $lon])->assertOk();

        $this->withHeaders($headers)
            ->postJson('/api/v1/attendance/check-in', ['latitude' => $lat, 'longitude' => $lon])
            ->assertStatus(409)
            ->assertJson(['message' => 'You have already checked in today.']);
    }

    public function test_check_in_far_from_the_office_is_unprocessable(): void
    {
        $user = User::factory()->create();
        [$officeLat, $officeLon] = $this->officeCoordinates();

        // Roughly 1km north of the office — well outside the default radius.
        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/attendance/check-in', ['latitude' => $officeLat + 0.009, 'longitude' => $officeLon])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('location');
    }

    public function test_check_out_before_checking_in_is_a_conflict(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/attendance/check-out')
            ->assertStatus(409)
            ->assertJson(['message' => 'You must check in before checking out.']);
    }

    public function test_check_out_after_checking_in_succeeds(): void
    {
        $user = User::factory()->create();
        [$lat, $lon] = $this->officeCoordinates();
        $headers = $this->authHeader($user);

        $this->withHeaders($headers)->postJson('/api/v1/attendance/check-in', ['latitude' => $lat, 'longitude' => $lon])->assertOk();

        $this->withHeaders($headers)
            ->postJson('/api/v1/attendance/check-out')
            ->assertOk();

        $this->withHeaders($headers)
            ->getJson('/api/v1/attendance/today')
            ->assertJson(['data' => ['has_checked_in' => true, 'has_checked_out' => true]]);
    }

    public function test_worked_minutes_reflects_time_so_far_then_the_final_total_after_checkout(): void
    {
        $user = User::factory()->create();
        [$lat, $lon] = $this->officeCoordinates();
        $headers = $this->authHeader($user);

        Carbon::setTestNow(Carbon::parse('2026-08-03 09:00:00'));
        $this->withHeaders($headers)->postJson('/api/v1/attendance/check-in', ['latitude' => $lat, 'longitude' => $lon])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-08-03 11:30:00'));
        $this->withHeaders($headers)
            ->getJson('/api/v1/attendance/today')
            ->assertOk()
            ->assertJsonPath('data.record.worked_minutes', 150);

        Carbon::setTestNow(Carbon::parse('2026-08-03 17:00:00'));
        $this->withHeaders($headers)
            ->postJson('/api/v1/attendance/check-out')
            ->assertOk()
            ->assertJsonPath('data.worked_minutes', 480);
    }

    public function test_history_respects_the_limit_parameter(): void
    {
        $user = User::factory()->create();

        [$lat, $lon] = $this->officeCoordinates();
        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/attendance/check-in', ['latitude' => $lat, 'longitude' => $lon])
            ->assertOk();

        $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/attendance/history?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
