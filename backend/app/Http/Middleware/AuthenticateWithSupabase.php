<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Auth\SupabaseSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWithSupabase
{
    public function __construct(
        private readonly SupabaseSessionService $sessionService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $this->refreshSessionActivity($request->user());

            return $next($request);
        }

        $session = $this->restoreFromToken($request);

        if (! $session) {
            $session = $this->sessionService->restoreFromRemember();
        }

        if (! $session || ! isset($session['user_id'])) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('auth.desktop.login');
        }

        $user = User::find($session['user_id']);

        if (! $user || $user->trashed()) {
            return redirect()->route('auth.desktop.login');
        }

        auth()->setUser($user);
        $this->refreshSessionActivity($user);

        return $next($request);
    }

    private function restoreFromToken(Request $request): ?array
    {
        $token = $request->bearerToken()
            ?? $request->input('token')
            ?? $request->cookie('auth_token');

        if (! $token) {
            return null;
        }

        $sessionId = $token;

        return $this->sessionService->getSession($sessionId);
    }

    private function refreshSessionActivity(User $user): void
    {
        try {
            $session = $this->sessionService->getCurrentSession($user);

            if ($session && isset($session['session_id'])) {
                $this->sessionService->touchSession($session['session_id']);
            }
        } catch (\Throwable $e) {
            Log::warning('auth.session_refresh_failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
