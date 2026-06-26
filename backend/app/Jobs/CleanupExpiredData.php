<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AiUsageLog;
use App\Models\CodeGeneration;
use App\Models\Conversation;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupExpiredData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function handle(): void
    {
        $cutoff = now()->subDays(90);

        $deletedUsage = AiUsageLog::where('created_at', '<', $cutoff)
            ->where('success', true)
            ->take(5000)
            ->delete();

        $deletedConversations = Conversation::whereNull('project_id')
            ->where('created_at', '<', $cutoff)
            ->take(1000)
            ->delete();

        $failedGenerations = CodeGeneration::where('status', CodeGeneration::STATUS_FAILED)
            ->where('created_at', '<', now()->subDays(30))
            ->take(500)
            ->delete();

        $expiredSubscriptions = Subscription::where('status', Subscription::STATUS_EXPIRED)
            ->where('ends_at', '<', now()->subDays(90))
            ->take(500)
            ->delete();

        Log::info('cleanup.expired_data_complete', [
            'deleted_usage_logs' => $deletedUsage,
            'deleted_conversations' => $deletedConversations,
            'deleted_failed_generations' => $failedGenerations,
            'deleted_expired_subscriptions' => $expiredSubscriptions,
        ]);
    }
}
