<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_view_the_generic_dashboard(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get(route('dashboard'))->assertOk();
    }

    public function test_manager_can_view_the_generic_dashboard(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get(route('dashboard'))->assertOk();
    }

    /**
     * HR has its own real dashboard with company KPIs - visiting the
     * generic one directly (bookmark, typed URL) should never show them a
     * second, unrelated page.
     */
    public function test_hr_visiting_the_generic_dashboard_is_redirected_to_the_hr_dashboard(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
    }

    /**
     * Regression guard: the generic dashboard used to be a plain Blade view
     * with no Livewire component on the page at all, so Livewire never had
     * a reason to inject its script tag - and since this app's only Alpine
     * instance ships bundled inside that script, Alpine (and every
     * Alpine-driven control on the page, including the header profile
     * dropdown) silently never loaded. Converting it into a real Livewire
     * component fixes this; this asserts the script tag is now present so
     * the bug can't quietly come back.
     */
    public function test_the_generic_dashboard_actually_loads_livewires_script(): void
    {
        $employee = User::factory()->create();

        $html = $this->actingAs($employee)->get(route('dashboard'))->getContent();

        $this->assertStringContainsString('wire:id', $html);
        $this->assertStringContainsString('livewire.js', $html);
    }
}
