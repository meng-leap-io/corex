<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Sentry\Event;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return [
    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'environment' => env('APP_ENV', 'production'),

    'release' => env('APP_VERSION', '1.0.0'),

    'sample_rate' => (float) env('SENTRY_SAMPLE_RATE', 1.0),

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 0.25),

    'profiles_sample_rate' => (float) env('SENTRY_PROFILES_SAMPLE_RATE', 0.1),

    'send_default_pii' => env('SENTRY_SEND_PII', false),

    'breadcrumbs' => [
        'sql_queries' => env('SENTRY_BREADCRUMBS_SQL', true),
        'sql_bindings' => env('SENTRY_BREADCRUMBS_SQL_BINDINGS', false),
        'queue_info' => env('SENTRY_BREADCRUMBS_QUEUE', true),
        'command_info' => env('SENTRY_BREADCRUMBS_COMMANDS', true),
        'http_client_requests' => env('SENTRY_BREADCRUMBS_HTTP', true),
        'logs' => env('SENTRY_BREADCRUMBS_LOGS', true),
        'cache' => env('SENTRY_BREADCRUMBS_CACHE', true),
        'redis' => env('SENTRY_BREADCRUMBS_REDIS', true),
    ],

    ' integrations' => [
        'lazy' => true,
    ],

    'ignore_exceptions' => [
        NotFoundHttpException::class,
        AccessDeniedHttpException::class,
        AuthenticationException::class,
        ValidationException::class,
        ModelNotFoundException::class,
    ],

    'tags' => [
        'service' => 'backend',
        'platform' => 'laravel',
    ],

    'before_send' => function (Event $event): ?Event {
        if (app()->environment('local')) {
            return null;
        }

        return $event;
    },
];
