<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_shows_personal_information(): void
    {
        $manager = User::factory()->manager()->create(['name' => 'Morgan Manager']);
        $department = Department::factory()->create(['name' => 'Engineering']);

        $employee = User::factory()->create([
            'name' => 'Jane Employee',
            'email' => 'jane@leavedesk.test',
            'department_id' => $department->id,
            'manager_id' => $manager->id,
            'joined_at' => '2026-01-15',
        ]);

        $html = $this->actingAs($employee)->get('/profile')->getContent();

        $this->assertStringContainsString('Jane Employee', $html);
        $this->assertStringContainsString('jane@leavedesk.test', $html);
        $this->assertStringContainsString('Engineering', $html);
        $this->assertStringContainsString('Morgan Manager', $html);
        $this->assertStringContainsString('Jan 15, 2026', $html);
    }

    public function test_profile_shows_a_dash_when_department_and_manager_are_not_set(): void
    {
        $employee = User::factory()->create(['department_id' => null, 'manager_id' => null]);

        $this->actingAs($employee)->get('/profile')->assertSee('—');
    }

    public function test_a_manager_with_direct_reports_sees_their_team(): void
    {
        $manager = User::factory()->manager()->create();
        User::factory()->create(['name' => 'Report One', 'manager_id' => $manager->id]);
        User::factory()->inactive()->create(['name' => 'Inactive Report', 'manager_id' => $manager->id]);

        $html = $this->actingAs($manager)->get('/profile')->getContent();

        $this->assertStringContainsString('My Team', $html);
        $this->assertStringContainsString('Report One', $html);
        $this->assertStringNotContainsString('Inactive Report', $html);
    }

    public function test_an_employee_with_no_direct_reports_does_not_see_a_team_section(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get('/profile')->assertDontSee('My Team');
    }

    public function test_profile_shows_leave_balances_for_an_employee(): void
    {
        $employee = User::factory()->create();
        $leaveType = LeaveType::factory()->create(['name' => 'Annual Leave']);

        LeaveBalance::factory()->create([
            'user_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 15,
            'carried_forward_days' => 2,
            'used_days' => 3,
        ]);

        $html = $this->actingAs($employee)->get('/profile')->getContent();

        $this->assertStringContainsString('Annual Leave', $html);
        $this->assertStringContainsString('15', $html);
    }

    public function test_profile_shows_leave_balances_for_hr_too(): void
    {
        $hr = User::factory()->hr()->create();
        $leaveType = LeaveType::factory()->create(['name' => 'Casual Leave']);

        LeaveBalance::factory()->create([
            'user_id' => $hr->id,
            'leave_type_id' => $leaveType->id,
            'year' => now()->year,
            'allocated_days' => 10,
        ]);

        $this->actingAs($hr)->get('/profile')->assertSee('Casual Leave');
    }

    /**
     * Regression guard: /profile used to be a plain Blade view behind a
     * plain controller route - the one other page (besides the old
     * /dashboard) where Livewire never had a reason to inject its script,
     * so Alpine (bundled inside it) never loaded, and the password form's
     * "Saved." fade-out message never actually worked. Converting this
     * page into a real Livewire component fixes that.
     */
    public function test_the_profile_page_actually_loads_livewires_script(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)->get('/profile')->getContent();

        $this->assertStringContainsString('wire:id', $html);
        $this->assertStringContainsString('livewire.js', $html);
    }
}
