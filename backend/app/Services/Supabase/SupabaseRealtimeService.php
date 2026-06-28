<?php

declare(strict_types=1);

namespace App\Services\Supabase;

use App\Services\Supabase\Realtime\OfflineQueue;
use App\Services\Supabase\Realtime\RealtimeChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseRealtimeService
{
    private string $url;

    private string $key;

    private string $projectRef;

    private array $channels = [];

    private array $presenceState = [];

    private bool $enabled;

    public function __construct(
        private readonly SupabaseService $supabase,
        private readonly OfflineQueue $offlineQueue = new OfflineQueue(),
    ) {
        $this->url = rtrim(config('supabase.url', ''), '/');
        $this->key = config('supabase.realtime.key', config('supabase.key', ''));
        $this->projectRef = parse_url($this->url, PHP_URL_HOST);
        $this->enabled = config('supabase.realtime.enabled', true);
    }

    public function channel(string $name): RealtimeChannel
    {
        if (!isset($this->channels[$name])) {
            $this->channels[$name] = new RealtimeChannel(
                $name,
                $this->projectRef,
                $this->key,
            );
        }

        return $this->channels[$name];
    }

    public function subscribe(RealtimeChannel $channel): self
    {
        $this->channels[$channel->name()] = $channel;

        Log::info('supabase.realtime.channel_ready', [
            'channel' => $channel->name(),
            'subscriptions' => count($channel->getSubscriptions()),
        ]);

        return $this;
    }

    public function unsubscribe(string $channelName): self
    {
        unset($this->channels[$channelName]);

        Log::info('supabase.realtime.unsubscribed', ['channel' => $channelName]);

        return $this;
    }

    public function broadcast(string $channel, string $event, array $payload): bool
    {
        if (!$this->enabled) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'apikey' => $this->key,
                'Authorization' => "Bearer {$this->key}",
                'Content-Type' => 'application/json',
            ])->post("{$this->url}/realtime/v1/broadcast", [
                'channel' => $channel,
                'event' => $event,
                'payload' => $payload,
            ]);

            if ($response->successful()) {
                Log::debug('supabase.realtime.broadcast_sent', [
                    'channel' => $channel,
                    'event' => $event,
                ]);

                return true;
            }

            Log::warning('supabase.realtime.broadcast_failed', [
                'channel' => $channel,
                'event' => $event,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;

        } catch (\Throwable $e) {
            Log::error('supabase.realtime.broadcast_error', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function trackPresence(string $channel, string $user, array $metadata = []): void
    {
        if (!isset($this->presenceState[$channel])) {
            $this->presenceState[$channel] = [];
        }

        $this->presenceState[$channel][$user] = array_merge($metadata, [
            'user_id' => $user,
            'online_at' => now()->toIso8601String(),
        ]);

        try {
            Http::withHeaders([
                'apikey' => $this->key,
                'Authorization' => "Bearer {$this->key}",
                'Content-Type' => 'application/json',
            ])->post("{$this->url}/realtime/v1/presence", [
                'channel' => $channel,
                'user' => $user,
                'metadata' => $metadata,
            ]);
        } catch (\Throwable $e) {
            Log::error('supabase.realtime.presence_error', [
                'channel' => $channel,
                'user' => $user,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function untrackPresence(string $channel, string $user): void
    {
        unset($this->presenceState[$channel][$user]);
    }

    public function getPresence(string $channel): array
    {
        return $this->presenceState[$channel] ?? [];
    }

    public function getOnlineUsers(string $channel): array
    {
        return array_keys($this->getPresence($channel));
    }

    public function getChannels(): array
    {
        return array_keys($this->channels);
    }

    public function getChannel(string $name): ?RealtimeChannel
    {
        return $this->channels[$name] ?? null;
    }

    public function getRealtimeEndpoint(): string
    {
        return "wss://{$this->projectRef}/realtime/v1/websocket?apikey={$this->key}&vsn=1.0.0";
    }

    public function getDatabaseChangesEndpoint(string $table, string $event = '*'): string
    {
        return "wss://{$this->projectRef}/realtime/v1/websocket?"
            . http_build_query([
                'apikey' => $this->key,
                'table' => $table,
                'event' => $event,
            ]);
    }

    public function buildChannelName(string $table, string $schema = 'public'): string
    {
        return "replica:{$schema}:{$table}";
    }

    public function sendMessage(string $conversationId, array $message, array $user): bool
    {
        return $this->broadcast(
            "chat:{$conversationId}",
            'message.sent',
            [
                'message' => $message,
                'user' => $user,
                'timestamp' => now()->toIso8601String(),
                'type' => 'chat.message',
            ],
        );
    }

    public function sendProjectUpdate(string $projectId, array $changes, array $user): bool
    {
        return $this->broadcast(
            "project:{$projectId}",
            'project.updated',
            [
                'changes' => $changes,
                'user' => $user,
                'timestamp' => now()->toIso8601String(),
                'type' => 'project.update',
            ],
        );
    }

    public function sendNotification(string $userId, array $notification): bool
    {
        return $this->broadcast(
            "notifications:{$userId}",
            'notification.created',
            [
                'notification' => $notification,
                'timestamp' => now()->toIso8601String(),
                'type' => 'notification',
            ],
        );
    }

    public function sendTeamPresence(string $teamId, array $users): bool
    {
        return $this->broadcast(
            "team:{$teamId}",
            'presence.updated',
            [
                'users' => $users,
                'online_count' => count($users),
                'timestamp' => now()->toIso8601String(),
                'type' => 'presence',
            ],
        );
    }

    public function getClientConfig(): array
    {
        return [
            'url' => $this->url,
            'key' => $this->key,
            'projectRef' => $this->projectRef,
            'enabled' => $this->enabled,
            'endpoint' => $this->getRealtimeEndpoint(),
            'channels' => array_map(
                fn (RealtimeChannel $ch) => $ch->toRealtimePayload(),
                $this->channels,
            ),
        ];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getOfflineQueue(): OfflineQueue
    {
        return $this->offlineQueue;
    }

    public function health(): array
    {
        return [
            'enabled' => $this->enabled,
            'channels' => count($this->channels),
            'presence_channels' => count($this->presenceState),
            'connected' => $this->supabase->isConnected(),
            'project' => $this->projectRef,
        ];
    }
}
