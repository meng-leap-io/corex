<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\SyncSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SnapshotManager
{
    private int $maxSnapshotsPerRecord;

    private int $snapshotTtlDays;

    public function __construct()
    {
        $this->maxSnapshotsPerRecord = config('supabase.sync.max_snapshots', 10);
        $this->snapshotTtlDays = config('supabase.sync.snapshot_ttl_days', 30);
    }

    public function create(Model $model, string $reason = 'sync'): string
    {
        $snapshotId = (string) Str::uuid();
        $data = $model->toArray();

        SyncSnapshot::create([
            'id' => $snapshotId,
            'table_name' => $model->getTable(),
            'record_id' => (string) $model->getKey(),
            'user_id' => $data['user_id'] ?? null,
            'data' => $data,
            'version' => $model->sync_version ?? 0,
            'reason' => $reason,
            'created_at' => now(),
        ]);

        $this->prune($model->getTable(), (string) $model->getKey());

        Log::debug('sync.snapshot.created', [
            'snapshot_id' => $snapshotId,
            'table' => $model->getTable(),
            'record_id' => $model->getKey(),
            'version' => $model->sync_version ?? 0,
            'reason' => $reason,
        ]);

        return $snapshotId;
    }

    public function restore(string $snapshotId): ?Model
    {
        $snapshot = SyncSnapshot::find($snapshotId);

        if (! $snapshot) {
            Log::warning('sync.snapshot.not_found', ['snapshot_id' => $snapshotId]);

            return null;
        }

        $modelClass = config("supabase.sync.model_map.{$snapshot->table_name}");

        if (! $modelClass || ! class_exists($modelClass)) {
            Log::error('sync.snapshot.model_not_found', [
                'table' => $snapshot->table_name,
                'snapshot_id' => $snapshotId,
            ]);

            return null;
        }

        DB::beginTransaction();

        try {
            $model = $modelClass::find($snapshot->record_id);

            if ($model) {
                $restoreData = $snapshot->data;
                unset($restoreData[$model->getKeyName()]);

                $model->disableSync();
                $model->update($restoreData);
                $model->enableSync();

                Log::info('sync.snapshot.restored', [
                    'snapshot_id' => $snapshotId,
                    'table' => $snapshot->table_name,
                    'record_id' => $snapshot->record_id,
                    'version' => $snapshot->version,
                ]);
            } else {
                $model = $modelClass::create($snapshot->data);

                Log::info('sync.snapshot.recreated', [
                    'snapshot_id' => $snapshotId,
                    'table' => $snapshot->table_name,
                    'record_id' => $snapshot->record_id,
                ]);
            }

            $snapshot->update(['restored_at' => now()]);

            DB::commit();

            return $model;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('sync.snapshot.restore_failed', [
                'snapshot_id' => $snapshotId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getLatest(Model $model): ?SyncSnapshot
    {
        return SyncSnapshot::where('table_name', $model->getTable())
            ->where('record_id', (string) $model->getKey())
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function list(Model $model, int $limit = 20): array
    {
        return SyncSnapshot::where('table_name', $model->getTable())
            ->where('record_id', (string) $model->getKey())
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function listForTable(string $table, string $recordId, int $limit = 20): array
    {
        return SyncSnapshot::where('table_name', $table)
            ->where('record_id', $recordId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function prune(string $table, string $recordId): int
    {
        $count = SyncSnapshot::where('table_name', $table)
            ->where('record_id', $recordId)
            ->where('id', 'not in', function ($query) use ($table, $recordId) {
                $query->select('id')
                    ->from('sync_snapshots')
                    ->where('table_name', $table)
                    ->where('record_id', $recordId)
                    ->orderBy('created_at', 'desc')
                    ->limit($this->maxSnapshotsPerRecord);
            })
            ->delete();

        if ($count > 0) {
            Log::debug('sync.snapshot.pruned', [
                'table' => $table,
                'record_id' => $recordId,
                'removed' => $count,
            ]);
        }

        return $count;
    }

    public function cleanExpired(): int
    {
        $cutoff = now()->subDays($this->snapshotTtlDays);

        $count = SyncSnapshot::where('created_at', '<', $cutoff)->delete();

        if ($count > 0) {
            Log::info('sync.snapshot.expired_cleaned', [
                'removed' => $count,
                'cutoff' => $cutoff->toIso8601String(),
            ]);
        }

        return $count;
    }
}
