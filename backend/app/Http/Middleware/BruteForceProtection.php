<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BruteForceProtection
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    private const BLOCK_DURATION_MINUTES = 15;

    private const BLOCK_THRESHOLD = 3;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->buildKey($request);
        $blockKey = $key.':blocked';

        if (Cache::get($blockKey, false)) {
            Log::warning('security.brute_force_blocked', [
                'key' => $key,
                'ip' => $request->ip(),
                'route' => $request->path(),
            ]);

            return response()->json([
                'message' => 'Too many attempts. Please try again later.',
                'code' => 'RATE_LIMITED',
                'retry_after' => Cache::ttl($blockKey),
            ], 429);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 401 || $response->getStatusCode() === 429) {
            $attempts = Cache::increment($key, 1);

            if ($attempts === 1) {
                Cache::expire($key, self::DECAY_SECONDS);
            }

            if ($attempts >= self::MAX_ATTEMPTS * self::BLOCK_THRESHOLD) {
                Cache::put($blockKey, true, now()->addMinutes(self::BLOCK_DURATION_MINUTES));
                Cache::forget($key);

                Log::warning('security.brute_force_block_triggered', [
                    'key' => $key,
                    'ip' => $request->ip(),
                    'attempts' => $attempts,
                    'block_duration' => self::BLOCK_DURATION_MINUTES,
                ]);
            }
        }

        if ($response->getStatusCode() < 400) {
            Cache::forget($key);
            Cache::forget($blockKey);
        }

        return $response;
    }

    private function buildKey(Request $request): string
    {
        $identifier = $request->input('email')
            ? md5(strtolower($request->input('email')))
            : $request->ip();

        return 'bf:'.$request->path().':'.$identifier;
    }
}
