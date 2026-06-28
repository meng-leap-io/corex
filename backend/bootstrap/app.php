<?php

use App\Http\Middleware\AuthenticateWithSupabase;
use App\Http\Middleware\BruteForceProtection;
use App\Http\Middleware\CheckAdminRole;
use App\Http\Middleware\EnsureSupabaseAuth;
use App\Http\Middleware\JwtAuth;
use App\Http\Middleware\JwtValidation;
use App\Http\Middleware\NativePHPSession;
use App\Http\Middleware\PreventRequestsDuringMaintenance;
use App\Http\Middleware\RequestSigning;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetCorsHeaders;
use App\Http\Middleware\SetRlsContext;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\ValidateUserAgent;
use App\Providers\OptimizationServiceProvider;
use App\Providers\RouteServiceProvider;
use App\Providers\SecurityServiceProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        OptimizationServiceProvider::class,
        SecurityServiceProvider::class,
        RouteServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(TrustProxies::class);
        $middleware->append(PreventRequestsDuringMaintenance::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->append(ValidateUserAgent::class);
        $middleware->append(TrimStrings::class);
        $middleware->append(ConvertEmptyStringsToNull::class);
        $middleware->append(SetCorsHeaders::class);

        $middleware->alias([
            'jwt.auth' => JwtAuth::class,
            'jwt.validate' => JwtValidation::class,
            'brute_force' => BruteForceProtection::class,
            'request.sign' => RequestSigning::class,
            'nativephp' => NativePHPSession::class,
            'auth.supabase' => AuthenticateWithSupabase::class,
            'rls.context' => SetRlsContext::class,
            'auth.supabase.jwt' => EnsureSupabaseAuth::class,
            'admin' => CheckAdminRole::class,
        ]);

        $middleware->api(prepend: [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            SubstituteBindings::class,
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

        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })
    ->create();
