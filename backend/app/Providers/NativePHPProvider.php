<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class NativePHPProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            $this->loadRoutesFrom(base_path('routes/desktop.php'));
        }

        $this->bootOfflineDetection();
        $this->bootAutoSave();
        $this->bootThemeSync();
    }

    protected function bootOfflineDetection(): void
    {
        $this->app->booted(function () {
            $this->app->make('cache')->remember('_native_online', 30, function () {
                return $this->checkConnectivity();
            });
        });
    }

    protected function bootAutoSave(): void
    {
        if (! cache()->has('autosave_interval')) {
            cache()->forever('autosave_interval', 2000);
        }
    }

    protected function bootThemeSync(): void
    {
        if (! cache()->has('theme')) {
            cache()->forever('theme', 'dark');
        }
    }

    protected function checkConnectivity(): bool
    {
        $hosts = ['api.corex.dev', 'github.com', 'google.com'];
        foreach ($hosts as $host) {
            try {
                $connection = @fsockopen($host, 443, $errno, $errstr, 2);
                if (is_resource($connection)) {
                    fclose($connection);

                    return true;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return false;
    }
}
