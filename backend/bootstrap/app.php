<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\JwtAuth;
use App\Http\Middleware\JwtValidation;
use App\Http\Middleware\SetCorsHeaders;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\BruteForceProtection;
use App\Http\Middleware\CheckAdminRole;
use App\Http\Middleware\EnsureSupabaseAuth;
use App\Http\Middleware\SetRlsContext;
use App\Http\Middleware\ValidateUserAgent;
use App\Http\Middleware\RequestSigning;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        App\Providers\OptimizationServiceProvider::class,
        App\Providers\SecurityServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(TrustProxies::class);
        $middleware->append(PreventRequestsDuringMaintenance::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(ValidateUserAgent::class);
        $middleware->append(\Illuminate\Foundation\Http\Middleware\TrimStrings::class);
        $middleware->append(\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class);
        $middleware->append(SetCorsHeaders::class);

        $middleware->alias([
            'jwt.auth' => JwtAuth::class,
            'jwt.validate' => JwtValidation::class,
            'brute_force' => BruteForceProtection::class,
            'request.sign' => RequestSigning::class,
            'nativephp' => \App\Http\Middleware\NativePHPSession::class,
            'auth.supabase' => \App\Http\Middleware\AuthenticateWithSupabase::class,
            'rls.context' => SetRlsContext::class,
            'auth.supabase.jwt' => EnsureSupabaseAuth::class,
            'admin' => CheckAdminRole::class,
        ]);

        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);

        $middleware->api(append: [
            BruteForceProtection::class,
            SetRlsContext::class,
        ]);

        $middleware->web(append: [
            SetRlsContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {
            if (app()->bound('log')) {
                app('log')->error('unhandled_exception', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        });

        $exceptions->shouldRenderJsonWhen(function (\Illuminate\Http\Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })
    ->create();
