<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookLog;
use App\Services\Webhook\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    private string $logId;

    public function __construct(string $logId)
    {
        $this->logId = $logId;
        $this->onQueue('webhooks');
    }

    public function handle(WebhookService $service): void
    {
        $log = WebhookLog::find($this->logId);

        if (! $log) {
            Log::warning('webhook.job.log_not_found', ['log_id' => $this->logId]);

            return;
        }

        if ($log->status === 'completed') {
            return;
        }

        $log->increment('attempts');
        $log->update(['status' => 'processing']);

        try {
            $result = $service->processLog($this->logId);

            if (($result['status'] ?? 500) >= 400) {
                throw new \RuntimeException($result['message'] ?? 'Processing failed');
            }

            Log::info('webhook.job.processed', [
                'log_id' => $this->logId,
                'status' => $result['status'] ?? 200,
            ]);
        } catch (\Throwable $e) {
            Log::error('webhook.job.failed', [
                'log_id' => $this->logId,
                'attempt' => $log->attempts,
                'error' => $e->getMessage(),
            ]);

            if ($this->attempts() >= $this->tries) {
                $log->markFailed($e->getMessage());
            } else {
                $log->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
                $this->release($this->backoff[$this->attempts()] ?? 60);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        $log = WebhookLog::find($this->logId);

        if ($log) {
            $log->markFailed($e->getMessage());
        }

        Log::error('webhook.job.exhausted', [
            'log_id' => $this->logId,
            'error' => $e->getMessage(),
        ]);
    }
}
