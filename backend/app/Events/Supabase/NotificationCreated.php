<?php

declare(strict_types=1);

namespace App\Events\Supabase;

use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class NotificationCreated extends BroadcastEvent implements ShouldBroadcastNow
{
    public function __construct(
        string $channel,
        string $event,
        array $payload,
        private readonly User $recipient,
    ) {
        parent::__construct($channel, $event, $payload);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->recipient->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'notification',
            'notification' => $this->payload['notification'] ?? [],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public static function forUser(User $user, array $notification): self
    {
        return new self(
            channel: "notifications:{$user->id}",
            event: 'notification.created',
            payload: ['notification' => $notification],
            recipient: $user,
        );
    }
}
