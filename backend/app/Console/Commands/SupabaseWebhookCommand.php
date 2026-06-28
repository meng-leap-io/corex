<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WebhookEndpoint;
use App\Models\WebhookLog;
use App\Services\Webhook\WebhookService;
use Illuminate\Console\Command;

class SupabaseWebhookCommand extends Command
{
    protected $signature = 'supabase:webhook
        {action=status : Action: status, retry, retry-all, cleanup, endpoints, invoke }
        {--log= : Webhook log ID for retry }
        {--function= : Edge function name to invoke }
        {--payload= : JSON payload for edge function }
        {--days=30 : Retention days for cleanup }';

    protected $description = 'Manage Supabase webhooks and Edge Functions';

    public function handle(WebhookService $service): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'status' => $this->showStatus(),
            'retry' => $this->retryWebhook($service),
            'retry-all' => $this->retryAll($service),
            'cleanup' => $this->cleanup($service),
            'endpoints' => $this->listEndpoints(),
            'invoke' => $this->invokeFunction($service),
            default => $this->showStatus(),
        };
    }

    private function showStatus(): int
    {
        $stats = app(WebhookService::class)->getStats();

        $this->info('Webhook System Status');
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Received', $stats['total']],
                ['Pending', $stats['pending']],
                ['Processing', $stats['processing']],
                ['Completed', $stats['completed']],
                ['Failed', $stats['failed']],
                ['Active Endpoints', $stats['endpoints']],
            ],
        );

        return Command::SUCCESS;
    }

    private function retryWebhook(WebhookService $service): int
    {
        $logId = $this->option('log');

        if (!$logId) {
            $this->error('--log option is required');

            return Command::FAILURE;
        }

        if ($service->retrySingle($logId)) {
            $this->info("Queued webhook {$logId} for retry");

            return Command::SUCCESS;
        }

        $this->error("Webhook {$logId} not found or not in failed state");

        return Command::FAILURE;
    }

    private function retryAll(WebhookService $service): int
    {
        $count = $service->retryFailed();
        $this->info("Retrying {$count} failed webhooks");

        return Command::SUCCESS;
    }

    private function cleanup(WebhookService $service): int
    {
        $days = (int) $this->option('days');
        $deleted = $service->cleanup($days);
        $this->info("Cleaned up {$deleted} webhook logs older than {$days} days");

        return Command::SUCCESS;
    }

    private function listEndpoints(): int
    {
        $endpoints = WebhookEndpoint::all();

        if ($endpoints->isEmpty()) {
            $this->warn('No webhook endpoints configured');

            return Command::SUCCESS;
        }

        $this->table(
            ['Name', 'URL', 'Events', 'Status', 'Retries'],
            $endpoints->map(fn ($e) => [
                $e->name,
                \Illuminate\Support\Str::limit($e->url, 40),
                implode(', ', $e->events ?? []),
                $e->status,
                $e->retry_count,
            ])->toArray(),
        );

        return Command::SUCCESS;
    }

    private function invokeFunction(WebhookService $service): int
    {
        $function = $this->option('function');
        $payloadJson = $this->option('payload');

        if (!$function) {
            $this->error('--function option is required');

            return Command::FAILURE;
        }

        $payload = $payloadJson ? json_decode($payloadJson, true) : [];

        if ($payloadJson && $payload === null) {
            $this->error('Invalid JSON payload');

            return Command::FAILURE;
        }

        $this->info("Invoking Edge Function: {$function}");
        $this->newLine();

        $result = $service->invokeEdgeFunction($function, $payload);

        $this->line("Status: {$result['status']}");
        $this->line('Response:');
        $this->line(json_encode($result['body'], JSON_PRETTY_PRINT));

        return $result['success'] ? Command::SUCCESS : Command::FAILURE;
    }
}
