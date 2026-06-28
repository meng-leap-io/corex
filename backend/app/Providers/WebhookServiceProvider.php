<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Webhook\WebhookRouter;
use App\Services\Webhook\WebhookService;
use App\Services\Webhook\WebhookSignature;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class WebhookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/webhooks.php',
            'webhooks',
        );

        $this->app->singleton(WebhookSignature::class, function () {
            return WebhookSignature::fromConfig('default');
        });

        $this->app->singleton(WebhookRouter::class, function (Application $app) {
            $router = new WebhookRouter();

            $router->register('webhooks/stripe', config('webhooks.handlers.stripe'), [
                'verify_signature' => true,
                'rate_limit' => true,
                'rate_limit_per_minute' => 120,
            ]);

            $router->register('webhooks/resend', config('webhooks.handlers.resend'), [
                'verify_signature' => true,
                'rate_limit' => true,
            ]);

            $router->register('webhooks/github', config('webhooks.handlers.github'), [
                'verify_signature' => true,
                'rate_limit' => true,
            ]);

            $router->register('webhooks/supabase', 'App\\Services\\Webhook\\Handlers\\SupabaseHandler', [
                'verify_signature' => true,
                'rate_limit' => true,
            ]);

            return $router;
        });

        $this->app->singleton(WebhookService::class, function (Application $app) {
            return new WebhookService(
                $app->make(WebhookRouter::class),
                $app->make(WebhookSignature::class),
                $app->make(\App\Services\Supabase\SupabaseService::class),
            );
        });
    }

    public function boot(): void
    {
        $this->app->make(WebhookRouter::class)->loadFromDatabase();
    }
}
