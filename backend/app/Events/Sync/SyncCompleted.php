<?php

declare(strict_types=1);

namespace App\Events\Sync;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SyncCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $table,
        public int $pushed = 0,
        public int $pulled = 0,
        public int $conflicts = 0,
        public int $errors = 0,
        public float $duration = 0.0,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('sync'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sync.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'table' => $this->table,
            'pushed' => $this->pushed,
            'pulled' => $this->pulled,
            'conflicts' => $this->conflicts,
            'errors' => $this->errors,
            'duration' => $this->duration,
            'completed_at' => now()->toIso8601String(),
        ];
    }
}
