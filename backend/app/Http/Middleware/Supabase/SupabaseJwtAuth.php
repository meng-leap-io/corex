<?php

declare(strict_types=1);

namespace App\Http\Middleware\Supabase;

use App\Services\Supabase\SupabaseAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SupabaseJwtAuth
{
    public function __construct(
        private readonly SupabaseAuthService $authService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return response()->json([
                'message' => 'Authentication required.',
                'error' => 'missing_token',
            ], 401);
        }

        $user = $this->authService->verifySupabaseToken($token);

        if (! $user) {
            Log::warning('supabase.auth.invalid_token', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'message' => 'Invalid or expired token.',
                'error' => 'invalid_token',
            ], 401);
        }

        if ($user->trashed()) {
            return response()->json([
                'message' => 'Account has been deactivated.',
                'error' => 'account_deactivated',
            ], 403);
        }

        auth()->setUser($user);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return $request->input('token') ?? $request->input('access_token');
    }
}
