<?php

declare(strict_types=1);

namespace App\Listeners\Sync;

use App\Events\Sync\SyncFailed;
use App\Notifications\AlertNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class LogSyncFailure
{
    public function handle(SyncFailed $event): void
    {
        Log::error('Sync failed', [
            'table' => $event->table,
            'record_id' => $event->recordId,
            'action' => $event->action,
            'error' => $event->error,
            'attempts' => $event->attempts,
        ]);

        Notification::route('mail', config('mail.admin_address'))
            ->notify(new AlertNotification(
                type: 'sync_failure',
                message: "Sync failed for table {$event->table}: {$event->error}",
                data: [
                    'table' => $event->table,
                    'record_id' => $event->recordId,
                    'action' => $event->action,
                    'attempts' => $event->attempts,
                ],
                severity: 'critical',
            ));
    }
}
