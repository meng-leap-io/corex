<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureLogger();

        Log::info('app_service_provider_booted', ['environment' => app()->environment()]);
    }

    private function configureLogger(): void
    {
        $logLevel = env('LOG_LEVEL', 'error');
        $channels = config('logging.channels', []);

        if (isset($channels['stack'])) {
            $channels['stack']['level'] = $logLevel;
            config(['logging.channels' => $channels]);
        }
    }
}
