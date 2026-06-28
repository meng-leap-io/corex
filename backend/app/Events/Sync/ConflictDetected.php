<?php

declare(strict_types=1);

namespace App\Events\Sync;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConflictDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $conflictId,
        public string $table,
        public string $recordId,
        public ?string $userId = null,
        public int $localVersion = 0,
        public int $remoteVersion = 0,
        public string $reason = '',
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('sync'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sync.conflict';
    }

    public function broadcastWith(): array
    {
        return [
            'conflict_id' => $this->conflictId,
            'table' => $this->table,
            'record_id' => $this->recordId,
            'user_id' => $this->userId,
            'local_version' => $this->localVersion,
            'remote_version' => $this->remoteVersion,
            'reason' => $this->reason,
            'detected_at' => now()->toIso8601String(),
        ];
    }
}
