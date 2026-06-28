<?php

declare(strict_types=1);

namespace App\Listeners\Sync;

use App\Events\Sync\SyncCompleted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LogSyncCompletion
{
    public function handle(SyncCompleted $event): void
    {
        Cache::put("sync:status:{$event->table}", [
            'status' => 'completed',
            'pushed' => $event->pushed,
            'pulled' => $event->pulled,
            'conflicts' => $event->conflicts,
            'errors' => $event->errors,
            'duration' => $event->duration,
            'completed_at' => now()->toIso8601String(),
        ], 3600);

        Log::info('Sync completed', [
            'table' => $event->table,
            'pushed' => $event->pushed,
            'pulled' => $event->pulled,
            'conflicts' => $event->conflicts,
            'errors' => $event->errors,
            'duration' => $event->duration,
        ]);
    }
}
