<?php

declare(strict_types=1);

namespace App\Services\Supabase;

use App\Contracts\SyncContract;
use App\Services\Sync\ConflictResolver;
use App\Services\Sync\SnapshotManager;
use App\Services\Sync\SyncEngine;
use App\Services\Sync\SyncQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SyncService implements SyncContract
{
    private SyncEngine $engine;

    private SyncQueue $queue;

    private ConflictResolver $conflictResolver;

    private SnapshotManager $snapshotManager;

    public function __construct(
        SyncEngine $engine,
        SyncQueue $queue,
        ConflictResolver $conflictResolver,
        SnapshotManager $snapshotManager,
    ) {
        $this->engine = $engine;
        $this->queue = $queue;
        $this->conflictResolver = $conflictResolver;
        $this->snapshotManager = $snapshotManager;
    }

    public function syncModel(Model $model, string $action = 'upsert'): bool
    {
        return $this->engine->syncModel($model, $action);
    }

    public function syncPending(): int
    {
        return $this->engine->syncPending();
    }

    public function pushLocalChanges(?string $table = null): Collection
    {
        return $this->engine->pushLocalChanges($table);
    }

    public function pullRemoteChanges(?string $table = null, ?string $lastSync = null): Collection
    {
        return $this->engine->pullRemoteChanges($table, $lastSync);
    }

    public function markSynced(Model $model): void
    {
        $this->engine->markSynced($model);
    }

    public function markPending(Model $model, string $action = 'upsert'): void
    {
        $this->engine->markPending($model, $action);
    }

    public function resolveConflicts(Model $local, array $remote, array $conflictRules = []): array
    {
        $strategy = $conflictRules['strategy'] ?? config('supabase.sync.conflict.default_strategy', 'last_write_wins');

        return $this->conflictResolver->resolve($local, $remote, $strategy);
    }

    public function getLastSyncTime(string $table): ?string
    {
        return $this->engine->getLastSyncTime($table);
    }

    public function verifyConnection(): bool
    {
        return $this->engine->verifyConnection();
    }

    public function fullSync(?string $table = null): array
    {
        return $this->engine->fullSync($table);
    }

    public function getSyncStatus(string $table, string $recordId): ?array
    {
        return $this->engine->getSyncStatus($table, $recordId);
    }

    public function getPendingCount(): int
    {
        return $this->engine->getPendingCount();
    }

    public function getConflictCount(): int
    {
        return $this->engine->getConflictCount();
    }

    public function getQueueStats(): array
    {
        return $this->engine->getQueueStats();
    }

    public function getSyncProgress(): array
    {
        return $this->engine->getSyncProgress();
    }

    public function getVersion(Model $model): int
    {
        return $this->engine->getVersion($model);
    }

    public function createSnapshot(Model $model): string
    {
        return $this->snapshotManager->create($model, 'manual');
    }

    public function rollback(string $snapshotId): bool
    {
        return $this->snapshotManager->restore($snapshotId) !== null;
    }

    public function resolveConflict(string $conflictId, array $resolution): bool
    {
        return $this->engine->resolveConflict($conflictId, $resolution);
    }
}
