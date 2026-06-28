<?php

declare(strict_types=1);
use App\Services\Webhook\Handlers\GitHubHandler;
use App\Services\Webhook\Handlers\ResendHandler;
use App\Services\Webhook\Handlers\StripeHandler;

return [
    'signing' => [
        'default' => env('WEBHOOK_SECRET', env('APP_KEY')),
        'stripe' => env('STRIPE_WEBHOOK_SECRET'),
        'resend' => env('RESEND_WEBHOOK_SECRET'),
        'github' => env('GITHUB_WEBHOOK_SECRET'),
        'supabase' => env('SUPABASE_WEBHOOK_SECRET'),
    ],

    'retry' => [
        'max_attempts' => env('WEBHOOK_MAX_ATTEMPTS', 3),
        'backoff_base' => env('WEBHOOK_RETRY_BACKOFF', 10),
        'max_backoff' => env('WEBHOOK_RETRY_MAX_BACKOFF', 300),
    ],

    'rate_limiting' => [
        'enabled' => env('WEBHOOK_RATE_LIMIT_ENABLED', true),
        'default_per_minute' => env('WEBHOOK_RATE_LIMIT', 60),
        'stripe_per_minute' => env('STRIPE_RATE_LIMIT', 120),
        'resend_per_minute' => env('RESEND_RATE_LIMIT', 120),
    ],

    'cleanup' => [
        'retention_days' => env('WEBHOOK_RETENTION_DAYS', 30),
    ],

    'edge_functions' => [
        'base_url' => env('SUPABASE_URL').'/functions/v1',
        'timeout' => env('EDGE_FUNCTION_TIMEOUT', 30),
    ],

    'handlers' => [
        'stripe' => StripeHandler::class,
        'resend' => ResendHandler::class,
        'github' => GitHubHandler::class,
    ],
];
