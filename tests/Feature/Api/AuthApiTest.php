<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_valid_credentials_returns_a_token_and_user_payload(): void
    {
        $user = User::factory()->create(['email' => 'employee@leavedesk.test', 'password' => 'password123']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'employee@leavedesk.test',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure([
            'data' => ['token', 'token_type', 'user' => ['id', 'name', 'email', 'role', 'role_label', 'department_id', 'is_active']],
        ]);

        $this->assertSame('Bearer', $response->json('data.token_type'));
        $this->assertSame($user->id, $response->json('data.user.id'));
    }

    public function test_login_with_wrong_password_is_unprocessable(): void
    {
        User::factory()->create(['email' => 'employee@leavedesk.test', 'password' => 'password123']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'employee@leavedesk.test',
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_login_for_an_inactive_user_is_unprocessable(): void
    {
        User::factory()->inactive()->create(['email' => 'inactive@leavedesk.test', 'password' => 'password123']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@leavedesk.test',
            'password' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_logout_revokes_only_the_current_device_token(): void
    {
        $user = User::factory()->create();
        $deviceA = $user->createToken('device-a');
        $deviceB = $user->createToken('device-b');

        $this->withHeader('Authorization', "Bearer {$deviceA->plainTextToken}")
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // Asserted directly against the database rather than with a second
        // authenticated HTTP call using the (now revoked) token: Sanctum's
        // guard caches its resolved user for the lifetime of a single test
        // method's container, so a second raw-bearer-token request in the
        // same test can return a stale cached "still authenticated" result
        // even after the token row is gone — a test-only artifact that
        // never happens in real usage, where every request is a fresh
        // process. Checking the token table directly sidesteps it.
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $deviceA->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $deviceB->accessToken->id]);
    }

    public function test_me_without_a_token_is_unauthorized(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_me_with_a_valid_token_returns_the_current_user(): void
    {
        $manager = User::factory()->manager()->create();
        $token = $manager->createToken('mobile-app-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonFragment(['role' => 'manager', 'role_label' => 'Manager']);
    }
}
