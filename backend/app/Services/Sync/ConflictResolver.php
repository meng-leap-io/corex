<?php

declare(strict_types=1);

namespace App\Services\Sync;

use App\Models\SyncConflict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ConflictResolver
{
    public const STRATEGY_LAST_WRITE_WINS = 'last_write_wins';

    public const STRATEGY_LOCAL_WINS = 'local_wins';

    public const STRATEGY_REMOTE_WINS = 'remote_wins';

    public const STRATEGY_MERGE = 'merge';

    public const STRATEGY_MANUAL = 'manual';

    public const ALL_STRATEGIES = [
        self::STRATEGY_LAST_WRITE_WINS,
        self::STRATEGY_LOCAL_WINS,
        self::STRATEGY_REMOTE_WINS,
        self::STRATEGY_MERGE,
        self::STRATEGY_MANUAL,
    ];

    private array $fieldStrategies;

    public function __construct()
    {
        $this->fieldStrategies = config('supabase.sync.conflict_fields', []);
    }

    public function resolve(
        Model $local,
        array $remote,
        string $strategy = self::STRATEGY_LAST_WRITE_WINS,
    ): array {
        $localData = $local->toArray();
        $localVersion = $local->sync_version ?? 0;
        $remoteVersion = $remote['sync_version'] ?? 0;

        if ($remoteVersion <= $localVersion && $strategy !== self::STRATEGY_REMOTE_WINS) {
            return $localData;
        }

        $resolved = match ($strategy) {
            self::STRATEGY_LOCAL_WINS => $this->resolveLocalWins($localData, $remote),
            self::STRATEGY_REMOTE_WINS => $this->resolveRemoteWins($localData, $remote),
            self::STRATEGY_MERGE => $this->resolveMerge($localData, $remote),
            self::STRATEGY_MANUAL => $this->resolveManual($local, $remote),
            default => $this->resolveLastWriteWins($localData, $remote),
        };

        $resolved['sync_version'] = max($localVersion, $remoteVersion) + 1;
        $resolved['sync_resolved_at'] = now()->toIso8601String();

        return $resolved;
    }

    public function autoResolve(Model $local, array $remote): ?array
    {
        $strategy = $this->detectStrategy($local, $remote);

        if ($strategy === self::STRATEGY_MANUAL) {
            return null;
        }

        return $this->resolve($local, $remote, $strategy);
    }

    public function createConflict(Model $local, array $remote, string $reason = ''): SyncConflict
    {
        $localData = $local->toArray();

        return SyncConflict::create([
            'table_name' => $local->getTable(),
            'record_id' => (string) $local->getKey(),
            'user_id' => $localData['user_id'] ?? null,
            'local_version' => $local->sync_version ?? 0,
            'remote_version' => $remote['sync_version'] ?? 0,
            'local_data' => $localData,
            'remote_data' => $remote,
            'diff' => $this->computeDiff($localData, $remote),
            'reason' => $reason,
            'strategy' => self::STRATEGY_MANUAL,
            'status' => 'pending',
        ]);
    }

    public function resolveExisting(SyncConflict $conflict, array $resolution, int $newVersion): Model
    {
        $modelClass = app('sync.model_map')[$conflict->table_name] ?? null;

        if (! $modelClass || ! class_exists($modelClass)) {
            throw new \RuntimeException("Cannot resolve conflict: no model class for table '{$conflict->table_name}'");
        }

        $model = $modelClass::find($conflict->record_id);

        if (! $model) {
            throw new \RuntimeException("Cannot resolve conflict: record '{$conflict->record_id}' not found");
        }

        $model->update($resolution);
        $model->sync_version = $newVersion;

        $conflict->update([
            'resolution_data' => $resolution,
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        Log::info('sync.conflict.resolved', [
            'table' => $conflict->table_name,
            'record_id' => $conflict->record_id,
            'conflict_id' => $conflict->id,
        ]);

        return $model;
    }

    private function detectStrategy(Model $local, array $remote): string
    {
        $localData = $local->toArray();
        $localVersion = $local->sync_version ?? 0;
        $remoteVersion = $remote['sync_version'] ?? 0;

        $versionDiff = abs($remoteVersion - $localVersion);

        if ($versionDiff > 10) {
            return self::STRATEGY_MANUAL;
        }

        $localUpdated = strtotime($localData['updated_at'] ?? 'now');
        $remoteUpdated = strtotime($remote['updated_at'] ?? 'now');
        $timeDiff = abs($localUpdated - $remoteUpdated);

        if ($timeDiff > 86400) {
            return self::STRATEGY_MANUAL;
        }

        $diff = $this->computeDiff($localData, $remote);
        $conflictingFields = count($diff);

        if ($conflictingFields > 5) {
            return self::STRATEGY_MERGE;
        }

        $overlappingFields = $this->findOverlappingChanges($localData, $remote);

        if (count($overlappingFields) > 3) {
            return self::STRATEGY_MANUAL;
        }

        return self::STRATEGY_LAST_WRITE_WINS;
    }

    private function resolveLocalWins(array $local, array $remote): array
    {
        $localPrefixed = config('supabase.sync.local_preferred_fields', [
            'id', 'created_at', 'updated_at', 'sync_version', 'user_id',
        ]);

        $result = $local;

        foreach ($remote as $key => $value) {
            if (! in_array($key, $localPrefixed, true) && ! isset($local[$key])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function resolveRemoteWins(array $local, array $remote): array
    {
        $remotePrefixed = config('supabase.sync.remote_preferred_fields', [
            'id', 'supabase_id',
        ]);

        $result = $remote;

        foreach ($local as $key => $value) {
            if (! in_array($key, $remotePrefixed, true) && ! isset($remote[$key])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function resolveLastWriteWins(array $local, array $remote): array
    {
        $localTime = strtotime($local['updated_at'] ?? 'now');
        $remoteTime = strtotime($remote['updated_at'] ?? 'now');

        $winner = $localTime >= $remoteTime ? $local : $remote;
        $loser = $localTime >= $remoteTime ? $remote : $local;

        return array_merge($loser, $winner);
    }

    private function resolveMerge(array $local, array $remote): array
    {
        $result = array_merge($remote, $local);

        $diff = $this->computeDiff($local, $remote);

        $localPrefixed = config('supabase.sync.local_preferred_fields', []);
        $remotePrefixed = config('supabase.sync.remote_preferred_fields', []);

        foreach ($diff as $field) {
            if (in_array($field, $localPrefixed, true)) {
                $result[$field] = $local[$field] ?? $remote[$field];
            } elseif (in_array($field, $remotePrefixed, true)) {
                $result[$field] = $remote[$field] ?? $local[$field];
            } elseif ($this->isNumericField($field)) {
                $result[$field] = ($local[$field] ?? 0) + ($remote[$field] ?? 0);
            }
        }

        return $result;
    }

    private function resolveManual(Model $local, array $remote): array
    {
        return $local->toArray();
    }

    private function computeDiff(array $local, array $remote): array
    {
        $diff = [];
        $allKeys = array_unique(array_merge(array_keys($local), array_keys($remote)));

        $skipFields = config('supabase.sync.skip_diff_fields', [
            'updated_at', 'created_at', 'sync_version', 'sync_resolved_at',
            'deleted_at',
        ]);

        foreach ($allKeys as $key) {
            if (in_array($key, $skipFields, true)) {
                continue;
            }

            $localVal = $local[$key] ?? null;
            $remoteVal = $remote[$key] ?? null;

            if (gettype($localVal) === 'array' || gettype($remoteVal) === 'array') {
                if (json_encode($localVal) !== json_encode($remoteVal)) {
                    $diff[] = $key;
                }
            } elseif ((string) $localVal !== (string) $remoteVal) {
                $diff[] = $key;
            }
        }

        return $diff;
    }

    private function findOverlappingChanges(array $local, array $remote): array
    {
        $overlapping = [];

        foreach ($this->computeDiff($local, $remote) as $field) {
            if (isset($local[$field]) && isset($remote[$field])) {
                $overlapping[] = $field;
            }
        }

        return $overlapping;
    }

    private function isNumericField(string $field): bool
    {
        return in_array($field, [
            'token_count', 'message_count', 'tokens_prompt', 'tokens_completion',
            'total_cost', 'total_cost_usd', 'usage_count', 'api_usage_current',
            'api_usage_limit', 'size', 'views',
        ], true);
    }
}
