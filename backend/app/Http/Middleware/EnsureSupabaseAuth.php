<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Supabase\SupabaseService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureSupabaseAuth
{
    public function __construct(
        private readonly SupabaseService $supabase,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $supabaseToken = $request->bearerToken()
            ?? $request->input('supabase_token')
            ?? $request->cookie('sb-access-token');

        if (!$supabaseToken) {
            if ($request->user()) {
                return $next($request);
            }

            return $this->unauthorized($request);
        }

        $user = $this->resolveUserFromToken($supabaseToken);

        if (!$user) {
            try {
                $response = $this->supabase->post('/auth/v1/user', [], [
                    'headers' => ['Authorization' => "Bearer {$supabaseToken}"],
                ]);

                if ($response->successful()) {
                    $supabaseUser = $response->json();

                    $localUser = User::where('supabase_id', $supabaseUser['id'])->first();

                    if (!$localUser) {
                        $localUser = User::where('email', $supabaseUser['email'])->first();

                        if ($localUser) {
                            $localUser->update(['supabase_id' => $supabaseUser['id']]);
                        } else {
                            return $this->unauthorized($request, 'Supabase user not linked to local account');
                        }
                    }

                    $user = $localUser;
                    auth()->setUser($user);
                }
            } catch (\Throwable $e) {
                Log::error('supabase.auth.verify_failed', [
                    'error' => $e->getMessage(),
                ]);

                return $this->unauthorized($request, 'Failed to verify Supabase token');
            }
        }

        if (!$user) {
            return $this->unauthorized($request);
        }

        auth()->setUser($user);

        return $next($request);
    }

    private function resolveUserFromToken(string $token): ?User
    {
        $user = User::where('remember_token', $token)->first();

        if ($user) {
            return $user;
        }

        try {
            $decoded = json_decode(base64_decode(explode('.', $token)[1] ?? ''), true);

            if (isset($decoded['sub'])) {
                $user = User::where('supabase_id', $decoded['sub'])->first();

                if ($user) {
                    return $user;
                }

                $user = User::where('email', $decoded['email'] ?? '')->first();

                if ($user) {
                    $user->update(['supabase_id' => $decoded['sub']]);

                    return $user;
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function unauthorized(Request $request, string $message = 'Unauthenticated'): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->guest(route('auth.desktop.login'));
    }
}
