<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\PerformanceService;
use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AnalyticsService::class, function () {
            return new AnalyticsService();
        });

        $this->app->singleton(PerformanceService::class, function () {
            return new PerformanceService();
        });
    }

    public function boot(): void
    {
        //
    }
}
