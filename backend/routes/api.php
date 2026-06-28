<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        return new UserResource($request->user());
    });

    Route::get('/user/profile', [UserController::class, 'profile']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);

    Route::apiResource('projects', ProjectController::class)
        ->except(['create', 'edit']);

    Route::post('/projects/{project}/duplicate', [
        ProjectController::class, 'duplicate',
    ]);

    Route::get('/conversations', [
        ConversationController::class, 'index',
    ]);
    Route::post('/conversations', [
        ConversationController::class, 'store',
    ]);
    Route::get('/conversations/{conversation}', [
        ConversationController::class, 'show',
    ]);
    Route::delete('/conversations/{conversation}', [
        ConversationController::class, 'destroy',
    ]);

    // File storage
    Route::prefix('files')->group(function () {
        Route::get('/', [FileController::class, 'index']);
        Route::post('/', [FileController::class, 'store']);
        Route::get('/{file}', [FileController::class, 'show']);
        Route::delete('/{file}', [FileController::class, 'destroy']);
        Route::get('/{file}/download', [FileController::class, 'download']);
        Route::post('/{file}/share', [FileController::class, 'share']);
        Route::post('/avatar', [FileController::class, 'uploadAvatar']);
        Route::get('/buckets', [FileController::class, 'buckets']);
        Route::get('/remote', [FileController::class, 'listRemote']);
    });

    Route::get('/files/shared/{token}', [FileController::class, 'shared']);

    // AI usage stats
    Route::get('/ai/usage', [AIController::class, 'usage']);
    Route::get('/ai/usage/daily', [AIController::class, 'dailyUsage']);

    // Team and sharing endpoints
    Route::prefix('teams')->group(function () {
        Route::get('/', [TeamController::class, 'index']);
        Route::post('/', [TeamController::class, 'store']);
        Route::get('/{team}', [TeamController::class, 'show']);
        Route::put('/{team}', [TeamController::class, 'update']);
        Route::delete('/{team}', [TeamController::class, 'destroy']);
        Route::post('/{team}/members', [TeamController::class, 'addMember']);
        Route::delete('/{team}/members/{user}', [TeamController::class, 'removeMember']);
        Route::put('/{team}/members/{user}/role', [TeamController::class, 'updateMemberRole']);
        Route::post('/{team}/projects/{project}', [TeamController::class, 'addProject']);
        Route::delete('/{team}/projects/{project}', [TeamController::class, 'removeProject']);
    });

    // Project sharing
    Route::prefix('projects/{project}/sharing')->group(function () {
        Route::get('/', [ProjectController::class, 'sharingSettings']);
        Route::put('/', [ProjectController::class, 'updateSharing']);
        Route::post('/members', [ProjectController::class, 'addMember']);
        Route::delete('/members/{user}', [ProjectController::class, 'removeMember']);
        Route::put('/members/{user}/role', [ProjectController::class, 'updateMemberRole']);
        Route::post('/make-public', [ProjectController::class, 'makePublic']);
        Route::post('/make-private', [ProjectController::class, 'makePrivate']);
    });

    // Rate limited admin routes (admin role required)
    Route::middleware(['throttle:api', 'admin'])->group(function () {
        Route::get('/admin/users', [UserController::class, 'index']);
        Route::get('/admin/users/{user}', [UserController::class, 'show']);
        Route::put('/admin/users/{user}', [UserController::class, 'update']);
        Route::delete('/admin/users/{user}', [UserController::class, 'delete']);
        Route::get('/admin/stats', [AdminController::class, 'stats']);
        Route::get('/admin/usage', [AdminController::class, 'usage']);
        Route::get('/admin/health', [AdminController::class, 'health']);
        Route::get('/admin/analytics/events', [AdminController::class, 'analyticsEvents']);
        Route::get('/admin/analytics/features', [AdminController::class, 'featureUsage']);
        Route::get('/admin/analytics/snapshots', [AdminController::class, 'performanceSnapshots']);
        Route::get('/admin/analytics/page-views', [AdminController::class, 'pageViews']);
    });
});

// ── JWT-authenticated routes (inter-service machine-to-machine) ──────────
Route::middleware(['jwt.auth', 'throttle:ai'])->prefix('v1')->group(function () {
    Route::post('/chat/completions', function (Request $request) {
        $body = $request->json()->all();
        $gatewayUrl = config('services.ai-gateway.url');
        $gatewayKey = config('services.ai-gateway.key');

        $response = Http::withHeaders([
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

        $response = Http::withHeaders([
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
Route::post('/webhooks/stripe', [WebhookController::class, 'stripe'])
    ->name('webhooks.stripe');
Route::post('/webhooks/resend', [WebhookController::class, 'resend'])
    ->name('webhooks.resend');
Route::post('/webhooks/github', [WebhookController::class, 'github'])
    ->name('webhooks.github');
Route::post('/webhooks/supabase', [WebhookController::class, 'supabase'])
    ->name('webhooks.supabase');
Route::post('/webhooks/{provider}', [WebhookController::class, 'generic'])
    ->name('webhooks.generic');

// ── Edge Function invocation (authenticated) ─────────────────────────────
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/edge-functions/invoke', [WebhookController::class, 'invokeFunction'])
        ->name('edge-functions.invoke');
    Route::post('/webhooks/{logId}/retry', [WebhookController::class, 'retry'])
        ->name('webhooks.retry');
    Route::post('/webhooks/retry-all', [WebhookController::class, 'retryAll'])
        ->name('webhooks.retry-all');
    Route::get('/webhooks/stats', [WebhookController::class, 'stats'])
        ->name('webhooks.stats');
});
