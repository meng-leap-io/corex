<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\SupabaseRlsCommand;
use App\Console\Commands\SupabaseSyncCommand;
use App\Console\Commands\SupabaseSyncWorkCommand;
use App\Contracts\SupabaseAuthContract;
use App\Contracts\SupabaseStorageContract;
use App\Contracts\SyncContract;
use App\Services\Supabase\RlsContextService;
use App\Services\Supabase\Storage\SupabaseFilesystemAdapter;
use App\Services\Supabase\SupabaseAuthService;
use App\Services\Supabase\SupabaseService;
use App\Services\Supabase\SupabaseStorageService;
use App\Services\Supabase\SyncService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class SupabaseServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/supabase.php',
            'supabase',
        );

        $this->app->singleton(SupabaseService::class, function () {
            return new SupabaseService;
        });

        $this->app->singleton(RlsContextService::class, function () {
            return new RlsContextService;
        });

        $this->app->alias(RlsContextService::class, 'supabase.rls');

        $this->app->singleton(SupabaseAuthContract::class, function ($app) {
            return new SupabaseAuthService(
                $app->make(SupabaseService::class),
            );
        });

        $this->app->singleton(SupabaseStorageContract::class, function ($app) {
            return new SupabaseStorageService(
                $app->make(SupabaseService::class),
            );
        });

        $this->app->singleton(SyncContract::class, function ($app) {
            return new SyncService(
                $app->make(SupabaseService::class),
            );
        });

        $this->app->alias(SupabaseAuthContract::class, 'supabase.auth');
        $this->app->alias(SupabaseStorageContract::class, 'supabase.storage');
        $this->app->alias(SyncContract::class, 'supabase.sync');

        $this->registerCommands();
    }

    public function boot(): void
    {
        $this->ensureSyncTableExists();

        $this->registerFilesystemDriver();

        Log::info('supabase.provider.booted', [
            'url' => config('supabase.url'),
        ]);
    }

    private function registerFilesystemDriver(): void
    {
        $this->app->afterResolving('filesystem', function ($filesystem) {
            $filesystem->extend('supabase', function ($app, $config) {
                $storage = $app->make(SupabaseStorageContract::class);

                return new FilesystemAdapter(
                    new SupabaseFilesystemAdapter(
                        $storage,
                        $config['bucket'] ?? config('supabase.storage.bucket', 'app-files'),
                    ),
                    $app->make(Filesystem::class),
                    $config,
                );
            });
        });
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                SupabaseSyncCommand::class,
                SupabaseSyncWorkCommand::class,
                SupabaseRlsCommand::class,
            ]);
        }
    }

    private function ensureSyncTableExists(): void
    {
        try {
            $connection = DB::connection('sqlite');
            $schema = $connection->getSchemaBuilder();
            $tableName = config('supabase.sync.table_sync_tracking', 'sync_tracker');

            if (! $schema->hasTable($tableName)) {
                $schema->create($tableName, function ($table) {
                    $table->id();
                    $table->string('table_name');
                    $table->string('record_id');
                    $table->string('sync_status')->default('pending');
                    $table->string('sync_action')->default('upsert');
                    $table->timestamp('synced_at')->nullable();
                    $table->timestamps();

                    $table->unique(['table_name', 'record_id']);
                    $table->index('sync_status');
                    $table->index('synced_at');
                });

                Log::info('supabase.sync_tracker_table_created', ['table' => $tableName]);
            }
        } catch (\Throwable $e) {
            Log::warning('supabase.sync_tracker_table_check_failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
