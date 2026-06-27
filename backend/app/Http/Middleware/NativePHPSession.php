<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NativePHPSession
{
    public function handle(Request $request, Closure $next): Response
    {
        // NativePHP routes use a different session strategy:
        // token-based via Sanctum, no cookies needed
        config()->set('session.driver', 'array');

        return $next($request);
    }
}
