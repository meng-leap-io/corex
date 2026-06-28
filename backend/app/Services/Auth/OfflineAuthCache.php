<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Cache\RedisStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OfflineAuthCache
{
    private const CACHE_PREFIX = 'offline_auth_';

    private const TOKEN_PREFIX = 'offline_token_';

    public function __construct(
        private readonly int $cacheDurationDays = 30,
    ) {}

    public function cacheCredentials(User $user, string $accessToken, string $refreshToken, array $metadata = []): void
    {
        $data = array_merge([
            'id' => $user->id,
            'supabase_id' => $user->supabase_id,
            'email' => $user->email,
            'name' => $user->name,
            'avatar' => $user->avatar_url,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'plan' => $user->plan,
            'cached_at' => now()->toIso8601String(),
            'expires_at' => now()->addDays($this->cacheDurationDays)->toIso8601String(),
        ], $metadata);

        Cache::put(
            self::CACHE_PREFIX.$user->id,
            $data,
            now()->addDays($this->cacheDurationDays),
        );

        Cache::put(
            self::TOKEN_PREFIX.hash('sha256', $accessToken),
            $user->id,
            now()->addDays($this->cacheDurationDays),
        );

        Log::info('offline_auth.cached', ['user_id' => $user->id]);
    }

    public function getCachedUser(int|string $userId): ?array
    {
        return Cache::get(self::CACHE_PREFIX.$userId);
    }

    public function getCachedUserByToken(string $accessToken): ?array
    {
        $userId = Cache::get(self::TOKEN_PREFIX.hash('sha256', $accessToken));

        if (! $userId) {
            return null;
        }

        return $this->getCachedUser($userId);
    }

    public function hasCachedSession(int|string $userId): bool
    {
        return Cache::has(self::CACHE_PREFIX.$userId);
    }

    public function removeCachedSession(User $user): void
    {
        $cached = $this->getCachedUser($user->id);

        if ($cached && isset($cached['access_token'])) {
            Cache::forget(self::TOKEN_PREFIX.hash('sha256', $cached['access_token']));
        }

        Cache::forget(self::CACHE_PREFIX.$user->id);

        Log::info('offline_auth.cleared', ['user_id' => $user->id]);
    }

    public function authenticateOffline(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    public function isOfflineMode(): bool
    {
        try {
            $response = Http::timeout(3)
                ->head(rtrim(config('supabase.url'), '/').'/auth/v1/health');

            return $response->failed();
        } catch (\Throwable) {
            return true;
        }
    }

    public function getOfflineStatus(User $user): array
    {
        $cached = $this->getCachedUser($user->id);

        return [
            'is_offline' => $this->isOfflineMode(),
            'has_cached_session' => $cached !== null,
            'cached_at' => $cached['cached_at'] ?? null,
            'expires_at' => $cached['expires_at'] ?? null,
            'is_expired' => $cached ? now()->gt(parse($cached['expires_at'])) : true,
        ];
    }

    public function syncOfflineChanges(User $user): array
    {
        $offlineData = $this->getCachedUser($user->id);

        if (! $offlineData) {
            return ['synced' => false, 'reason' => 'No offline data'];
        }

        $updated = [];

        if ($offlineData['name'] !== $user->name) {
            $updated['name'] = $user->name;
        }

        if ($offlineData['plan'] !== $user->plan) {
            $updated['plan'] = $user->plan;
        }

        if (! empty($updated)) {
            $updated['synced_at'] = now()->toIso8601String();
            Cache::put(
                self::CACHE_PREFIX.$user->id,
                array_merge($offlineData, $updated),
                now()->addDays($this->cacheDurationDays),
            );

            Log::info('offline_auth.synced', [
                'user_id' => $user->id,
                'changes' => array_keys($updated),
            ]);
        }

        return [
            'synced' => true,
            'changes' => $updated,
        ];
    }

    public function generateOfflineToken(User $user): string
    {
        $token = Str::random(64);

        Cache::put(
            'offline_token_map_'.hash('sha256', $token),
            [
                'user_id' => $user->id,
                'created_at' => now()->toIso8601String(),
            ],
            now()->addDays($this->cacheDurationDays),
        );

        return $token;
    }

    public function validateOfflineToken(string $token): ?User
    {
        $data = Cache::get('offline_token_map_'.hash('sha256', $token));

        if (! $data) {
            return null;
        }

        return User::find($data['user_id']);
    }

    public function getCachedUsers(): array
    {
        $users = [];

        try {
            $store = Cache::getStore();

            if (method_exists($store, 'getPrefix')) {
                $prefix = $store->getPrefix().self::CACHE_PREFIX;

                if ($store instanceof RedisStore) {
                    $keys = $store->connection()->keys("{$prefix}*");

                    foreach ($keys as $key) {
                        $userId = str_replace($prefix, '', $key);
                        $data = $this->getCachedUser($userId);

                        if ($data) {
                            $users[] = $data;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('offline_auth.list_failed', ['error' => $e->getMessage()]);
        }

        return $users;
    }
}
