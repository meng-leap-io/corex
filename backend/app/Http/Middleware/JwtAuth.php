<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class JwtAuth
{
    private const ALLOWED_ALGORITHMS = ['HS256', 'RS256'];

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return response()->json([
                'message' => 'Authentication required.',
                'code' => 'MISSING_TOKEN',
            ], 401);
        }

        try {
            $payload = $this->decodeToken($token);

            $request->merge([
                'jwt_user_id' => $payload->sub ?? null,
                'jwt_scopes' => $payload->scopes ?? [],
                'jwt_provider' => $payload->provider ?? 'local',
            ]);

            $this->setUserResolver($request, $payload->sub ?? null);

            return $next($request);
        } catch (ExpiredException $e) {
            Log::warning('jwt.expired', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Token has expired. Please refresh your token.',
                'code' => 'TOKEN_EXPIRED',
            ], 401);
        } catch (SignatureInvalidException $e) {
            Log::warning('jwt.invalid_signature', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Invalid token signature.',
                'code' => 'INVALID_SIGNATURE',
            ], 401);
        } catch (\UnexpectedValueException $e) {
            Log::warning('jwt.malformed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Malformed token.',
                'code' => 'MALFORMED_TOKEN',
            ], 401);
        } catch (\Exception $e) {
            Log::error('jwt.validation_failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => 'Authentication failed.',
                'code' => 'AUTH_FAILED',
            ], 401);
        }
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        if ($request->has('api_token')) {
            return $request->input('api_token');
        }

        return $request->cookie('jwt_token');
    }

    private function decodeToken(string $token): object
    {
        $algorithm = config('jwt.algorithm', 'HS256');

        if (! in_array($algorithm, self::ALLOWED_ALGORITHMS, true)) {
            throw new \RuntimeException("Unsupported JWT algorithm: {$algorithm}");
        }

        $key = $algorithm === 'RS256'
            ? config('jwt.public_key')
            : config('jwt.secret');

        if (empty($key)) {
            throw new \RuntimeException('JWT key is not configured. Check JWT_SECRET or JWT_PUBLIC_KEY.');
        }

        return JWT::decode($token, new Key($key, $algorithm));
    }

    private function setUserResolver(Request $request, ?string $userId): void
    {
        if (! $userId) {
            return;
        }

        $request->setUserResolver(function () use ($userId) {
            $modelClass = config('auth.providers.users.model');

            if (! $modelClass || ! class_exists($modelClass)) {
                return null;
            }

            return $modelClass::find($userId);
        });
    }
}
