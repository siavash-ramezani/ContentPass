<?php

namespace Tests\Feature\Api\V1;

use App\Models\Content;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class ContentIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    public function test_index_returns_only_published_content(): void
    {
        Content::factory()->count(2)->create();
        Content::factory()->unpublished()->create();
        Content::factory()->futureDated()->create();

        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/content', $this->authHeaders($user));

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.items'));
        $this->assertSame(2, $response->json('data.pagination.total'));
    }

    public function test_accessible_flag_is_true_for_free_content_and_false_for_content_above_users_plan_level(): void
    {
        $proPlan = Plan::factory()->create(['level' => 1]);

        $freeContent = Content::factory()->create(['required_plan_level' => 0]);
        $proContent = Content::factory()->create(['required_plan_level' => 1]);

        $freeUser = User::factory()->create(['plan_id' => null]);

        $items = collect(
            $this->getJson('/api/v1/content', $this->authHeaders($freeUser))->json('data.items')
        )->keyBy('id');

        $this->assertTrue($items[$freeContent->id]['accessible']);
        $this->assertFalse($items[$proContent->id]['accessible']);
    }

    public function test_accessible_flag_is_true_for_content_at_or_below_the_users_plan_level(): void
    {
        $proPlan = Plan::factory()->create(['level' => 1]);

        $freeContent = Content::factory()->create(['required_plan_level' => 0]);
        $proContent = Content::factory()->create(['required_plan_level' => 1]);

        $proUser = User::factory()->create(['plan_id' => $proPlan->id]);

        $items = collect(
            $this->getJson('/api/v1/content', $this->authHeaders($proUser))->json('data.items')
        )->keyBy('id');

        $this->assertTrue($items[$freeContent->id]['accessible']);
        $this->assertTrue($items[$proContent->id]['accessible']);
    }

    public function test_unpublished_and_future_dated_content_are_excluded(): void
    {
        $unpublished = Content::factory()->unpublished()->create();
        $future = Content::factory()->futureDated()->create();
        Content::factory()->create();

        $user = User::factory()->create();

        $ids = collect(
            $this->getJson('/api/v1/content', $this->authHeaders($user))->json('data.items')
        )->pluck('id');

        $this->assertFalse($ids->contains($unpublished->id));
        $this->assertFalse($ids->contains($future->id));
    }

    public function test_unauthenticated_request_gets_unauthorized(): void
    {
        $this->getJson('/api/v1/content')->assertStatus(401);
    }
}
