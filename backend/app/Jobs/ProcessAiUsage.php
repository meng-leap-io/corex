<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAiUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries = 3;
    public int $backoff = 2;

    public function __construct(
        private readonly string $userId,
        private readonly string $provider,
        private readonly string $model,
        private readonly int $promptTokens,
        private readonly int $completionTokens,
        private readonly float $cost,
        private readonly int $duration,
        private readonly string $endpoint,
        private readonly bool $success,
        private readonly ?string $ipAddress = null,
        private readonly ?string $userAgent = null,
    ) {}

    public function handle(): void
    {
        try {
            AiUsageLog::create([
                'user_id' => $this->userId,
                'provider' => $this->provider,
                'model' => $this->model,
                'prompt_tokens' => $this->promptTokens,
                'completion_tokens' => $this->completionTokens,
                'cost' => $this->cost,
                'duration' => $this->duration,
                'endpoint' => $this->endpoint,
                'success' => $this->success,
                'ip_address' => $this->ipAddress,
                'user_agent' => $this->userAgent,
            ]);

            if ($this->success) {
                $user = User::find($this->userId);
                if ($user) {
                    $user->incrementApiUsage($this->promptTokens + $this->completionTokens);
                }
            }
        } catch (\Throwable $e) {
            Log::error('job.process_ai_usage_failed', [
                'user_id' => $this->userId,
                'model' => $this->model,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('job.process_ai_usage_exhausted', [
            'user_id' => $this->userId,
            'model' => $this->model,
            'error' => $e->getMessage(),
        ]);

        AiUsageLog::create([
            'user_id' => $this->userId,
            'provider' => $this->provider,
            'model' => $this->model,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'cost' => $this->cost,
            'duration' => $this->duration,
            'endpoint' => $this->endpoint,
            'success' => $this->success,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
        ]);
    }
}
