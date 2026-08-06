<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_screen_is_reachable_when_no_hr_account_exists_yet(): void
    {
        $this->get('/setup')->assertOk();
    }

    public function test_setup_screen_redirects_to_login_once_an_hr_account_exists(): void
    {
        User::factory()->hr()->create();

        $this->get('/setup')->assertRedirect(route('login'));
    }

    public function test_root_url_sends_a_fresh_install_to_setup_instead_of_login(): void
    {
        $this->get('/')->assertRedirect(route('setup'));
    }

    public function test_root_url_sends_an_already_set_up_install_to_login(): void
    {
        User::factory()->hr()->create();

        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_submitting_creates_the_first_hr_account_and_logs_them_in(): void
    {
        $response = $this->post('/setup', [
            'name' => 'Founding HR',
            'email' => 'hr@newcompany.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('admin.dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'hr@newcompany.test',
            'role' => 'hr',
            'is_active' => true,
        ]);
    }

    public function test_a_second_submission_is_blocked_once_an_hr_account_already_exists(): void
    {
        User::factory()->hr()->create();

        $response = $this->post('/setup', [
            'name' => 'Second Admin',
            'email' => 'second-hr@newcompany.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'second-hr@newcompany.test']);
    }

    public function test_password_confirmation_must_match(): void
    {
        $this->post('/setup', [
            'name' => 'Founding HR',
            'email' => 'hr@newcompany.test',
            'password' => 'password123',
            'password_confirmation' => 'does-not-match',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'hr@newcompany.test']);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@newcompany.test']);

        $this->post('/setup', [
            'name' => 'Founding HR',
            'email' => 'taken@newcompany.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');
    }
}
