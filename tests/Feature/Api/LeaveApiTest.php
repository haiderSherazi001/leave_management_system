<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveApiTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('mobile-app-token')->plainTextToken];
    }

    public function test_types_excludes_inactive_leave_types(): void
    {
        $user = User::factory()->create();
        LeaveType::factory()->create(['name' => 'Annual Leave']);
        LeaveType::factory()->inactive()->create(['name' => 'Retired Leave']);

        $response = $this->withHeaders($this->authHeader($user))->getJson('/api/v1/leave/types')->assertOk();

        $response->assertJsonFragment(['name' => 'Annual Leave']);
        $response->assertJsonMissing(['name' => 'Retired Leave']);
    }

    public function test_balances_returns_the_current_years_allocations(): void
    {
        $user = User::factory()->create();
        $leaveType = LeaveType::factory()->create(['name' => 'Sick Leave']);

        LeaveBalance::factory()->create([
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 2,
        ]);

        $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/leave/balances')
            ->assertOk()
            ->assertJsonFragment(['leave_type_name' => 'Sick Leave', 'allocated_days' => 10, 'used_days' => 2]);
    }

    public function test_preview_total_days_matches_the_web_calculation(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/leave/preview-total-days?'.http_build_query([
                'start_date' => now()->addDays(5)->toDateString(),
                'end_date' => now()->addDays(5)->toDateString(),
                'is_half_day' => true,
            ]))
            ->assertOk()
            ->assertJson(['data' => ['total_days' => 0.5]]);
    }

    public function test_submitting_a_leave_request_within_balance_succeeds(): void
    {
        $user = User::factory()->create();
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/leave/requests', [
                'leave_type_id' => $leaveType->id,
                'start_date' => now()->addDays(5)->toDateString(),
                'end_date' => now()->addDays(6)->toDateString(),
                'reason' => 'Family trip',
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('leave_requests', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'status' => 'pending_manager',
        ]);
    }

    public function test_submitting_a_leave_request_exceeding_balance_is_a_conflict(): void
    {
        $user = User::factory()->create();
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 1,
            'used_days' => 0,
        ]);

        $this->withHeaders($this->authHeader($user))
            ->postJson('/api/v1/leave/requests', [
                'leave_type_id' => $leaveType->id,
                'start_date' => now()->addDays(5)->toDateString(),
                'end_date' => now()->addDays(9)->toDateString(),
                'reason' => 'Long trip',
            ])
            ->assertStatus(409);

        $this->assertDatabaseCount('leave_requests', 0);
    }

    public function test_history_returns_the_employees_full_request_history(): void
    {
        $user = User::factory()->create();
        $leaveType = LeaveType::factory()->create();

        LeaveBalance::factory()->create([
            'user_id' => $user->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
            'used_days' => 0,
        ]);

        $this->withHeaders($this->authHeader($user))->postJson('/api/v1/leave/requests', [
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
            'reason' => 'Doctor visit',
        ])->assertCreated();

        $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/leave/requests')
            ->assertOk()
            ->assertJsonFragment(['status' => 'pending_manager', 'decision_note' => null]);
    }

    public function test_upcoming_holidays_returns_only_future_holidays(): void
    {
        $user = User::factory()->create();
        Holiday::factory()->create(['date' => now()->addDays(4)->toDateString(), 'name' => 'Founders Day']);
        Holiday::factory()->create(['date' => now()->subMonths(2)->toDateString(), 'name' => 'Past Holiday']);

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/leave/holidays/upcoming')
            ->assertOk();

        $response->assertJsonFragment(['name' => 'Founders Day']);
        $response->assertJsonMissing(['name' => 'Past Holiday']);
    }

    /**
     * Regression test: an explicit ?limit=N always arrives as a string over
     * HTTP, unlike the `?? 5` fallback default (a literal int) the test
     * above exercises - HolidayService::upcoming(int $limit) is strictly
     * typed, so passing the validated string straight through used to throw
     * a TypeError the moment a real client (the mobile app) sent this
     * query param at all.
     */
    public function test_upcoming_holidays_accepts_an_explicit_limit(): void
    {
        $user = User::factory()->create();
        Holiday::factory()->count(3)->sequence(
            ['date' => now()->addDays(1)->toDateString(), 'name' => 'Holiday A'],
            ['date' => now()->addDays(2)->toDateString(), 'name' => 'Holiday B'],
            ['date' => now()->addDays(3)->toDateString(), 'name' => 'Holiday C'],
        )->create();

        $response = $this->withHeaders($this->authHeader($user))
            ->getJson('/api/v1/leave/holidays/upcoming?limit=2')
            ->assertOk();

        $response->assertJsonCount(2, 'data');
    }
}
