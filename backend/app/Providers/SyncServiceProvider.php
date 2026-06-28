<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\SyncContract;
use App\Events\Sync\ConflictDetected;
use App\Events\Sync\SyncCompleted;
use App\Events\Sync\SyncFailed;
use App\Events\Sync\SyncStarted;
use App\Models\SyncConflict;
use App\Models\SyncStatus;
use App\Services\Supabase\SupabaseService;
use App\Services\Supabase\SyncService;
use App\Services\Sync\ConflictResolver;
use App\Services\Sync\SnapshotManager;
use App\Services\Sync\SyncEngine;
use App\Services\Sync\SyncQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SyncQueue::class, function () {
            return new SyncQueue;
        });

        $this->app->singleton(ConflictResolver::class, function () {
            return new ConflictResolver;
        });

        $this->app->singleton(SnapshotManager::class, function () {
            return new SnapshotManager;
        });

        $this->app->singleton(SyncEngine::class, function ($app) {
            return new SyncEngine(
                $app->make(SyncQueue::class),
                $app->make(ConflictResolver::class),
                $app->make(SnapshotManager::class),
                $app->make(SupabaseService::class),
            );
        });

        $this->app->singleton(SyncContract::class, function ($app) {
            return new SyncService(
                $app->make(SyncEngine::class),
                $app->make(SyncQueue::class),
                $app->make(ConflictResolver::class),
                $app->make(SnapshotManager::class),
            );
        });

        $this->app->bind(SyncService::class, function ($app) {
            return $app->make(SyncContract::class);
        });

        // intentionally left blank - model map is read from config directly
    }

    public function boot(): void
    {
        Event::listen(function (SyncStarted $event) {
            SyncStatus::updateOrCreate(
                [
                    'table_name' => $event->table,
                ],
                [
                    'status' => 'syncing',
                    'version' => 0,
                ]
            );
        });

        Event::listen(function (SyncCompleted $event) {
            SyncStatus::where('table_name', $event->table)
                ->where('status', 'syncing')
                ->update([
                    'status' => 'synced',
                    'synced_at' => now(),
                ]);
        });

        Event::listen(function (SyncFailed $event) {
            SyncStatus::updateOrCreate(
                [
                    'table_name' => $event->table,
                    'record_id' => $event->recordId,
                ],
                [
                    'status' => 'failed',
                    'error_message' => $event->error,
                ]
            );
        });

        Event::listen(function (ConflictDetected $event) {
            SyncConflict::updateOrCreate(
                [
                    'table_name' => $event->table,
                    'record_id' => $event->recordId,
                    'status' => 'pending',
                ],
                [
                    'user_id' => $event->userId,
                    'local_version' => $event->localVersion,
                    'remote_version' => $event->remoteVersion,
                    'reason' => $event->reason,
                ]
            );
        });
    }
}
