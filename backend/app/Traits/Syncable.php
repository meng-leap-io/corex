<?php

declare(strict_types=1);

namespace App\Traits;

use App\Contracts\SyncContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

trait Syncable
{
    protected bool $syncEnabled = true;

    protected array $syncOriginal = [];

    public static function bootSyncable(): void
    {
        static::created(function (Model $model) {
            $model->captureSyncOriginal();
            $model->queueSync('upsert');
        });

        static::updated(function (Model $model) {
            if ($model->hasSyncChanges()) {
                $model->queueSync('upsert');
            }
        });

        static::deleted(function (Model $model) {
            $model->queueSync('delete');
        });

        static::restored(function (Model $model) {
            $model->captureSyncOriginal();
            $model->queueSync('upsert');
        });
    }

    public function initializeSyncable(): void
    {
        $this->captureSyncOriginal();

        $this->mergeFillable(['sync_version']);
        $this->mergeCasts(['sync_version' => 'integer']);

        if (!in_array('sync_version', $this->appends ?? [])) {
            $this->appends[] = 'sync_version';
        }
    }

    public function captureSyncOriginal(): void
    {
        $this->syncOriginal = $this->getSyncFields();
    }

    public function hasSyncChanges(): bool
    {
        $current = $this->getSyncFields();
        $original = $this->syncOriginal;

        foreach ($current as $key => $value) {
            $origValue = $original[$key] ?? null;
            if (gettype($value) === 'array' || gettype($origValue) === 'array') {
                if (json_encode($value) !== json_encode($origValue)) {
                    return true;
                }
            } elseif ((string) $value !== (string) $origValue) {
                return true;
            }
        }

        return false;
    }

    public function getSyncChanges(): array
    {
        $changes = [];
        $current = $this->getSyncFields();
        $original = $this->syncOriginal;

        foreach ($current as $key => $value) {
            $origValue = $original[$key] ?? null;
            if (gettype($value) === 'array' || gettype($origValue) === 'array') {
                if (json_encode($value) !== json_encode($origValue)) {
                    $changes[$key] = [
                        'from' => $origValue,
                        'to' => $value,
                    ];
                }
            } elseif ((string) $value !== (string) $origValue) {
                $changes[$key] = [
                    'from' => $origValue,
                    'to' => $value,
                ];
            }
        }

        return $changes;
    }

    public function queueSync(string $action = 'upsert'): void
    {
        if (!$this->syncEnabled || !config('supabase.sync.enabled') || !config('supabase.sync.auto_sync')) {
            return;
        }

        try {
            $sync = App::make(SyncContract::class);

            if ($sync->verifyConnection()) {
                $sync->syncModel($this, $action);
            } else {
                $sync->markPending($this, $action);
            }
        } catch (\Throwable) {
            try {
                $sync = App::make(SyncContract::class);
                $sync->markPending($this, $action);
            } catch (\Throwable) {
            }
        }
    }

    public function syncNow(string $action = 'upsert'): bool
    {
        $sync = App::make(SyncContract::class);

        return $sync->syncModel($this, $action);
    }

    public function isSynced(): bool
    {
        try {
            $sync = App::make(SyncContract::class);
            $status = $sync->getSyncStatus($this->getTable(), (string) $this->getKey());

            return $status && $status['status'] === 'synced';
        } catch (\Throwable) {
            return false;
        }
    }

    public function getSyncVersion(): int
    {
        return (int) ($this->sync_version ?? 0);
    }

    public function incrementSyncVersion(): void
    {
        $this->sync_version = $this->getSyncVersion() + 1;
    }

    public function beforeSync(): void
    {
        $this->incrementSyncVersion();
        $this->captureSyncOriginal();
    }

    public function afterSync(): void
    {
        $this->captureSyncOriginal();
    }

    public function scopeOnlySynced($query)
    {
        return $query->whereNotNull('synced_at');
    }

    public function scopeOnlyPending($query)
    {
        return $query->whereNull('synced_at');
    }

    public function disableSync(): void
    {
        $this->syncEnabled = false;
    }

    public function enableSync(): void
    {
        $this->syncEnabled = true;
    }

    public function isSyncingEnabled(): bool
    {
        return $this->syncEnabled;
    }

    private function getSyncFields(): array
    {
        $data = $this->toArray();
        $skipFields = config('supabase.sync.skip_diff_fields', [
            'updated_at', 'created_at', 'sync_version', 'sync_resolved_at',
            'deleted_at', 'synced_at',
        ]);

        return array_diff_key($data, array_flip($skipFields));
    }
}
