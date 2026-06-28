<?php

declare(strict_types=1);

namespace App\Events\Supabase;

use App\Models\Conversation;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MessageSent extends BroadcastEvent implements ShouldBroadcastNow
{
    public function __construct(
        string $channel,
        string $event,
        array $payload,
        private readonly Conversation $conversation,
    ) {
        parent::__construct($channel, $event, $payload);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("chat.{$this->conversation->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'message',
            'conversation_id' => $this->conversation->id,
            'message' => $this->payload['message'] ?? [],
            'user' => $this->payload['user'] ?? [],
            'timestamp' => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public static function fromConversation(Conversation $conversation, array $message, array $user): self
    {
        return new self(
            channel: "chat:{$conversation->id}",
            event: 'message.sent',
            payload: [
                'message' => $message,
                'user' => $user,
            ],
            conversation: $conversation,
        );
    }
}
