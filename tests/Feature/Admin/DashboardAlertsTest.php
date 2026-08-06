<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Dashboard;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\OfficeLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_freshly_registered_hr_account_sees_all_four_setup_alerts(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(Dashboard::class)
            ->assertSee('No leave types configured yet')
            ->assertSee("Work schedule isn't set")
            ->assertSee("Office location isn't set")
            ->assertSee("You haven't invited anyone yet");
    }

    public function test_each_setup_alert_disappears_once_its_own_condition_is_met(): void
    {
        $hr = User::factory()->hr()->create();
        LeaveType::factory()->create();
        WorkSchedule::factory()->create();
        app(OfficeLocationService::class)->save(31.5, 74.3, 100, null);
        User::factory()->create();

        $this->actingAs($hr);

        Livewire::test(Dashboard::class)
            ->assertDontSee('No leave types configured yet')
            ->assertDontSee("Work schedule isn't set")
            ->assertDontSee("Office location isn't set")
            ->assertDontSee("You haven't invited anyone yet");
    }

    public function test_dismissing_one_alert_leaves_the_others_visible(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(Dashboard::class)
            ->call('dismissSetupAlert', 'leave_types')
            ->assertDontSee('No leave types configured yet')
            ->assertSee("Work schedule isn't set")
            ->assertSee("Office location isn't set")
            ->assertSee("You haven't invited anyone yet");
    }

    public function test_a_dismissed_alert_stays_dismissed_on_a_fresh_component_instance(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(Dashboard::class)->call('dismissSetupAlert', 'leave_types');

        Livewire::test(Dashboard::class)->assertDontSee('No leave types configured yet');
    }

    public function test_non_hr_users_never_see_setup_alerts(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get(route('dashboard'))->assertDontSee('No leave types configured yet');
    }

    public function test_stale_pending_hr_alert_appears_only_once_a_request_has_waited_over_three_days(): void
    {
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create();

        $fresh = LeaveRequest::factory()->pendingHr()->create(['user_id' => $employee->id]);

        $this->actingAs($hr);
        Livewire::test(Dashboard::class)->assertDontSee('waiting on your approval');

        $fresh->forceFill(['created_at' => now()->subDays(4)])->save();

        Livewire::test(Dashboard::class)->assertSee('waiting on your approval');
    }

    /**
     * Unlike the setup alerts, this one reflects live data - there is no
     * dismiss action for it at all, so it can never be permanently
     * silenced while a real backlog exists. Every "Dismiss" control on the
     * page belongs to a setup alert, so the count must equal the number of
     * still-outstanding setup alerts — not one more, which would mean the
     * stale alert had grown one too. Creating an HR account, an employee,
     * and a leave request (via its factory's nested LeaveType) already
     * resolves the "employees" and "leave_types" alerts, leaving exactly
     * "work_schedule" and "office_location" outstanding.
     */
    public function test_stale_pending_hr_alert_has_no_dismiss_control(): void
    {
        $hr = User::factory()->hr()->create();
        $employee = User::factory()->create();

        LeaveRequest::factory()->pendingHr()->create(['user_id' => $employee->id])
            ->forceFill(['created_at' => now()->subDays(5)])
            ->save();

        $html = $this->actingAs($hr)->get(route('admin.dashboard'))->getContent();

        $this->assertSame(2, substr_count($html, 'aria-label="Dismiss"'));
    }
}
