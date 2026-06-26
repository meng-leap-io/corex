<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\CacheService;
use Illuminate\Support\ServiceProvider;

class OptimizationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CacheService::class);
    }

    public function boot(): void
    {
        //
    }
}
