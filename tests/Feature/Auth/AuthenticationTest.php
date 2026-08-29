<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    // -- Register ------------------------------------------------------

    public function test_registration_with_valid_data_returns_created_user_without_password(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.user.name', 'Jane Doe');
        $response->assertJsonPath('data.user.email', 'jane@example.com');
        $this->assertSame(
            ['id', 'name', 'email', 'created_at', 'updated_at'],
            array_keys($response->json('data.user'))
        );
        $this->assertNotEmpty($response->json('data.access_token'));

        $this->assertDatabaseHas('users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Someone Else',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email'], 'error');
    }

    public function test_registration_fails_with_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'email', 'password'], 'error');
    }

    public function test_registration_fails_with_a_password_shorter_than_the_minimum_length(): void
    {
        // RegisterRequest requires min:8 (plus `confirmed`) — nothing stricter
        // (no mixed-case/symbol rules), so a too-short password is the only
        // "weak password" case this validation rule actually rejects.
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'short1',
            'password_confirmation' => 'short1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password'], 'error');
        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }

    // -- Login -----------------------------------------------------------

    public function test_login_with_valid_credentials_returns_a_token(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.user.id', $user->id);
        $response->assertJsonPath('data.token_type', 'bearer');
        $this->assertNotEmpty($response->json('data.access_token'));
    }

    public function test_login_with_wrong_password_returns_unauthorized(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'not-the-right-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_with_unknown_email_returns_unauthorized_with_the_same_error_as_wrong_password(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.com',
            'password' => 'not-the-right-password',
        ]);

        $unknownEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever-password',
        ]);

        $wrongPassword->assertStatus(401);
        $unknownEmail->assertStatus(401);

        // The API must not leak whether an email is registered — both
        // failure modes should be indistinguishable from the response.
        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
    }

    public function test_login_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password'], 'error');
    }

    // -- Me ----------------------------------------------------------------

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Jane Doe']);

        $response = $this->getJson('/api/v1/auth/me', $this->authHeaders($user));

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $user->id);
        $response->assertJsonPath('data.name', 'Jane Doe');
    }

    public function test_me_without_a_token_returns_unauthorized(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_me_with_a_malformed_token_returns_unauthorized(): void
    {
        $response = $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer this-is-not-a-valid-jwt',
        ]);

        $response->assertStatus(401);
    }

    // -- Refresh -------------------------------------------------------

    public function test_refresh_with_a_valid_token_returns_a_new_token(): void
    {
        $user = User::factory()->create();
        $originalToken = JWTAuth::fromUser($user);

        $response = $this->postJson('/api/v1/auth/refresh', [], [
            'Authorization' => 'Bearer '.$originalToken,
        ]);

        $response->assertStatus(200);
        $newToken = $response->json('data.access_token');
        $this->assertNotEmpty($newToken);
        $this->assertNotSame($originalToken, $newToken);
    }

    public function test_refresh_with_an_invalid_token_returns_unauthorized(): void
    {
        // A merely time-expired token is intentionally still refreshable
        // under this app's JWT config (refresh_ttl is 14 days past the
        // 60-minute ttl) — that's the whole point of the refresh endpoint,
        // not a failure case. What genuinely can't be refreshed is a
        // malformed/invalid token, which is what this test covers.
        $response = $this->postJson('/api/v1/auth/refresh', [], [
            'Authorization' => 'Bearer this-is-not-a-valid-jwt',
        ]);

        $response->assertStatus(401);
    }

    // -- Logout --------------------------------------------------------

    public function test_logout_invalidates_the_token(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->postJson('/api/v1/auth/logout', [], $headers)->assertStatus(200);

        // The same token must not work again after logout.
        $this->getJson('/api/v1/auth/me', $headers)->assertStatus(401);
    }
}
