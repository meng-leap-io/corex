<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class JwtValidation
{
    private const ALLOWED_ALGORITHMS = ['RS256', 'HS256'];

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json(['message' => 'Missing authentication token.'], 401);
        }

        try {
            $payload = $this->decodeToken($token);
            $request->merge(['jwt_payload' => $payload]);
            $request->setUserResolver(function () use ($payload) {
                $model = config('auth.providers.users.model');
                return $model::find($payload->sub ?? null);
            });
        } catch (\Firebase\JWT\ExpiredException $e) {
            Log::warning('jwt_expired', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Token has expired.'], 401);
        } catch (\Firebase\JWT\SignatureInvalidException $e) {
            Log::warning('jwt_invalid_signature', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Invalid token signature.'], 401);
        } catch (\Exception $e) {
            Log::error('jwt_validation_error', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Invalid authentication token.'], 401);
        }

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        if ($request->has('token')) {
            return $request->input('token');
        }

        return $request->cookie('jwt_token');
    }

    private function decodeToken(string $token): object
    {
        $algorithm = config('jwt.algorithm', 'HS256');
        $secret = $algorithm === 'RS256'
            ? config('jwt.public_key')
            : config('jwt.secret');

        if (empty($secret)) {
            throw new \RuntimeException('JWT secret or public key is not configured.');
        }

        return JWT::decode($token, new Key($secret, $algorithm));
    }
}
