<?php

declare(strict_types=1);

namespace App\Listeners\Sync;

use App\Events\Sync\SyncStarted;
use Illuminate\Support\Facades\Log;

class LogSyncStart
{
    public function handle(SyncStarted $event): void
    {
        Log::info('Sync started', [
            'table' => $event->table,
            'tables' => $event->tables,
            'full_sync' => $event->fullSync,
            'started_at' => now()->toIso8601String(),
        ]);
    }
}
