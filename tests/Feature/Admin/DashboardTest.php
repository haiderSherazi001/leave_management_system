<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Dashboard;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Livewire::test() skips full-page layout resolution, so a real HTTP
     * hit is needed to catch a missing/mismatched layout (bit this project
     * before on other pages).
     */
    public function test_hr_can_access_the_dashboard_over_http(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_non_hr_users_cannot_access_the_dashboard(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();

        foreach ([$employee, $manager] as $user) {
            $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        }
    }

    /**
     * For HR, every "Dashboard" nav entry (brand logo, top-level link, and
     * the Admin dropdown which used to have its own separate copy) must
     * point at the real HR dashboard — the generic placeholder URL should
     * no longer appear anywhere in their nav.
     */
    public function test_hr_nav_consolidates_dashboard_to_a_single_destination(): void
    {
        $hr = User::factory()->hr()->create();

        $html = $this->actingAs($hr)->get(route('admin.employees'))->getContent();

        $this->assertStringContainsString(route('admin.dashboard'), $html);
        $this->assertStringNotContainsString(route('dashboard'), $html);
    }

    public function test_employee_nav_dashboard_link_still_points_at_the_generic_dashboard(): void
    {
        $employee = User::factory()->create();

        $html = $this->actingAs($employee)->get(route('leave.apply'))->getContent();

        $this->assertStringContainsString(route('dashboard'), $html);
        $this->assertStringNotContainsString(route('admin.dashboard'), $html);
    }

    public function test_dashboard_counts_are_scoped_to_active_users_and_company_wide(): void
    {
        $today = now()->toDateString();

        // Present and late — mutually exclusive counts.
        Attendance::factory()->create(['status' => 'present', 'date' => $today]);
        Attendance::factory()->create(['status' => 'present', 'date' => $today]);
        Attendance::factory()->create(['status' => 'late', 'date' => $today]);

        // An inactive user's check-in must not count toward "company-wide health".
        $inactiveUser = User::factory()->inactive()->create();
        Attendance::factory()->create(['user_id' => $inactiveUser->id, 'status' => 'present', 'date' => $today]);

        // Approved leave covering today.
        $leaveType = LeaveType::factory()->create();
        LeaveRequest::factory()->approved()->create([
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
        ]);

        // Pending requests under two different managers, one at each
        // approval stage — the count must be company-wide, not scoped to
        // any single manager's team, and must include both stages.
        $managerA = User::factory()->manager()->create();
        $managerB = User::factory()->manager()->create();
        LeaveRequest::factory()->create([
            'leave_type_id' => $leaveType->id,
            'user_id' => User::factory()->create(['manager_id' => $managerA->id])->id,
            'status' => 'pending_manager',
        ]);
        LeaveRequest::factory()->pendingHr()->create([
            'leave_type_id' => $leaveType->id,
            'user_id' => User::factory()->create(['manager_id' => $managerB->id])->id,
        ]);

        // A rejected request and an approved-but-past request must not be counted anywhere.
        LeaveRequest::factory()->rejected()->create(['leave_type_id' => $leaveType->id]);
        LeaveRequest::factory()->approved()->create([
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->subDays(9)->toDateString(),
        ]);

        $stats = $this->app->make(DashboardService::class)->attendanceOverview();

        $this->assertSame(2, $stats['presentToday']);
        $this->assertSame(1, $stats['lateToday']);
        $this->assertSame(1, $stats['onLeaveToday']);
        $this->assertSame(1, $stats['pendingManager']);
        $this->assertSame(1, $stats['pendingHr']);

        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(Dashboard::class)
            ->assertSee('Present Today')
            ->assertSee('2')
            ->assertSee('Late Check-ins Today')
            ->assertSee('On Leave Today')
            ->assertSee('Awaiting Manager')
            ->assertSee('Awaiting HR');
    }
}
