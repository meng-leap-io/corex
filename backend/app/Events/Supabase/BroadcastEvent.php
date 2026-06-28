<?php

declare(strict_types=1);

namespace App\Events\Supabase;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class BroadcastEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        protected readonly string $channel,
        protected readonly string $event,
        protected readonly array $payload,
    ) {}

    public function channel(): string
    {
        return $this->channel;
    }

    public function event(): string
    {
        return $this->event;
    }

    public function payload(): array
    {
        return $this->payload;
    }

    abstract public function broadcastWith(): array;

    abstract public function broadcastAs(): string;
}
