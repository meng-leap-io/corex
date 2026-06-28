<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestSigning
{
    private const HEADER_TIMESTAMP = 'X-Timestamp';

    private const HEADER_SIGNATURE = 'X-Signature';

    private const MAX_CLOCK_SKEW = 300;

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $signature = $request->header(self::HEADER_SIGNATURE);
        $timestamp = $request->header(self::HEADER_TIMESTAMP);

        if (! $signature || ! $timestamp) {
            Log::warning('security.missing_request_signature', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'message' => 'Missing request signature.',
                'code' => 'MISSING_SIGNATURE',
            ], 401);
        }

        if (! $this->isTimestampValid($timestamp)) {
            Log::warning('security.stale_request_signature', [
                'ip' => $request->ip(),
                'timestamp' => $timestamp,
            ]);

            return response()->json([
                'message' => 'Stale request signature.',
                'code' => 'STALE_SIGNATURE',
            ], 401);
        }

        if (! $this->isSignatureValid($request, $signature)) {
            Log::warning('security.invalid_request_signature', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'message' => 'Invalid request signature.',
                'code' => 'INVALID_SIGNATURE',
            ], 401);
        }

        return $next($request);
    }

    private function shouldSkip(Request $request): bool
    {
        $skipPaths = [
            'api/health',
            'api/auth/login',
            'api/auth/register',
            'api/auth/forgot-password',
            'api/auth/reset-password',
        ];

        foreach ($skipPaths as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }

    private function isTimestampValid(string $timestamp): bool
    {
        $time = (int) $timestamp;
        $now = time();

        return abs($now - $time) <= self::MAX_CLOCK_SKEW;
    }

    private function isSignatureValid(Request $request, string $signature): bool
    {
        $secret = config('app.key');
        if (! $secret) {
            return false;
        }

        $payload = $this->buildPayload($request);
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function buildPayload(Request $request): string
    {
        $method = $request->method();
        $path = $request->path();
        $timestamp = $request->header(self::HEADER_TIMESTAMP, '0');
        $body = $request->getContent();

        return "{$method}\n{$path}\n{$timestamp}\n{$body}";
    }
}
