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

    // Supabase Auth routes
    Route::post('/auth/supabase/register', [AuthController::class, 'supabaseRegister']);
    Route::post('/auth/supabase/login', [AuthController::class, 'supabaseLogin']);
    Route::get('/auth/supabase/oauth/{provider}', [AuthController::class, 'supabaseOAuth']);
    Route::post('/auth/supabase/callback', [AuthController::class, 'supabaseCallback']);
    Route::post('/auth/supabase/refresh', [AuthController::class, 'supabaseRefresh']);
});

// ── Sanctum Authenticated Routes ──────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/verify', [AuthController::class, 'verify']);
    Route::post('/auth/supabase/logout', [AuthController::class, 'supabaseLogout']);

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

    // File storage
    Route::prefix('files')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\FileController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\FileController::class, 'store']);
        Route::get('/{file}', [\App\Http\Controllers\Api\FileController::class, 'show']);
        Route::delete('/{file}', [\App\Http\Controllers\Api\FileController::class, 'destroy']);
        Route::get('/{file}/download', [\App\Http\Controllers\Api\FileController::class, 'download']);
        Route::post('/{file}/share', [\App\Http\Controllers\Api\FileController::class, 'share']);
        Route::post('/avatar', [\App\Http\Controllers\Api\FileController::class, 'uploadAvatar']);
        Route::get('/buckets', [\App\Http\Controllers\Api\FileController::class, 'buckets']);
        Route::get('/remote', [\App\Http\Controllers\Api\FileController::class, 'listRemote']);
    });

    Route::get('/files/shared/{token}', [\App\Http\Controllers\Api\FileController::class, 'shared']);

    // AI usage stats
    Route::get('/ai/usage', [\App\Http\Controllers\Api\AIController::class, 'usage']);
    Route::get('/ai/usage/daily', [\App\Http\Controllers\Api\AIController::class, 'dailyUsage']);

    // Team and sharing endpoints
    Route::prefix('teams')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\TeamController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\Api\TeamController::class, 'store']);
        Route::get('/{team}', [\App\Http\Controllers\Api\TeamController::class, 'show']);
        Route::put('/{team}', [\App\Http\Controllers\Api\TeamController::class, 'update']);
        Route::delete('/{team}', [\App\Http\Controllers\Api\TeamController::class, 'destroy']);
        Route::post('/{team}/members', [\App\Http\Controllers\Api\TeamController::class, 'addMember']);
        Route::delete('/{team}/members/{user}', [\App\Http\Controllers\Api\TeamController::class, 'removeMember']);
        Route::put('/{team}/members/{user}/role', [\App\Http\Controllers\Api\TeamController::class, 'updateMemberRole']);
        Route::post('/{team}/projects/{project}', [\App\Http\Controllers\Api\TeamController::class, 'addProject']);
        Route::delete('/{team}/projects/{project}', [\App\Http\Controllers\Api\TeamController::class, 'removeProject']);
    });

    // Project sharing
    Route::prefix('projects/{project}/sharing')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ProjectController::class, 'sharingSettings']);
        Route::put('/', [\App\Http\Controllers\Api\ProjectController::class, 'updateSharing']);
        Route::post('/members', [\App\Http\Controllers\Api\ProjectController::class, 'addMember']);
        Route::delete('/members/{user}', [\App\Http\Controllers\Api\ProjectController::class, 'removeMember']);
        Route::put('/members/{user}/role', [\App\Http\Controllers\Api\ProjectController::class, 'updateMemberRole']);
        Route::post('/make-public', [\App\Http\Controllers\Api\ProjectController::class, 'makePublic']);
        Route::post('/make-private', [\App\Http\Controllers\Api\ProjectController::class, 'makePrivate']);
    });

    // Rate limited admin routes (admin role required)
    Route::middleware(['throttle:api', 'admin'])->group(function () {
        Route::get('/admin/users', [UserController::class, 'index']);
        Route::get('/admin/users/{user}', [UserController::class, 'show']);
        Route::put('/admin/users/{user}', [UserController::class, 'update']);
        Route::delete('/admin/users/{user}', [UserController::class, 'delete']);
        Route::get('/admin/stats', [\App\Http\Controllers\Api\AdminController::class, 'stats']);
        Route::get('/admin/usage', [\App\Http\Controllers\Api\AdminController::class, 'usage']);
        Route::get('/admin/health', [\App\Http\Controllers\Api\AdminController::class, 'health']);
        Route::get('/admin/analytics/events', [\App\Http\Controllers\Api\AdminController::class, 'analyticsEvents']);
        Route::get('/admin/analytics/features', [\App\Http\Controllers\Api\AdminController::class, 'featureUsage']);
        Route::get('/admin/analytics/snapshots', [\App\Http\Controllers\Api\AdminController::class, 'performanceSnapshots']);
        Route::get('/admin/analytics/page-views', [\App\Http\Controllers\Api\AdminController::class, 'pageViews']);
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

// ── Webhook Endpoints (no CSRF, external services POST here) ────────────
Route::post('/webhooks/stripe', [\App\Http\Controllers\Api\WebhookController::class, 'stripe'])
    ->name('webhooks.stripe');
Route::post('/webhooks/resend', [\App\Http\Controllers\Api\WebhookController::class, 'resend'])
    ->name('webhooks.resend');
Route::post('/webhooks/github', [\App\Http\Controllers\Api\WebhookController::class, 'github'])
    ->name('webhooks.github');
Route::post('/webhooks/supabase', [\App\Http\Controllers\Api\WebhookController::class, 'supabase'])
    ->name('webhooks.supabase');
Route::post('/webhooks/{provider}', [\App\Http\Controllers\Api\WebhookController::class, 'generic'])
    ->name('webhooks.generic');

// ── Edge Function invocation (authenticated) ─────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/edge-functions/invoke', [\App\Http\Controllers\Api\WebhookController::class, 'invokeFunction'])
        ->name('edge-functions.invoke');
    Route::post('/webhooks/{logId}/retry', [\App\Http\Controllers\Api\WebhookController::class, 'retry'])
        ->name('webhooks.retry');
    Route::post('/webhooks/retry-all', [\App\Http\Controllers\Api\WebhookController::class, 'retryAll'])
        ->name('webhooks.retry-all');
    Route::get('/webhooks/stats', [\App\Http\Controllers\Api\WebhookController::class, 'stats'])
        ->name('webhooks.stats');
});
