<?php

namespace Tests\Feature\Api\V1;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function authHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    public function test_plans_index_works_without_auth(): void
    {
        Plan::factory()->create(['name' => 'Free', 'level' => 0]);
        Plan::factory()->create(['name' => 'Pro', 'level' => 1]);

        $response = $this->getJson('/api/v1/plans');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_subscribing_creates_an_active_subscription_and_updates_user_plan_id(): void
    {
        $plan = Plan::factory()->create(['level' => 1]);
        $user = User::factory()->create(['plan_id' => null]);

        $response = $this->postJson('/api/v1/subscriptions', ['plan_id' => $plan->id], $this->authHeaders($user));

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.plan.id', $plan->id);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->assertSame($plan->id, $user->refresh()->plan_id);
    }

    public function test_subscribing_while_already_subscribed_cancels_the_old_subscription_and_creates_a_new_one(): void
    {
        $oldPlan = Plan::factory()->create(['level' => 1]);
        $newPlan = Plan::factory()->create(['level' => 2]);
        $user = User::factory()->create(['plan_id' => $oldPlan->id]);

        $oldSubscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $oldPlan->id,
            'status' => 'active',
            'started_at' => now()->subDays(10),
            'renews_at' => now()->addDays(20),
        ]);

        $response = $this->postJson('/api/v1/subscriptions', ['plan_id' => $newPlan->id], $this->authHeaders($user));

        $response->assertStatus(201);
        $response->assertJsonPath('data.plan.id', $newPlan->id);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $oldSubscription->id,
            'status' => 'canceled',
        ]);
        $this->assertNotNull($oldSubscription->refresh()->canceled_at);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $newPlan->id,
            'status' => 'active',
        ]);

        $this->assertSame($newPlan->id, $user->refresh()->plan_id);
    }

    public function test_subscribing_with_an_invalid_plan_id_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/subscriptions', ['plan_id' => 999999], $this->authHeaders($user));

        $response->assertStatus(422);
    }

    public function test_canceling_the_active_subscription_sets_status_canceled_and_clears_plan_id(): void
    {
        $plan = Plan::factory()->create(['level' => 1]);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now(),
            'renews_at' => now()->addDays(30),
        ]);

        $response = $this->deleteJson('/api/v1/subscriptions/current', [], $this->authHeaders($user));

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'canceled');

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'status' => 'canceled',
        ]);
        $this->assertNotNull($subscription->refresh()->canceled_at);
        $this->assertNull($user->refresh()->plan_id);
    }

    public function test_canceling_with_no_active_subscription_returns_404(): void
    {
        $user = User::factory()->create();

        $response = $this->deleteJson('/api/v1/subscriptions/current', [], $this->authHeaders($user));

        $response->assertStatus(404);
    }

    public function test_get_current_returns_null_when_the_user_has_no_active_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/v1/subscriptions/current', $this->authHeaders($user));

        $response->assertStatus(200);
        $this->assertNull($response->json('data'));
    }

    public function test_get_current_returns_the_active_subscription_with_plan_details(): void
    {
        $plan = Plan::factory()->create(['level' => 1]);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => now(),
            'renews_at' => now()->addDays(30),
        ]);

        $response = $this->getJson('/api/v1/subscriptions/current', $this->authHeaders($user));

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'active');
        $response->assertJsonPath('data.plan.id', $plan->id);
    }
}
