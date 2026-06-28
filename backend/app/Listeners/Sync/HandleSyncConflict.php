<?php

declare(strict_types=1);

namespace App\Listeners\Sync;

use App\Events\Sync\ConflictDetected;
use App\Models\SyncConflict;
use App\Models\User;
use App\Notifications\SyncConflictNotification;
use Illuminate\Support\Facades\Log;

class HandleSyncConflict
{
    public function handle(ConflictDetected $event): void
    {
        $conflict = SyncConflict::create([
            'table_name' => $event->table,
            'record_id' => $event->recordId,
            'user_id' => $event->userId,
            'local_version' => $event->localVersion,
            'remote_version' => $event->remoteVersion,
            'reason' => $event->reason,
            'status' => 'pending',
        ]);

        Log::warning('Sync conflict detected', [
            'conflict_id' => $conflict->id,
            'table' => $event->table,
            'record_id' => $event->recordId,
            'reason' => $event->reason,
        ]);

        if ($event->userId !== null) {
            $user = User::find($event->userId);
            if ($user !== null) {
                $user->notify(new SyncConflictNotification(
                    conflictId: $event->conflictId,
                    table: $event->table,
                    recordId: $event->recordId,
                    reason: $event->reason,
                ));
            }
        }

        $this->attemptAutoResolve($event, $conflict);
    }

    private function attemptAutoResolve(ConflictDetected $event, SyncConflict $conflict): void
    {
        if ($event->localVersion > $event->remoteVersion) {
            $conflict->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);

            Log::info('Sync conflict auto-resolved (local version newer)', [
                'conflict_id' => $event->conflictId,
                'local_version' => $event->localVersion,
                'remote_version' => $event->remoteVersion,
            ]);
        }
    }
}
