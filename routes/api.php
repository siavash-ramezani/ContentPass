<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', [HealthController::class, 'index']);

    Route::get('plans', [PlanController::class, 'index']);

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

        Route::middleware('auth:api')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('refresh', [AuthController::class, 'refresh']);
            Route::get('me', [AuthController::class, 'me']);
        });
    });

    Route::middleware('auth:api')->group(function () {
        Route::get('content', [ContentController::class, 'index']);

        Route::prefix('subscriptions')->group(function () {
            Route::post('/', [SubscriptionController::class, 'store']);
            Route::get('current', [SubscriptionController::class, 'current']);
            Route::delete('current', [SubscriptionController::class, 'destroy']);
        });
    });

    Route::prefix('admin')->middleware(['auth:api', 'role:admin'])->group(function () {
        Route::get('ping', [AdminController::class, 'ping']);
    });
});
