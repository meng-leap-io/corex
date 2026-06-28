<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Contracts\SyncContract;
use App\Models\SyncConflict;
use App\Services\Supabase\SupabaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncEngine implements SyncContract
{
    private SyncQueue $queue;

    private ConflictResolver $conflictResolver;

    private SnapshotManager $snapshotManager;

    private SupabaseService $supabase;

    private array $modelMap;

    public function __construct(
        SyncQueue $queue,
        ConflictResolver $conflictResolver,
        SnapshotManager $snapshotManager,
        SupabaseService $supabase,
    ) {
        $this->queue = $queue;
        $this->conflictResolver = $conflictResolver;
        $this->snapshotManager = $snapshotManager;
        $this->supabase = $supabase;
        $this->modelMap = config('supabase.sync.model_map', []);
    }

    public function syncModel(Model $model, string $action = 'upsert'): bool
    {
        if (! method_exists($model, 'isSyncingEnabled') || ! $model->isSyncingEnabled()) {
            return false;
        }

        try {
            $this->snapshotManager->create($model, 'pre_sync');

            $remoteData = $this->fetchRemote($model, $action);

            if ($remoteData && $this->hasConflict($model, $remoteData)) {
                $resolved = $this->conflictResolver->autoResolve($model, $remoteData);

                if ($resolved === null) {
                    $this->conflictResolver->createConflict(
                        $model,
                        $remoteData,
                        'Auto-resolution deferred to manual'
                    );

                    return false;
                }

                $model->disableSync();
                $model->update($resolved);
                $model->enableSync();

                $this->pushToRemote($model, 'upsert');
            } else {
                $this->pushToRemote($model, $action);
            }

            $model->sync_version = ($model->sync_version ?? 0) + 1;
            $model->synced_at = now();

            if ($model->isDirty()) {
                $model->saveQuietly();
            }

            $this->markSynced($model);

            Log::debug('sync.engine.synced', [
                'table' => $model->getTable(),
                'record_id' => $model->getKey(),
                'action' => $action,
                'version' => $model->sync_version,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('sync.engine.sync_failed', [
                'table' => $model->getTable(),
                'record_id' => $model->getKey(),
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            $this->queue->fail(
                $this->queue->push($model->getTable(), $model->getKey(), $action),
                $e->getMessage()
            );

            return false;
        }
    }

    public function syncPending(): int
    {
        $synced = 0;
        $batchSize = config('supabase.sync.worker_batch_size', 25);

        while ($synced < $batchSize) {
            $job = $this->queue->pop();

            if ($job === null) {
                break;
            }

            $modelClass = $this->modelMap[$job['table']] ?? null;

            if (! $modelClass || ! class_exists($modelClass)) {
                $this->queue->fail($job['id'], "No model class for table '{$job['table']}'");

                continue;
            }

            try {
                $model = $modelClass::find($job['record_id']);

                if (! $model) {
                    if ($job['action'] === 'delete') {
                        $this->queue->complete($job['id']);

                        continue;
                    }

                    $model = $modelClass::create($job['data'] ?? []);
                }

                $this->syncModel($model, $job['action']);
                $this->queue->complete($job['id']);
                $synced++;
            } catch (\Throwable $e) {
                $this->queue->fail($job['id'], $e->getMessage());
            }
        }

        return $synced;
    }

    public function pushLocalChanges(?string $table = null): Collection
    {
        $results = collect();

        $query = DB::table('sync_status')->where('status', 'pending');

        if ($table) {
            $query->where('table_name', $table);
        }

        $pending = $query->orderBy('created_at')->limit(100)->get();

        foreach ($pending as $item) {
            $modelClass = $this->modelMap[$item->table_name] ?? null;

            if (! $modelClass) {
                continue;
            }

            $model = $modelClass::find($item->record_id);

            if (! $model) {
                if ($item->action === 'delete') {
                    $this->pushDeleteToRemote($item->table_name, $item->record_id);
                    DB::table('sync_status')->where('id', $item->id)->delete();
                    $results->push(['id' => $item->record_id, 'action' => 'delete', 'success' => true]);
                }

                continue;
            }

            $success = $this->syncModel($model, $item->action);
            $results->push([
                'id' => $item->record_id,
                'table' => $item->table_name,
                'action' => $item->action,
                'success' => $success,
            ]);
        }

        return $results;
    }

    public function pullRemoteChanges(?string $table = null, ?string $lastSync = null): Collection
    {
        $results = collect();

        $endpoint = $table
            ? "/rest/v1/{$table}?select=*&order=updated_at.desc&limit=100"
            : null;

        if (! $endpoint) {
            return $results;
        }

        if ($lastSync) {
            $endpoint .= "&updated_at=gt.{$lastSync}";
        }

        try {
            $response = $this->supabase->get($endpoint);
            $remoteRecords = $response->json() ?? [];

            foreach ($remoteRecords as $remote) {
                $modelClass = $this->modelMap[$table] ?? null;

                if (! $modelClass) {
                    continue;
                }

                $local = $modelClass::find($remote['id']);

                if (! $local) {
                    $local = $modelClass::create($remote);
                    $results->push(['id' => $remote['id'], 'action' => 'create', 'success' => true]);
                } elseif ($this->hasConflict($local, $remote)) {
                    $resolved = $this->conflictResolver->autoResolve($local, $remote);

                    if ($resolved) {
                        $local->disableSync();
                        $local->update($resolved);
                        $local->enableSync();
                        $results->push(['id' => $remote['id'], 'action' => 'update', 'success' => true]);
                    } else {
                        $this->conflictResolver->createConflict($local, $remote, 'Pull conflict');
                        $results->push(['id' => $remote['id'], 'action' => 'conflict', 'success' => false]);
                    }
                } elseif ((int) ($remote['sync_version'] ?? 0) > (int) ($local->sync_version ?? 0)) {
                    $local->disableSync();
                    $local->update($remote);
                    $local->enableSync();
                    $results->push(['id' => $remote['id'], 'action' => 'update', 'success' => true]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('sync.engine.pull_failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    public function markSynced(Model $model): void
    {
        Cache::put(
            "sync:status:{$model->getTable()}:{$model->getKey()}",
            [
                'table' => $model->getTable(),
                'record_id' => (string) $model->getKey(),
                'status' => 'synced',
                'synced_at' => now()->toIso8601String(),
                'version' => $model->sync_version ?? 0,
            ],
            now()->addDay()
        );
    }

    public function markPending(Model $model, string $action = 'upsert'): void
    {
        $this->queue->push(
            $model->getTable(),
            (string) $model->getKey(),
            $action,
            $model->toArray()
        );

        Cache::put(
            "sync:status:{$model->getTable()}:{$model->getKey()}",
            [
                'table' => $model->getTable(),
                'record_id' => (string) $model->getKey(),
                'status' => 'pending',
                'action' => $action,
                'pending_at' => now()->toIso8601String(),
                'version' => $model->sync_version ?? 0,
            ],
            now()->addDay()
        );
    }

    public function resolveConflicts(Model $local, array $remote, array $conflictRules = []): array
    {
        $strategy = $conflictRules['strategy'] ?? ConflictResolver::STRATEGY_LAST_WRITE_WINS;

        return $this->conflictResolver->resolve($local, $remote, $strategy);
    }

    public function getLastSyncTime(string $table): ?string
    {
        $lastSync = DB::table('sync_status')
            ->where('table_name', $table)
            ->where('status', 'synced')
            ->orderBy('synced_at', 'desc')
            ->value('synced_at');

        return $lastSync;
    }

    public function verifyConnection(): bool
    {
        try {
            $response = $this->supabase->get('/rest/v1/');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function fullSync(?string $table = null): array
    {
        $results = [
            'pushed' => 0,
            'pulled' => 0,
            'conflicts' => 0,
            'errors' => 0,
        ];

        $pushed = $this->pushLocalChanges($table);
        $results['pushed'] = $pushed->count();

        $tables = $table ? [$table] : array_keys($this->modelMap);

        foreach ($tables as $t) {
            $pulled = $this->pullRemoteChanges($t);
            $results['pulled'] += $pulled->where('success', true)->count();
            $results['conflicts'] += $pulled->where('action', 'conflict')->count();
        }

        return $results;
    }

    public function getSyncStatus(string $table, string $recordId): ?array
    {
        return Cache::get("sync:status:{$table}:{$recordId}");
    }

    public function getPendingCount(): int
    {
        return $this->queue->pendingCount();
    }

    public function getConflictCount(): int
    {
        return SyncConflict::where('status', 'pending')->count();
    }

    public function getQueueStats(): array
    {
        return $this->queue->getStats();
    }

    public function getSyncProgress(): array
    {
        $total = DB::table('sync_status')->count();
        $synced = DB::table('sync_status')->where('status', 'synced')->count();
        $pending = DB::table('sync_status')->where('status', 'pending')->count();
        $failed = DB::table('sync_status')->where('status', 'failed')->count();

        return [
            'total' => $total,
            'synced' => $synced,
            'pending' => $pending,
            'failed' => $failed,
            'progress' => $total > 0 ? round(($synced / $total) * 100, 1) : 100,
        ];
    }

    public function getVersion(Model $model): int
    {
        return (int) ($model->sync_version ?? 0);
    }

    public function createSnapshot(Model $model): string
    {
        return $this->snapshotManager->create($model, 'manual');
    }

    public function rollback(string $snapshotId): bool
    {
        $restored = $this->snapshotManager->restore($snapshotId);

        return $restored !== null;
    }

    public function resolveConflict(string $conflictId, array $resolution): bool
    {
        $conflict = SyncConflict::find($conflictId);

        if (! $conflict || $conflict->status !== 'pending') {
            return false;
        }

        try {
            $this->conflictResolver->resolveExisting($conflict, $resolution, $conflict->remote_version + 1);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function fetchRemote(Model $model, string $action): ?array
    {
        if ($action === 'delete') {
            return null;
        }

        try {
            $table = $model->getTable();
            $id = $model->getKey();
            $response = $this->supabase->get("/rest/v1/{$table}?id=eq.{$id}&select=*");

            if ($response->successful()) {
                $data = $response->json();

                return $data[0] ?? null;
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function pushToRemote(Model $model, string $action): bool
    {
        try {
            $table = $model->getTable();
            $data = $model->toArray();
            $id = $model->getKey();

            $exists = $this->fetchRemote($model, $action);

            if ($action === 'delete') {
                $this->supabase->delete("/rest/v1/{$table}", ['id' => "eq.{$id}"]);
            } elseif ($exists) {
                $this->supabase->patch("/rest/v1/{$table}", $data, ['id' => "eq.{$id}"]);
            } else {
                $this->supabase->post("/rest/v1/{$table}", $data);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('sync.engine.push_failed', [
                'table' => $model->getTable(),
                'record_id' => $model->getKey(),
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function pushDeleteToRemote(string $table, string $recordId): bool
    {
        try {
            $this->supabase->delete("/rest/v1/{$table}", ['id' => "eq.{$recordId}"]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasConflict(Model $local, array $remote): bool
    {
        $localVersion = (int) ($local->sync_version ?? 0);
        $remoteVersion = (int) ($remote['sync_version'] ?? 0);

        if ($remoteVersion <= $localVersion) {
            return false;
        }

        $localUpdated = strtotime($local->updated_at ?? 'now');
        $remoteUpdated = strtotime($remote['updated_at'] ?? 'now');

        return abs($localUpdated - $remoteUpdated) > 2;
    }
}
