<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Analytics\AnalyticsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TrackPageView
{
    protected const array EXCLUDED_PATHS = [
        '_debugbar',
        '_ignition',
        'telescope',
        'livewire/message',
        'broadcasting/auth',
        'sanctum/csrf-cookie',
        'horizon',
    ];

    protected const array EXCLUDED_PREFIXES = [
        '/api/',
        '/_debugbar',
        '/_ignition',
        '/telescope',
        '/livewire/message',
        '/broadcasting',
        '/sanctum',
        '/horizon',
    ];

    public function __construct(
        private readonly AnalyticsService $analytics,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        $startQueries = count(DB::getQueryLog());

        $response = $next($request);

        $durationMs = (microtime(true) - $startTime) * 1000;
        $memoryBytes = memory_get_usage() - $startMemory;

        if (! $request->ajax() && ! $request->expectsJson()) {
            $this->analytics->recordPageView(
                request: $request,
                durationMs: $durationMs,
                statusCode: $response->getStatusCode(),
                memoryBytes: $memoryBytes,
            );
        }

        return $response;
    }

    protected function shouldSkip(Request $request): bool
    {
        $path = $request->path();

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($path, ltrim($prefix, '/'))) {
                return true;
            }
        }

        return in_array($path, self::EXCLUDED_PATHS, true);
    }
}
