<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;

use function Sentry\configureScope;

class SentryContext
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! app()->bound('sentry')) {
            return $next($request);
        }

        configureScope(function (Scope $scope) use ($request): void {
            $scope->setUser([
                'id' => $request->user()?->id ?? 'guest',
                'email' => $request->user()?->email ?? 'guest@corex.dev',
                'ip_address' => $request->ip(),
            ]);

            $scope->setTag('request_id', $request->header('X-Request-ID', uniqid()));
            $scope->setTag('host', $request->getHost());
            $scope->setTag('method', $request->method());
            $scope->setTag('path', $request->path());

            $scope->setExtra('query_params', $request->query());
            $scope->setExtra('headers', collect($request->headers->all())
                ->except(['authorization', 'cookie', 'x-csrf-token'])
                ->toArray());
        });

        return $next($request);
    }
}
