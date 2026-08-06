<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_sees_the_landing_page_instead_of_being_redirected(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('LeaveDesk');
        $response->assertSee(route('register-company'), false);
        $response->assertSee(route('login'), false);
    }

    public function test_an_authenticated_employee_is_redirected_to_their_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_an_authenticated_hr_user_is_redirected_to_the_admin_dashboard(): void
    {
        $hr = User::factory()->hr()->create();

        $this->actingAs($hr)->get('/')->assertRedirect(route('admin.dashboard'));
    }
}
