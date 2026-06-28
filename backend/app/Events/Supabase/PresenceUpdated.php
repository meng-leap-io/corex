<?php

declare(strict_types=1);

namespace App\Events\Supabase;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class PresenceUpdated extends BroadcastEvent implements ShouldBroadcastNow
{
    public function __construct(
        string $channel,
        string $event,
        array $payload,
        private readonly string $teamId,
    ) {
        parent::__construct($channel, $event, $payload);
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("team.{$this->teamId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'presence',
            'users' => $this->payload['users'] ?? [],
            'online_count' => count($this->payload['users'] ?? []),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'presence.updated';
    }

    public static function forTeam(string $teamId, array $users): self
    {
        return new self(
            channel: "team:{$teamId}",
            event: 'presence.updated',
            payload: ['users' => $users],
            teamId: $teamId,
        );
    }
}
