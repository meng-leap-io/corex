<?php

declare(strict_types=1);

namespace App\Services\Supabase\Realtime;

class RealtimeChannel
{
    private const TYPE_BROADCAST = 'broadcast';

    private const TYPE_PRESENCE = 'presence';

    private const TYPE_POSTGRES_CHANGES = 'postgres_changes';

    private array $subscriptions = [];

    private array $presenceState = [];

    public function __construct(
        private readonly string $name,
        private readonly string $projectRef,
        private readonly string $key,
        private readonly array $config = [],
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function on(string $event, callable $callback): self
    {
        $this->subscriptions[] = [
            'type' => self::TYPE_BROADCAST,
            'event' => $event,
            'callback' => $callback,
        ];

        return $this;
    }

    public function onPresence(callable $callback): self
    {
        $this->subscriptions[] = [
            'type' => self::TYPE_PRESENCE,
            'event' => 'presence',
            'callback' => $callback,
        ];

        return $this;
    }

    public function onPostgresChange(string $event, string $table, ?callable $callback = null): self
    {
        $this->subscriptions[] = [
            'type' => self::TYPE_POSTGRES_CHANGES,
            'event' => $event,
            'table' => $table,
            'schema' => $this->config['schema'] ?? 'public',
            'callback' => $callback,
        ];

        return $this;
    }

    public function trackPresence(string $user, array $metadata = []): void
    {
        $this->presenceState[$user] = array_merge($metadata, [
            'user' => $user,
            'joined_at' => now()->toIso8601String(),
            'channel' => $this->name,
        ]);
    }

    public function untrackPresence(string $user): void
    {
        unset($this->presenceState[$user]);
    }

    public function getPresence(): array
    {
        return $this->presenceState;
    }

    public function getSubscriptions(): array
    {
        return $this->subscriptions;
    }

    public function toRealtimePayload(): array
    {
        return [
            'channel' => $this->name,
            'config' => [
                'broadcast' => ['acknowledge' => true],
                'presence' => ['key' => $this->key],
                'private' => $this->config['private'] ?? false,
            ],
            'subscriptions' => array_map(fn ($sub) => [
                'type' => $sub['type'],
                'event' => $sub['event'],
                'table' => $sub['table'] ?? null,
                'schema' => $sub['schema'] ?? 'public',
            ], $this->subscriptions),
        ];
    }

    public static function forChat(string $conversationId, string $projectRef, string $key): self
    {
        return new self("chat:{$conversationId}", $projectRef, $key, [
            'private' => true,
            'schema' => 'public',
        ]);
    }

    public static function forProject(string $projectId, string $projectRef, string $key): self
    {
        return new self("project:{$projectId}", $projectRef, $key, [
            'private' => false,
            'schema' => 'public',
        ]);
    }

    public static function forNotifications(string $userId, string $projectRef, string $key): self
    {
        return new self("notifications:{$userId}", $projectRef, $key, [
            'private' => true,
            'schema' => 'public',
        ]);
    }

    public static function forTeam(string $teamId, string $projectRef, string $key): self
    {
        return new self("team:{$teamId}", $projectRef, $key, [
            'private' => true,
            'schema' => 'public',
        ]);
    }

    public static function forUser(string $userId, string $projectRef, string $key): self
    {
        return new self("user:{$userId}", $projectRef, $key, [
            'private' => true,
            'schema' => 'public',
        ]);
    }
}
