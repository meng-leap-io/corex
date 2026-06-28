<?php

declare(strict_types=1);

namespace App\Events\Sync;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $table,
        public string $recordId,
        public string $action,
        public string $error,
        public int $attempts = 0,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('sync'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sync.failed';
    }

    public function broadcastWith(): array
    {
        return [
            'table' => $this->table,
            'record_id' => $this->recordId,
            'action' => $this->action,
            'error' => $this->error,
            'attempts' => $this->attempts,
            'failed_at' => now()->toIso8601String(),
        ];
    }
}
