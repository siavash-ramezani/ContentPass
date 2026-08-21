<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    use ApiResponses;

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Hash::make($request->validated('password')),
        ]);

        SendWelcomeEmailJob::dispatch($user);

        $token = Auth::guard('api')->login($user);

        return $this->respondWithToken($user, $token, 'Registered successfully', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        if (! $token = Auth::guard('api')->attempt($credentials)) {
            return $this->error('Invalid credentials', 401);
        }

        return $this->respondWithToken(Auth::guard('api')->user(), $token, 'Logged in successfully');
    }

    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return $this->success(null, 'Logged out successfully');
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = Auth::guard('api')->refresh();
        } catch (JWTException) {
            return $this->error('Could not refresh token', 401);
        }

        return $this->respondWithToken(Auth::guard('api')->user(), $token, 'Token refreshed successfully');
    }

    public function me(): JsonResponse
    {
        return $this->success(new UserResource(Auth::guard('api')->user()));
    }

    private function respondWithToken(User $user, string $token, string $message, int $status = 200): JsonResponse
    {
        return $this->success([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::guard('api')->factory()->getTTL() * 60,
        ], $message, $status);
    }
}
