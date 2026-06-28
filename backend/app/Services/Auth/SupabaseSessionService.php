<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupabaseSessionService
{
    private const REMEMBER_COOKIE = 'supabase_remember';

    private const SESSION_CACHE_PREFIX = 'supabase_session_';

    private const OFFLINE_CACHE_PREFIX = 'auth_offline_';

    public function __construct(
        private readonly int $rememberDurationDays = 30,
        private readonly int $sessionCacheMinutes = 60,
    ) {}

    public function createSession(User $user, string $accessToken, string $refreshToken, bool $remember = false): array
    {
        $sessionId = Str::uuid()->toString();

        $sessionData = [
            'session_id' => $sessionId,
            'user_id' => $user->id,
            'supabase_id' => $user->supabase_id,
            'email' => $user->email,
            'name' => $user->name,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'remember' => $remember,
            'created_at' => now()->toIso8601String(),
            'last_activity' => now()->toIso8601String(),
        ];

        Cache::put(
            self::SESSION_CACHE_PREFIX . $sessionId,
            $sessionData,
            now()->addMinutes($this->sessionCacheMinutes),
        );

        Cache::put(
            self::SESSION_CACHE_PREFIX . 'user_' . $user->id,
            $sessionId,
            now()->addMinutes($this->sessionCacheMinutes),
        );

        if ($remember) {
            $this->setRememberCookie($user, $refreshToken);
        }

        $this->cacheOfflineCredentials($user, $accessToken, $refreshToken);

        Log::info('session.created', [
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'remember' => $remember,
        ]);

        return $sessionData;
    }

    public function refreshSession(string $refreshToken): ?array
    {
        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'apikey' => config('supabase.key'),
                'Authorization' => 'Bearer ' . config('supabase.key'),
                'Content-Type' => 'application/json',
            ])->post(rtrim(config('supabase.url'), '/') . '/auth/v1/token?grant_type=refresh_token', [
                'refresh_token' => $refreshToken,
            ]);

            if ($response->failed()) {
                Log::warning('session.refresh_failed', ['status' => $response->status()]);
                return null;
            }

            $data = $response->json();
            $user = User::where('supabase_id', $data['user']['id'])->first();

            if (!$user) {
                return null;
            }

            return $this->createSession(
                $user,
                $data['access_token'],
                $data['refresh_token'],
                true,
            );

        } catch (\Throwable $e) {
            Log::error('session.refresh_error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getSession(string $sessionId): ?array
    {
        return Cache::get(self::SESSION_CACHE_PREFIX . $sessionId);
    }

    public function getCurrentSession(User $user): ?array
    {
        $sessionId = Cache::get(self::SESSION_CACHE_PREFIX . 'user_' . $user->id);

        if (!$sessionId) {
            return null;
        }

        return $this->getSession($sessionId);
    }

    public function destroySession(User $user, ?string $sessionId = null): void
    {
        $sid = $sessionId ?? Cache::get(self::SESSION_CACHE_PREFIX . 'user_' . $user->id);

        if ($sid) {
            Cache::forget(self::SESSION_CACHE_PREFIX . $sid);
        }

        Cache::forget(self::SESSION_CACHE_PREFIX . 'user_' . $user->id);
        Cache::forget(self::OFFLINE_CACHE_PREFIX . $user->id);

        Cookie::queue(Cookie::forget(self::REMEMBER_COOKIE));

        Log::info('session.destroyed', ['user_id' => $user->id]);
    }

    public function touchSession(string $sessionId): void
    {
        $session = Cache::get(self::SESSION_CACHE_PREFIX . $sessionId);

        if ($session) {
            $session['last_activity'] = now()->toIso8601String();
            Cache::put(
                self::SESSION_CACHE_PREFIX . $sessionId,
                $session,
                now()->addMinutes($this->sessionCacheMinutes),
            );
        }
    }

    public function restoreFromRemember(): ?array
    {
        $cookie = Cookie::get(self::REMEMBER_COOKIE);

        if (!$cookie) {
            return null;
        }

        try {
            $data = Crypt::decrypt($cookie);

            if (!isset($data['refresh_token']) || !isset($data['user_id'])) {
                return null;
            }

            $user = User::find($data['user_id']);

            if (!$user) {
                return null;
            }

            return $this->refreshSession($data['refresh_token']);

        } catch (\Throwable $e) {
            Log::warning('session.remember_cookie_invalid', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function isSessionExpired(array $session): bool
    {
        $lastActivity = $session['last_activity'] ?? $session['created_at'];
        $expiresAt = \Carbon\Carbon::parse($lastActivity)->addMinutes($this->sessionCacheMinutes);

        return now()->gt($expiresAt);
    }

    private function setRememberCookie(User $user, string $refreshToken): void
    {
        $data = Crypt::encrypt([
            'user_id' => $user->id,
            'refresh_token' => $refreshToken,
            'remember_me' => true,
        ]);

        Cookie::queue(
            Cookie::make(
                self::REMEMBER_COOKIE,
                $data,
                $this->rememberDurationDays * 1440,
                '/',
                null,
                true,
                true,
                false,
                'strict',
            ),
        );
    }

    private function cacheOfflineCredentials(User $user, string $accessToken, string $refreshToken): void
    {
        Cache::put(self::OFFLINE_CACHE_PREFIX . $user->id, [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'avatar' => $user->avatar_url,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'cached_at' => now()->toIso8601String(),
        ], now()->addDays($this->rememberDurationDays));
    }

    public function getOfflineCredentials(User $user): ?array
    {
        return Cache::get(self::OFFLINE_CACHE_PREFIX . $user->id);
    }

    public function hasOfflineSession(User $user): bool
    {
        return Cache::has(self::OFFLINE_CACHE_PREFIX . $user->id);
    }

    public function cleanupExpiredSessions(): int
    {
        $count = 0;

        foreach (Cache::get('session_keys', []) as $key) {
            if (str_starts_with($key, self::SESSION_CACHE_PREFIX)) {
                $session = Cache::get($key);

                if ($session && $this->isSessionExpired($session)) {
                    Cache::forget($key);
                    $count++;
                }
            }
        }

        return $count;
    }
}
