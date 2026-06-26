<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Corex.dev API Routes
|--------------------------------------------------------------------------
|
| Stateless JSON API. Authentication via Sanctum tokens for user sessions
| and JWT middleware (JwtAuth) for inter-service machine-to-machine auth.
|
| Rate limiting:
|   throttle:auth  — 10 req/min (login, register, password reset)
|   throttle:api   — 120 req/min (general API)
|   throttle:ai    — 60 req/min (AI-specific endpoints)
|--------------------------------------------------------------------------
*/

// ── Health Check ───────────────────────────────────────────────────────────
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'laravel-api',
        'timestamp' => now()->toIso8601String(),
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env'),
    ]);
});

// ── Public Authentication (rate limited: 10 req/min per IP) ────────────────
Route::middleware('throttle:auth')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
});

// ── Sanctum Authenticated Routes ──────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/verify', [AuthController::class, 'verify']);

    Route::get('/user', function (Request $request) {
        return new \App\Http\Resources\UserResource($request->user());
    });

    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);

    Route::apiResource('projects', \App\Http\Controllers\Api\ProjectController::class)
        ->except(['create', 'edit']);

    Route::post('/projects/{project}/duplicate', [
        \App\Http\Controllers\Api\ProjectController::class, 'duplicate',
    ]);

    Route::get('/conversations', [
        \App\Http\Controllers\Api\ConversationController::class, 'index',
    ]);
    Route::post('/conversations', [
        \App\Http\Controllers\Api\ConversationController::class, 'store',
    ]);
    Route::get('/conversations/{conversation}', [
        \App\Http\Controllers\Api\ConversationController::class, 'show',
    ]);
    Route::delete('/conversations/{conversation}', [
        \App\Http\Controllers\Api\ConversationController::class, 'destroy',
    ]);

    // AI usage stats
    Route::get('/ai/usage', [\App\Http\Controllers\Api\AIController::class, 'usage']);
    Route::get('/ai/usage/daily', [\App\Http\Controllers\Api\AIController::class, 'dailyUsage']);

    // Rate limited admin routes
    Route::middleware('throttle:api')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'delete']);
    });
});

// ── JWT-authenticated routes (inter-service machine-to-machine) ──────────
Route::middleware(['jwt.auth', 'throttle:ai'])->prefix('v1')->group(function () {
    Route::post('/chat/completions', function (Request $request) {
        $body = $request->json()->all();
        $gatewayUrl = config('services.ai-gateway.url');
        $gatewayKey = config('services.ai-gateway.key');

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => "Bearer {$gatewayKey}",
            'Content-Type' => 'application/json',
        ])->post("{$gatewayUrl}/v1/chat/completions", $body);

        return response()->json(
            $response->json(),
            $response->status(),
        );
    });

    Route::post('/embeddings', function (Request $request) {
        $body = $request->json()->all();
        $gatewayUrl = config('services.ai-gateway.url');
        $gatewayKey = config('services.ai-gateway.key');

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => "Bearer {$gatewayKey}",
            'Content-Type' => 'application/json',
        ])->post("{$gatewayUrl}/v1/embeddings", $body);

        return response()->json(
            $response->json(),
            $response->status(),
        );
    });
});
