<?php

declare(strict_types=1);

namespace App\Services\Webhook;

use App\Jobs\ProcessWebhookJob;
use App\Models\WebhookEndpoint;
use App\Models\WebhookLog;
use App\Services\Supabase\SupabaseService;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookService
{
    private WebhookRouter $router;

    private WebhookSignature $signature;

    private SupabaseService $supabase;

    private array $config;

    public function __construct(
        WebhookRouter $router,
        WebhookSignature $signature,
        SupabaseService $supabase,
    ) {
        $this->router = $router;
        $this->signature = $signature;
        $this->supabase = $supabase;
        $this->config = config('webhooks');
    }

    public function handleIncoming(Request $request, string $provider = 'default'): array
    {
        $log = $this->createLog($request, $provider);

        try {
            if (!$this->verifySignature($request, $provider)) {
                $log->markFailed('Invalid signature');
                Log::warning('webhook.signature_invalid', [
                    'provider' => $provider,
                    'event_type' => $log->event_type,
                    'log_id' => $log->id,
                ]);

                return ['status' => 401, 'message' => 'Invalid signature'];
            }

            if ($this->isRateLimited($request, $provider)) {
                $log->markFailed('Rate limit exceeded');
                Log::warning('webhook.rate_limited', [
                    'provider' => $provider,
                    'ip' => $request->ip(),
                    'log_id' => $log->id,
                ]);

                return ['status' => 429, 'message' => 'Too many requests'];
            }

            $log->update(['status' => 'processing']);

            $handlerClass = $this->router->resolve($request);

            if ($handlerClass) {
                $handler = $this->router->handler($handlerClass);

                if (method_exists($handler, 'handle')) {
                    $result = $handler->handle($request, $log);

                    $log->markCompleted(
                        $result['response'] ?? null,
                        $result['status'] ?? 200,
                    );

                    $this->relayToEndpoints($log);

                    return [
                        'status' => $result['status'] ?? 200,
                        'message' => 'Processed',
                        'log_id' => $log->id,
                    ];
                }
            }

            $log->update(['status' => 'pending']);

            ProcessWebhookJob::dispatch($log->id);

            return ['status' => 202, 'message' => 'Queued', 'log_id' => $log->id];
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            Log::error('webhook.processing_failed', [
                'provider' => $provider,
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);

            if ($log->canRetry()) {
                $this->retryLater($log);
            }

            return ['status' => 500, 'message' => 'Processing failed'];
        }
    }

    public function processLog(string $logId): array
    {
        $log = WebhookLog::findOrFail($logId);

        if ($log->status === 'completed') {
            return ['status' => 200, 'message' => 'Already completed'];
        }

        $log->increment('attempts');
        $log->update(['status' => 'processing']);

        try {
            $handlerClass = null;

            foreach ($this->router->getRoutes() as $path => $config) {
                if ($config['handler']) {
                    $handlerClass = $config['handler'];
                    break;
                }
            }

            if ($handlerClass) {
                $handler = $this->router->handler($handlerClass);

                if (method_exists($handler, 'process')) {
                    $result = $handler->process($log);

                    $log->markCompleted(
                        $result['response'] ?? null,
                        $result['status'] ?? 200,
                    );

                    $this->relayToEndpoints($log);

                    return ['status' => 200, 'message' => 'Processed'];
                }
            }

            $log->markFailed('No handler found');

            return ['status' => 404, 'message' => 'No handler found'];
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());

            if ($log->canRetry()) {
                $this->retryLater($log);
            }

            return ['status' => 500, 'message' => $e->getMessage()];
        }
    }

    public function relayToEndpoints(WebhookLog $log): void
    {
        $endpoints = WebhookEndpoint::active()
            ->forEvent($log->event_type)
            ->get();

        foreach ($endpoints as $endpoint) {
            $this->sendToEndpoint($endpoint, $log);
        }
    }

    public function sendToEndpoint(WebhookEndpoint $endpoint, WebhookLog $log): bool
    {
        $payload = [
            'event_type' => $log->event_type,
            'provider' => $log->provider,
            'payload' => $log->payload,
            'metadata' => [
                'log_id' => $log->id,
                'relayed_at' => now()->toIso8601String(),
            ],
        ];

        $signer = new WebhookSignature($endpoint->secret ?? $this->config['signing']['default']);
        $timestamp = time();
        $signature = $signer->sign($payload, $timestamp);

        $maxRetries = $endpoint->retry_count ?? 3;

        for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
            if ($attempt > 0) {
                $backoff = min(1000 * pow(2, $attempt), 30000);
                usleep($backoff * 1000);
            }

            try {
                $response = Http::timeout($endpoint->timeout_seconds ?? 10)
                    ->withHeaders(array_merge([
                        'X-Webhook-Signature' => $signature,
                        'X-Webhook-Timestamp' => (string) $timestamp,
                        'X-Webhook-Event' => $log->event_type,
                        'X-Webhook-Attempt' => (string) ($attempt + 1),
                        'Content-Type' => 'application/json',
                        'User-Agent' => 'CorexWebhook/1.0',
                    ], $endpoint->headers ?? []))
                    ->post($endpoint->url, $payload);

                if ($response->successful()) {
                    $endpoint->markSuccess();

                    Log::info('webhook.endpoint_relayed', [
                        'endpoint' => $endpoint->name,
                        'event' => $log->event_type,
                        'attempt' => $attempt + 1,
                    ]);

                    return true;
                }

                Log::warning('webhook.endpoint_relay_failed', [
                    'endpoint' => $endpoint->name,
                    'status' => $response->status(),
                    'attempt' => $attempt + 1,
                ]);
            } catch (\Throwable $e) {
                Log::error('webhook.endpoint_relay_error', [
                    'endpoint' => $endpoint->name,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1,
                ]);
            }
        }

        $endpoint->markFailure();

        return false;
    }

    public function retryFailed(int $maxAgeHours = 24): int
    {
        $cutoff = now()->subHours($maxAgeHours);

        $failedLogs = WebhookLog::failed()
            ->where('created_at', '>=', $cutoff)
            ->whereColumn('attempts', '<', 'max_attempts')
            ->get();

        $count = 0;

        foreach ($failedLogs as $log) {
            $log->update(['attempts' => 0, 'status' => 'pending']);
            ProcessWebhookJob::dispatch($log->id);
            $count++;
        }

        Log::info('webhook.retry_failed_initiated', ['count' => $count]);

        return $count;
    }

    public function retrySingle(string $logId): bool
    {
        $log = WebhookLog::find($logId);

        if (!$log || $log->status !== 'failed') {
            return false;
        }

        $log->update(['attempts' => 0, 'status' => 'pending']);
        ProcessWebhookJob::dispatch($log->id);

        return true;
    }

    public function getStats(): array
    {
        return [
            'total' => WebhookLog::count(),
            'pending' => WebhookLog::pending()->count(),
            'processing' => WebhookLog::processing()->count(),
            'completed' => WebhookLog::completed()->count(),
            'failed' => WebhookLog::failed()->count(),
            'endpoints' => WebhookEndpoint::active()->count(),
        ];
    }

    public function cleanup(int $retentionDays = 30): int
    {
        $cutoff = now()->subDays($retentionDays);

        $deleted = WebhookLog::where('created_at', '<', $cutoff)
            ->whereIn('status', ['completed', 'failed'])
            ->delete();

        Log::info('webhook.cleanup_completed', [
            'deleted' => $deleted,
            'retention_days' => $retentionDays,
        ]);

        return $deleted;
    }

    public function invokeEdgeFunction(string $functionName, array $payload): array
    {
        $anonKey = config('supabase.key');
        $url = rtrim(config('supabase.url'), '/');

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => "Bearer {$anonKey}",
                    'Content-Type' => 'application/json',
                ])
                ->post("{$url}/functions/v1/{$functionName}", $payload);

            $body = $response->json() ?? [];

            if (!$response->successful()) {
                Log::error('webhook.edge_function_error', [
                    'function' => $functionName,
                    'status' => $response->status(),
                    'error' => $body['error'] ?? $response->body(),
                ]);
            }

            return [
                'status' => $response->status(),
                'body' => $body,
                'success' => $response->successful(),
            ];
        } catch (\Throwable $e) {
            Log::error('webhook.edge_function_failed', [
                'function' => $functionName,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 500, 'body' => ['error' => $e->getMessage()], 'success' => false];
        }
    }

    private function createLog(Request $request, string $provider): WebhookLog
    {
        return WebhookLog::create([
            'id' => (string) Str::uuid(),
            'provider' => $provider,
            'event_type' => $request->header('X-Webhook-Event', $request->input('type', 'unknown')),
            'event_id' => $request->header('X-Webhook-Id', $request->input('id')),
            'payload' => $request->json() ?? $request->all(),
            'headers' => $request->headers->all(),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => $this->config['retry']['max_attempts'] ?? 3,
        ]);
    }

    private function verifySignature(Request $request, string $provider): bool
    {
        $config = $this->router->getConfig($request->path());

        if ($config && !$config['verify_signature']) {
            return true;
        }

        return match ($provider) {
            'stripe' => $this->signature->verifyStripe($request),
            'resend' => $this->signature->verifyResend($request),
            'github' => $this->signature->verifyGitHub($request),
            default => $this->signature->verify($request),
        };
    }

    private function isRateLimited(Request $request, string $provider): bool
    {
        $config = $this->router->getConfig($request->path());

        if ($config && !$config['rate_limit']) {
            return false;
        }

        $key = "webhook:ratelimit:{$provider}:{$request->ip()}";
        $limit = $this->config['rate_limiting']['default_per_minute'] ?? 60;

        $current = (int) Cache::get($key, 0);

        if ($current >= $limit) {
            return true;
        }

        Cache::add($key, 0, 60);
        Cache::increment($key);

        return false;
    }

    private function retryLater(WebhookLog $log): void
    {
        $delay = min(
            $this->config['retry']['backoff_base'] ?? 10,
            ($this->config['retry']['backoff_base'] ?? 10) * pow(2, $log->attempts)
        );

        ProcessWebhookJob::dispatch($log->id)->delay(now()->addSeconds($delay));

        Log::info('webhook.queued_for_retry', [
            'log_id' => $log->id,
            'attempt' => $log->attempts,
            'delay' => $delay,
        ]);
    }
}
