<?php

declare(strict_types=1);

namespace App\Services\Webhook;

use App\Models\WebhookRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

class WebhookRouter
{
    private array $routes = [];

    private array $handlers = [];

    public function register(string $path, string $handlerClass, array $options = []): void
    {
        $this->routes[$path] = array_merge([
            'handler' => $handlerClass,
            'method' => 'POST',
            'verify_signature' => true,
            'rate_limit' => true,
            'rate_limit_per_minute' => 60,
            'middleware' => [],
        ], $options);
    }

    public function resolve(Request $request): ?string
    {
        $path = $request->path();
        $method = $request->method();

        if (isset($this->routes[$path])) {
            $route = $this->routes[$path];

            if ($route['method'] !== $method) {
                return null;
            }

            return $route['handler'];
        }

        foreach ($this->routes as $routePath => $config) {
            if ($this->pathMatches($routePath, $path)) {
                if ($config['method'] !== $method) {
                    continue;
                }

                return $config['handler'];
            }
        }

        return null;
    }

    public function getConfig(string $path): ?array
    {
        if (isset($this->routes[$path])) {
            return $this->routes[$path];
        }

        foreach ($this->routes as $routePath => $config) {
            if ($this->pathMatches($routePath, $path)) {
                return $config;
            }
        }

        return null;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function loadFromDatabase(): void
    {
        try {
            $dbRoutes = WebhookRoute::where('status', 'active')->get();

            foreach ($dbRoutes as $route) {
                $this->routes[$route->path] = [
                    'handler' => $route->handler,
                    'method' => $route->method,
                    'verify_signature' => $route->verify_signature,
                    'rate_limit' => $route->rate_limit,
                    'rate_limit_per_minute' => $route->rate_limit_per_minute,
                    'middleware' => $route->middleware ?? [],
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('webhook.route_load_failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function handler(string $class): mixed
    {
        if (! isset($this->handlers[$class])) {
            $this->handlers[$class] = App::make($class);
        }

        return $this->handlers[$class];
    }

    private function pathMatches(string $pattern, string $path): bool
    {
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace(['\\{id\\}', '\\{event\\}', '\\*'], ['([^/]+)', '([^/]+)', '.*?'], $pattern);

        return (bool) preg_match('#^'.$pattern.'$#', $path);
    }
}
