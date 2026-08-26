<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public const RENEWAL_PERIOD_DAYS = 30;

    /**
     * Subscribe the user to the given plan. If the user already has an
     * active subscription, it is canceled first (an upgrade/downgrade).
     */
    public function subscribe(User $user, Plan $plan): Subscription
    {
        return DB::transaction(function () use ($user, $plan) {
            $current = $user->activeSubscription()->first();

            if ($current) {
                $current->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                ]);
            }

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'started_at' => now(),
                'renews_at' => now()->addDays(self::RENEWAL_PERIOD_DAYS),
            ]);

            $user->update(['plan_id' => $plan->id]);

            return $subscription->load('plan');
        });
    }

    /**
     * Cancel the user's active subscription and drop them back to the free
     * tier. Returns null if there was no active subscription to cancel.
     */
    public function cancelCurrent(User $user): ?Subscription
    {
        $subscription = $user->activeSubscription()->first();

        if (! $subscription) {
            return null;
        }

        return DB::transaction(function () use ($user, $subscription) {
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);

            $user->update(['plan_id' => null]);

            return $subscription->load('plan');
        });
    }

    public function currentFor(User $user): ?Subscription
    {
        return $user->activeSubscription()->with('plan')->first();
    }
}
