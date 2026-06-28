<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface SyncContract
{
    public function syncModel(Model $model, string $action = 'upsert'): bool;

    public function syncPending(): int;

    public function pushLocalChanges(?string $table = null): Collection;

    public function pullRemoteChanges(?string $table = null, ?string $lastSync = null): Collection;

    public function markSynced(Model $model): void;

    public function markPending(Model $model, string $action = 'upsert'): void;

    public function resolveConflicts(Model $local, array $remote, array $conflictRules = []): array;

    public function getLastSyncTime(string $table): ?string;

    public function verifyConnection(): bool;

    public function fullSync(?string $table = null): array;

    public function getSyncStatus(string $table, string $recordId): ?array;

    public function getPendingCount(): int;

    public function getConflictCount(): int;

    public function getQueueStats(): array;

    public function getSyncProgress(): array;

    public function getVersion(Model $model): int;

    public function createSnapshot(Model $model): string;

    public function rollback(string $snapshotId): bool;

    public function resolveConflict(string $conflictId, array $resolution): bool;
}
