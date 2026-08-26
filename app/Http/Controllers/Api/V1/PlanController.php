<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    use ApiResponses;

    /**
     * Public, unauthenticated list of plans — browsable before signup.
     */
    public function index(): JsonResponse
    {
        $plans = Plan::orderBy('level')->get();

        return $this->success(PlanResource::collection($plans));
    }
}
