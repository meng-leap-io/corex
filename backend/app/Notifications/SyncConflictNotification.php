<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SyncConflictNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $conflictId,
        public readonly string $table,
        public readonly string $recordId,
        public readonly string $reason = '',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'conflict_id' => $this->conflictId,
            'table' => $this->table,
            'record_id' => $this->recordId,
            'reason' => $this->reason,
            'message' => "Sync conflict detected in table '{$this->table}' for record {$this->recordId}.",
            'created_at' => now()->toIso8601String(),
        ];
    }
}
