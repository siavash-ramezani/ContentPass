<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\StoreSubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly SubscriptionService $subscriptions)
    {
        //
    }

    /**
     * Subscribe the authenticated user to a plan.
     *
     * MOCK BILLING: there is no real payment gateway here. This endpoint
     * simulates the *outcome* of a successful payment (e.g. a Stripe
     * checkout completing) — it does not charge anyone. A real integration
     * would create the Subscription from a webhook/callback after the
     * payment provider confirms payment, rather than directly from this
     * request. See the README's "Subscriptions" section for where that
     * would plug in.
     */
    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        $plan = Plan::findOrFail($request->validated('plan_id'));

        $subscription = $this->subscriptions->subscribe(Auth::guard('api')->user(), $plan);

        return $this->success(new SubscriptionResource($subscription), 'Subscribed successfully', 201);
    }

    public function current(): JsonResponse
    {
        $subscription = $this->subscriptions->currentFor(Auth::guard('api')->user());

        return $this->success($subscription ? new SubscriptionResource($subscription) : null);
    }

    public function destroy(): JsonResponse
    {
        $subscription = $this->subscriptions->cancelCurrent(Auth::guard('api')->user());

        if (! $subscription) {
            return $this->error('No active subscription to cancel.', 404, 'not_found');
        }

        return $this->success(new SubscriptionResource($subscription), 'Subscription canceled successfully');
    }
}
