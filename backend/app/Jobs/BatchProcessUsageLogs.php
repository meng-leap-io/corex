<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiUsageLog;
use Carbon\Carbon;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BatchProcessUsageLogs implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 2;

    public function __construct(
        private readonly int $chunkSize = 1000,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $processed = 0;
        $failed = 0;

        AiUsageLog::whereNull('processed_at')
            ->where('created_at', '<=', now()->subHour())
            ->chunkById($this->chunkSize, function ($logs) use (&$processed, &$failed) {
                $now = now();

                foreach ($logs as $log) {
                    try {
                        $log->updateQuietly(['processed_at' => $now]);
                        $processed++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('batch.process_usage_log_failed', [
                            'log_id' => $log->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                Log::info('batch.process_usage_logs_progress', [
                    'batch_size' => $logs->count(),
                    'processed' => $processed,
                    'failed' => $failed,
                ]);
            });

        Log::info('batch.process_usage_logs_complete', [
            'total_processed' => $processed,
            'total_failed' => $failed,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('batch.process_usage_logs_failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
