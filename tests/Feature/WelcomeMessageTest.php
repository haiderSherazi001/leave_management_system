<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WelcomeMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_employee_sees_the_welcome_message(): void
    {
        $employee = User::factory()->create();
        $this->actingAs($employee);

        Livewire::test(Dashboard::class)
            ->assertSet('showWelcome', true)
            ->assertSee('Welcome to LeaveDesk');
    }

    public function test_dismissing_the_welcome_message_marks_the_user_welcomed_and_it_does_not_reappear(): void
    {
        $employee = User::factory()->create();
        $this->actingAs($employee);

        Livewire::test(Dashboard::class)
            ->call('dismissWelcome')
            ->assertSet('showWelcome', false);

        $this->assertNotNull($employee->fresh()->welcomed_at);

        Livewire::test(Dashboard::class)->assertSet('showWelcome', false);
    }

    public function test_a_manager_sees_manager_specific_welcome_copy(): void
    {
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager);

        Livewire::test(Dashboard::class)->assertSee("you'll be notified here whenever someone on your team requests leave", false);
    }

    public function test_an_employee_does_not_see_manager_specific_welcome_copy(): void
    {
        $employee = User::factory()->create();
        $this->actingAs($employee);

        Livewire::test(Dashboard::class)->assertDontSee("you'll be notified here whenever someone on your team requests leave", false);
    }

    public function test_a_new_hr_account_sees_the_hr_welcome_message(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(AdminDashboard::class)
            ->assertSet('showWelcome', true)
            ->assertSee('Welcome to LeaveDesk');
    }

    public function test_dismissing_the_hr_welcome_message_marks_it_welcomed(): void
    {
        $hr = User::factory()->hr()->create();
        $this->actingAs($hr);

        Livewire::test(AdminDashboard::class)->call('dismissWelcome');

        $this->assertNotNull($hr->fresh()->welcomed_at);
    }

    public function test_a_user_who_already_dismissed_it_does_not_see_it_again_on_a_fresh_login(): void
    {
        $employee = User::factory()->create(['welcomed_at' => now()->subDay()]);
        $this->actingAs($employee);

        Livewire::test(Dashboard::class)->assertSet('showWelcome', false);
    }
}
