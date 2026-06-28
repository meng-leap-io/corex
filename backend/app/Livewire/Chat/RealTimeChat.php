<?php

declare(strict_types=1);

namespace App\Livewire\Chat;

use App\Models\Conversation;
use App\Services\Supabase\SupabaseRealtimeService;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class RealTimeChat extends Component
{
    use WithPagination;

    public ?string $conversationId = null;

    public string $message = '';

    public string $model = 'gpt-4o-mini';

    public array $messages = [];

    public bool $loading = false;

    public bool $isTyping = false;

    protected $listeners = [
        'echo:chat.{conversationId},message.sent' => 'handleRealtimeMessage',
        'messageReceived' => 'addMessage',
    ];

    protected $rules = [
        'message' => ['required', 'string', 'min:1', 'max:10000'],
        'model' => ['required', 'string', 'in:gpt-4o,gpt-4o-mini,claude-3-opus,claude-3-sonnet,gemini-1.5-pro'],
    ];

    public function mount(?string $conversationId = null): void
    {
        $this->conversationId = $conversationId;

        if ($conversationId) {
            $this->loadMessages();
        }
    }

    public function loadMessages(): void
    {
        if (! $this->conversationId) {
            return;
        }

        $conversation = Conversation::find($this->conversationId);

        if ($conversation) {
            $this->messages = $conversation->messages ?? [];
        }
    }

    public function sendMessage(): void
    {
        $this->validate();

        if (! $this->conversationId) {
            $this->createConversation();
        }

        $messageData = [
            'role' => 'user',
            'content' => $this->message,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->messages[] = $messageData;
        $this->loading = true;

        $this->dispatch('messageSent', message: $messageData);

        $this->broadcastMessage($messageData);

        $this->message = '';
    }

    protected function broadcastMessage(array $messageData): void
    {
        try {
            $realtime = app(SupabaseRealtimeService::class);

            $realtime->sendMessage(
                $this->conversationId,
                $messageData,
                ['id' => auth()->id(), 'name' => auth()->user()?->name],
            );
        } catch (\Throwable $e) {
            logger()->warning('realtime.broadcast_failed', ['error' => $e->getMessage()]);
        }
    }

    protected function createConversation(): void
    {
        $conversation = Conversation::create([
            'user_id' => auth()->id(),
            'title' => mb_substr($this->message, 0, 50),
            'model_used' => $this->model,
            'messages' => [],
        ]);

        $this->conversationId = $conversation->id;

        $this->dispatch('conversationCreated', conversationId: $conversation->id);
    }

    public function handleRealtimeMessage(array $payload): void
    {
        $this->addMessage($payload['message'] ?? $payload);
    }

    public function addMessage(array $message): void
    {
        $this->messages[] = $message;

        if ($this->conversationId) {
            $conversation = Conversation::find($this->conversationId);

            if ($conversation) {
                $conversation->appendMessage(
                    $message['role'] ?? 'assistant',
                    $message['content'] ?? '',
                );
            }
        }
    }

    public function clearChat(): void
    {
        $this->messages = [];
        $this->conversationId = null;
    }

    public function typing(): void
    {
        $this->isTyping = true;
        $this->dispatch('userTyping', conversationId: $this->conversationId);
    }

    public function render(): View
    {
        return view('livewire.chat.real-time-chat');
    }
}
