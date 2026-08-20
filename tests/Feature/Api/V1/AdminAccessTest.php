<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_role_gets_forbidden_from_admin_ping(): void
    {
        $user = User::factory()->create();
        $token = JWTAuth::fromUser($user);

        $this->getJson('/api/v1/admin/ping', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(403)
            ->assertJson([
                'error' => 'forbidden',
                'message' => 'You do not have permission to access this resource.',
            ]);
    }

    public function test_admin_role_gets_ok_from_admin_ping(): void
    {
        $admin = User::factory()->admin()->create();
        $token = JWTAuth::fromUser($admin);

        $this->getJson('/api/v1/admin/ping', ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200)
            ->assertJson(['status' => 'ok', 'role' => 'admin']);
    }

    public function test_unauthenticated_request_gets_unauthorized(): void
    {
        $this->getJson('/api/v1/admin/ping')
            ->assertStatus(401);
    }
}
