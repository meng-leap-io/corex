<?php

declare(strict_types=1);

namespace App\Events\Sync;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $table,
        public array $tables = [],
        public bool $fullSync = false,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('sync'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sync.started';
    }

    public function broadcastWith(): array
    {
        return [
            'table' => $this->table,
            'tables' => $this->tables,
            'full_sync' => $this->fullSync,
            'started_at' => now()->toIso8601String(),
        ];
    }
}
