<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCorsHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->buildPreflightResponse($request);
        }

        $response = $next($request);
        $this->setCorsHeaders($request, $response);

        return $response;
    }

    private function buildPreflightResponse(Request $request): Response
    {
        $response = response('', 204);
        $this->setCorsHeaders($request, $response);

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Request-ID, X-CSRF-TOKEN, X-Signature, X-Timestamp, X-API-Key');
        $response->headers->set('Access-Control-Max-Age', '86400');

        return $response;
    }

    private function setCorsHeaders(Request $request, Response $response): void
    {
        $allowedOrigins = config('cors.allowed_origins', 'https://corex.dev,https://console.corex.dev');
        $origin = $request->header('Origin');

        if ($origin && $this->isOriginAllowed($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
        } elseif ($origin && in_array('*', explode(',', $allowedOrigins), true)) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        } else {
            $defaultOrigin = explode(',', $allowedOrigins)[0];
            $response->headers->set('Access-Control-Allow-Origin', trim($defaultOrigin));
        }

        $response->headers->set('Access-Control-Allow-Credentials', 'true');
    }

    private function isOriginAllowed(string $origin, string $allowedList): bool
    {
        $allowed = array_map('trim', explode(',', $allowedList));

        foreach ($allowed as $allowedOrigin) {
            if ($allowedOrigin === $origin || $allowedOrigin === '*') {
                return true;
            }
        }

        return false;
    }
}
