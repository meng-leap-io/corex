<?php

declare(strict_types=1);

namespace App\Services\Sync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SyncQueue
{
    private string $prefix;

    private int $maxSize;

    public function __construct()
    {
        $this->prefix = config('supabase.sync.queue_prefix', 'sync_queue');
        $this->maxSize = config('supabase.sync.queue_max_size', 5000);
    }

    public function push(string $table, string $recordId, string $action = 'upsert', array $data = []): string
    {
        $jobId = (string) Str::uuid();

        $job = [
            'id' => $jobId,
            'table' => $table,
            'record_id' => $recordId,
            'action' => $action,
            'data' => $data,
            'attempts' => 0,
            'max_attempts' => config('supabase.sync.retry_attempts', 3),
            'created_at' => now()->toIso8601String(),
            'error' => null,
        ];

        $key = "{$this->prefix}:{$table}:{$recordId}";

        $existing = Cache::get($key);

        if ($existing && $existing['action'] === 'delete' && $action === 'upsert') {
            return $existing['id'];
        }

        Cache::put($key, $job, now()->addDays(7));

        Cache::lpush("{$this->prefix}:pending", $jobId);

        $this->trimPending();

        Log::debug('sync.queue.pushed', [
            'job_id' => $jobId,
            'table' => $table,
            'record_id' => $recordId,
            'action' => $action,
        ]);

        return $jobId;
    }

    public function pop(): ?array
    {
        $jobId = Cache::rpop("{$this->prefix}:pending");

        if ($jobId === null) {
            return null;
        }

        $job = Cache::get("{$this->prefix}:{$jobId}");

        if ($job === null) {
            return $this->pop();
        }

        return $job;
    }

    public function peek(int $limit = 10): array
    {
        $jobIds = Cache::lrange("{$this->prefix}:pending", 0, $limit - 1);
        $jobs = [];

        foreach ($jobIds as $id) {
            $job = Cache::get("{$this->prefix}:{$id}");
            if ($job) {
                $jobs[] = $job;
            }
        }

        return $jobs;
    }

    public function complete(string $jobId): void
    {
        Cache::forget("{$this->prefix}:{$jobId}");

        Log::debug('sync.queue.completed', ['job_id' => $jobId]);
    }

    public function fail(string $jobId, string $error): void
    {
        $job = Cache::get("{$this->prefix}:{$jobId}");

        if ($job) {
            $job['attempts']++;
            $job['error'] = $error;

            if ($job['attempts'] >= $job['max_attempts']) {
                Cache::lpush("{$this->prefix}:dead", $jobId);
                Cache::put("{$this->prefix}:dead:{$jobId}", $job, now()->addDays(30));

                Log::warning('sync.queue.dead', [
                    'job_id' => $jobId,
                    'table' => $job['table'],
                    'record_id' => $job['record_id'],
                    'attempts' => $job['attempts'],
                    'error' => $error,
                ]);
            } else {
                Cache::put("{$this->prefix}:{$jobId}", $job, now()->addDays(7));

                $backoff = min(pow(2, $job['attempts']) * 5, 300);
                Cache::lpush("{$this->prefix}:pending", $jobId);

                Log::debug('sync.queue.retry', [
                    'job_id' => $jobId,
                    'attempt' => $job['attempts'],
                    'backoff' => $backoff,
                ]);
            }
        }
    }

    public function pendingCount(): int
    {
        return Cache::llen("{$this->prefix}:pending");
    }

    public function deadCount(): int
    {
        return Cache::llen("{$this->prefix}:dead");
    }

    public function clearDead(): int
    {
        $deadJobIds = Cache::lrange("{$this->prefix}:dead", 0, -1);
        $count = count($deadJobIds);

        foreach ($deadJobIds as $id) {
            Cache::forget("{$this->prefix}:dead:{$id}");
        }

        Cache::forget("{$this->prefix}:dead");

        Log::info('sync.queue.dead_cleared', ['count' => $count]);

        return $count;
    }

    public function retryDead(): int
    {
        $deadJobIds = Cache::lrange("{$this->prefix}:dead", 0, -1);
        $count = 0;

        foreach ($deadJobIds as $id) {
            $job = Cache::get("{$this->prefix}:dead:{$id}");

            if ($job) {
                $job['attempts'] = 0;
                $job['error'] = null;
                Cache::put("{$this->prefix}:{$id}", $job, now()->addDays(7));
                Cache::lpush("{$this->prefix}:pending", $id);
                Cache::forget("{$this->prefix}:dead:{$id}");
                $count++;
            }
        }

        Cache::forget("{$this->prefix}:dead");

        Log::info('sync.queue.dead_retried', ['count' => $count]);

        return $count;
    }

    public function cancelPending(string $table, string $recordId): bool
    {
        $key = "{$this->prefix}:{$table}:{$recordId}";

        return Cache::forget($key);
    }

    public function flush(): void
    {
        $pattern = "{$this->prefix}:*";

        Cache::forget("{$this->prefix}:pending");
        Cache::forget("{$this->prefix}:dead");

        Log::info('sync.queue.flushed');
    }

    public function getStats(): array
    {
        return [
            'pending' => $this->pendingCount(),
            'dead' => $this->deadCount(),
            'prefix' => $this->prefix,
            'max_size' => $this->maxSize,
        ];
    }

    private function trimPending(): void
    {
        $count = Cache::llen("{$this->prefix}:pending");

        if ($count > $this->maxSize) {
            $trim = $count - $this->maxSize;
            Cache::ltrim("{$this->prefix}:pending", 0, $this->maxSize - 1);

            Log::warning('sync.queue.trimmed', ['removed' => $trim, 'max' => $this->maxSize]);
        }
    }
}
