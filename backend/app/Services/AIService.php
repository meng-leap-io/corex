<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\FetchAiCompletion;
use App\Jobs\ProcessAiUsage;
use App\Models\AiUsageLog;
use App\Models\CodeGeneration;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIService
{
    private const CACHE_TTL_USAGE = 300;

    private const CACHE_TTL_MODELS = 3600;

    private const BATCH_THRESHOLD = 50;

    private const MODEL_COSTS = [
        'gpt-4o' => ['input' => 0.000005, 'output' => 0.000015],
        'gpt-4o-mini' => ['input' => 0.00000015, 'output' => 0.0000006],
        'gpt-4-turbo' => ['input' => 0.00001, 'output' => 0.00003],
        'claude-3-opus' => ['input' => 0.000015, 'output' => 0.000075],
        'claude-3-sonnet' => ['input' => 0.000003, 'output' => 0.000015],
        'claude-3-haiku' => ['input' => 0.00000025, 'output' => 0.00000125],
        'gemini-1.5-pro' => ['input' => 0.0000035, 'output' => 0.0000105],
        'gemini-1.5-flash' => ['input' => 0.00000035, 'output' => 0.00000105],
    ];

    public function __construct(
        private readonly CacheService $cacheService,
        private readonly string $aiGatewayUrl = 'http://ai-gateway:8000',
    ) {}

    public function chatCompletion(User $user, array $messages, array $options = []): array
    {
        $model = $options['model'] ?? 'gpt-4o-mini';
        $projectId = $options['project_id'] ?? null;
        $async = $options['async'] ?? false;
        $generationId = $options['generation_id'] ?? null;

        if (! $user->hasApiCapacity()) {
            throw new \RuntimeException('API usage limit exceeded.');
        }

        if ($async) {
            FetchAiCompletion::dispatch(
                userId: $user->id,
                messages: $messages,
                model: $model,
                projectId: $projectId,
                generationId: $generationId,
                options: $options,
            );

            return [
                'status' => 'queued',
                'message' => 'AI completion queued for processing',
            ];
        }

        $startTime = microtime(true);

        try {
            $response = Http::timeout(120)
                ->withOptions(['http_errors' => false])
                ->post("{$this->aiGatewayUrl}/v1/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                    ...$options,
                ]);

            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $result = $response->json();

            if (! $response->successful()) {
                $this->queueUsageLog($user, $model, 0, 0, 0, $duration, false, '/v1/chat/completions');
                throw new \RuntimeException($result['error']['message'] ?? 'AI provider error.');
            }

            $promptTokens = $result['usage']['prompt_tokens'] ?? 0;
            $completionTokens = $result['usage']['completion_tokens'] ?? 0;
            $totalTokens = $promptTokens + $completionTokens;
            $cost = $this->calculateCost($model, $promptTokens, $completionTokens);

            $this->queueUsageLog($user, $model, $promptTokens, $completionTokens, $cost, $duration, true, '/v1/chat/completions');

            if ($projectId) {
                $this->createConversation($user, $projectId, $model, $messages, $result, $totalTokens, $cost);
            }

            $this->cacheService->invalidateAiUsage($user);

            return [
                'content' => $result['choices'][0]['message']['content'] ?? '',
                'model' => $model,
                'tokens' => [
                    'prompt' => $promptTokens,
                    'completion' => $completionTokens,
                    'total' => $totalTokens,
                ],
                'cost' => $cost,
                'duration' => $duration,
            ];
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $this->queueUsageLog($user, $model, 0, 0, 0, $duration, false, '/v1/chat/completions');

            Log::error('ai.chat_completion_failed', [
                'user_id' => $user->id,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function generateCode(User $user, string $prompt, array $options = []): array
    {
        $model = $options['model'] ?? 'gpt-4o-mini';
        $language = $options['language'] ?? null;
        $projectId = $options['project_id'] ?? null;
        $async = $options['async'] ?? false;

        $systemPrompt = 'You are a code generation assistant. Generate clean, well-documented code.'
            .($language ? " Use {$language}." : '');

        if ($async) {
            $generation = CodeGeneration::create([
                'user_id' => $user->id,
                'project_id' => $projectId,
                'prompt' => $prompt,
                'code_generated' => '',
                'language' => $language,
                'model_used' => $model,
                'tokens_used' => 0,
                'cost' => 0,
                'status' => CodeGeneration::STATUS_PROCESSING,
            ]);

            return $this->chatCompletion($user, [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ], [
                ...$options,
                'async' => true,
                'generation_id' => $generation->id,
            ]);
        }

        $result = $this->chatCompletion($user, [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $prompt],
        ], $options);

        $generation = CodeGeneration::create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'prompt' => $prompt,
            'code_generated' => $result['content'],
            'language' => $language,
            'model_used' => $model,
            'tokens_used' => $result['tokens']['total'],
            'cost' => $result['cost'],
            'status' => CodeGeneration::STATUS_COMPLETED,
        ]);

        return [
            'id' => $generation->id,
            'code' => $result['content'],
            'language' => $language,
            'model' => $model,
            'tokens' => $result['tokens'],
            'cost' => $result['cost'],
            'duration' => $result['duration'],
        ];
    }

    public function createEmbedding(User $user, string|array $input, array $options = []): array
    {
        $model = $options['model'] ?? 'text-embedding-3-small';
        $cacheKey = 'embedding:'.md5(serialize($input));

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $startTime = microtime(true);

        try {
            $response = Http::timeout(60)
                ->withOptions(['http_errors' => false])
                ->post("{$this->aiGatewayUrl}/v1/embeddings", [
                    'model' => $model,
                    'input' => $input,
                ]);

            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $result = $response->json();

            if (! $response->successful()) {
                throw new \RuntimeException($result['error']['message'] ?? 'Embedding provider error.');
            }

            $tokensUsed = $result['usage']['total_tokens'] ?? 0;
            $cost = $tokensUsed * 0.00000013;

            $this->queueUsageLog($user, $model, $tokensUsed, 0, $cost, $duration, true, '/v1/embeddings');

            $data = [
                'embeddings' => $result['data'],
                'model' => $model,
                'tokens' => $tokensUsed,
                'cost' => $cost,
                'duration' => $duration,
            ];

            Cache::put($cacheKey, $data, now()->addDay());

            return $data;
        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $startTime) * 1000);
            $this->queueUsageLog($user, $model, 0, 0, 0, $duration, false, '/v1/embeddings');

            Log::error('ai.embedding_failed', [
                'user_id' => $user->id,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getUsageStats(User $user, ?string $from = null, ?string $to = null): array
    {
        return $this->cacheService->getAiUsageStats($user, $from, $to);
    }

    public function getModelCost(string $model): array
    {
        return self::MODEL_COSTS[$model] ?? ['input' => 0, 'output' => 0];
    }

    public function checkUsageLimit(User $user): bool
    {
        return $user->hasApiCapacity();
    }

    public function getDailyUsageAggregated(User $user, int $days = 30): array
    {
        $cached = $this->cacheService->getDailyUsage($user, $days);
        if ($cached) {
            return $cached->toArray();
        }

        return [];
    }

    public function batchLogUsage(array $logs): void
    {
        $chunks = array_chunk($logs, self::BATCH_THRESHOLD);

        foreach ($chunks as $chunk) {
            $insertData = [];
            $now = now();

            foreach ($chunk as $log) {
                $insertData[] = [
                    'id' => (string) Str::uuid(),
                    'user_id' => $log['user_id'],
                    'provider' => $log['provider'],
                    'model' => $log['model'],
                    'prompt_tokens' => $log['prompt_tokens'] ?? 0,
                    'completion_tokens' => $log['completion_tokens'] ?? 0,
                    'cost' => $log['cost'] ?? 0,
                    'duration' => $log['duration'] ?? 0,
                    'endpoint' => $log['endpoint'] ?? '/v1/chat/completions',
                    'success' => $log['success'] ?? true,
                    'ip_address' => $log['ip_address'] ?? null,
                    'user_agent' => $log['user_agent'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            AiUsageLog::insert($insertData);
        }
    }

    public function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $costs = $this->getModelCost($model);

        return ($promptTokens * $costs['input']) + ($completionTokens * $costs['output']);
    }

    private function queueUsageLog(
        User $user,
        string $model,
        int $promptTokens,
        int $completionTokens,
        float $cost,
        int $duration,
        bool $success,
        string $endpoint,
    ): void {
        ProcessAiUsage::dispatch(
            userId: $user->id,
            provider: explode('-', $model)[0] ?? 'unknown',
            model: $model,
            promptTokens: $promptTokens,
            completionTokens: $completionTokens,
            cost: $cost,
            duration: $duration,
            endpoint: $endpoint,
            success: $success,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
        );
    }

    private function createConversation(
        User $user,
        string $projectId,
        string $model,
        array $messages,
        array $result,
        int $totalTokens,
        float $cost,
    ): void {
        $userMessage = end($messages);
        $assistantContent = $result['choices'][0]['message']['content'] ?? '';

        $title = mb_substr(is_string($userMessage['content'] ?? '') ? $userMessage['content'] : 'Conversation', 0, 100);

        Conversation::create([
            'user_id' => $user->id,
            'project_id' => $projectId,
            'title' => $title,
            'model_used' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage['content'] ?? '', 'timestamp' => now()->toIso8601String()],
                ['role' => 'assistant', 'content' => $assistantContent, 'timestamp' => now()->toIso8601String()],
            ],
            'tokens_used' => $totalTokens,
            'total_cost' => $cost,
        ]);
    }
}
