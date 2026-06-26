<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\CodeGeneration;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchAiCompletion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180;
    public int $tries = 3;
    public int $backoff = 5;

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
        private readonly string $userId,
        private readonly array $messages,
        private readonly string $model = 'gpt-4o-mini',
        private readonly ?string $projectId = null,
        private readonly ?string $generationId = null,
        private readonly array $options = [],
    ) {}

    public function handle(): void
    {
        $startTime = microtime(true);

        try {
            $gatewayUrl = config('services.ai_gateway.url', 'http://ai-gateway:8000');

            $response = Http::timeout(120)
                ->post("{$gatewayUrl}/v1/chat/completions", [
                    'model' => $this->model,
                    'messages' => $this->messages,
                    ...$this->options,
                ]);

            $duration = (int)((microtime(true) - $startTime) * 1000);
            $result = $response->json();

            if (!$response->successful()) {
                throw new \RuntimeException($result['error']['message'] ?? 'AI provider error');
            }

            $promptTokens = $result['usage']['prompt_tokens'] ?? 0;
            $completionTokens = $result['usage']['completion_tokens'] ?? 0;
            $totalTokens = $promptTokens + $completionTokens;
            $cost = $this->calculateCost($this->model, $promptTokens, $completionTokens);

            $assistantContent = $result['choices'][0]['message']['content'] ?? '';

            ProcessAiUsage::dispatch(
                userId: $this->userId,
                provider: explode('-', $this->model)[0] ?? 'unknown',
                model: $this->model,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
                cost: $cost,
                duration: $duration,
                endpoint: '/v1/chat/completions',
                success: true,
            );

            if ($this->generationId) {
                $this->updateGeneration($assistantContent, $totalTokens, $cost);
            }

            if ($this->projectId) {
                $this->createConversation($assistantContent, $promptTokens, $completionTokens, $totalTokens, $cost);
            }
        } catch (\Throwable $e) {
            $duration = (int)((microtime(true) - $startTime) * 1000);

            ProcessAiUsage::dispatch(
                userId: $this->userId,
                provider: 'unknown',
                model: $this->model,
                promptTokens: 0,
                completionTokens: 0,
                cost: 0,
                duration: $duration,
                endpoint: '/v1/chat/completions',
                success: false,
            );

            Log::error('job.fetch_ai_completion_failed', [
                'user_id' => $this->userId,
                'model' => $this->model,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('job.fetch_ai_completion_exhausted', [
            'user_id' => $this->userId,
            'model' => $this->model,
            'error' => $e->getMessage(),
        ]);

        if ($this->generationId) {
            CodeGeneration::where('id', $this->generationId)
                ->update(['status' => CodeGeneration::STATUS_FAILED]);
        }
    }

    private function updateGeneration(string $content, int $tokens, float $cost): void
    {
        CodeGeneration::where('id', $this->generationId)->update([
            'code_generated' => $content,
            'tokens_used' => $tokens,
            'cost' => $cost,
            'status' => CodeGeneration::STATUS_COMPLETED,
        ]);
    }

    private function createConversation(
        string $assistantContent,
        int $promptTokens,
        int $completionTokens,
        int $totalTokens,
        float $cost,
    ): void {
        $userMessage = end($this->messages);
        $title = mb_substr(is_string($userMessage['content'] ?? '') ? $userMessage['content'] : 'Conversation', 0, 100);

        Conversation::create([
            'user_id' => $this->userId,
            'project_id' => $this->projectId,
            'title' => $title,
            'model_used' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage['content'] ?? '', 'timestamp' => now()->toIso8601String()],
                ['role' => 'assistant', 'content' => $assistantContent, 'timestamp' => now()->toIso8601String()],
            ],
            'tokens_used' => $totalTokens,
            'total_cost' => $cost,
        ]);
    }

    private function calculateCost(string $model, int $promptTokens, int $completionTokens): float
    {
        $costs = self::MODEL_COSTS[$model] ?? ['input' => 0, 'output' => 0];
        return ($promptTokens * $costs['input']) + ($completionTokens * $costs['output']);
    }
}
