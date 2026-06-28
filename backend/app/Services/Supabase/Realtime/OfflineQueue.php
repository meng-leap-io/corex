<?php

declare(strict_types=1);

namespace App\Services\Supabase\Realtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OfflineQueue
{
    private const QUEUE_PREFIX = 'realtime_offline_';

    private const INDEX_KEY = 'realtime_offline_index';

    public function __construct(
        private readonly int $maxQueueSize = 1000,
    ) {}

    public function enqueue(string $channel, string $event, array $payload, string $userId = 'anonymous'): void
    {
        $message = [
            'id' => uniqid('rtq_', true),
            'channel' => $channel,
            'event' => $event,
            'payload' => $payload,
            'user_id' => $userId,
            'timestamp' => now()->toIso8601String(),
            'retry_count' => 0,
        ];

        $queueKey = self::QUEUE_PREFIX . $userId;
        $queue = $this->getQueue($userId);

        if (count($queue) >= $this->maxQueueSize) {
            array_shift($queue);
        }

        $queue[] = $message;

        Cache::put($queueKey, $queue, now()->addDays(7));

        $this->addToIndex($userId);

        Log::info('realtime.offline.queued', [
            'channel' => $channel,
            'event' => $event,
            'user_id' => $userId,
        ]);
    }

    public function dequeue(string $userId = 'anonymous'): ?array
    {
        $queue = $this->getQueue($userId);

        if (empty($queue)) {
            return null;
        }

        return array_shift($queue);
    }

    public function dequeueAll(string $userId = 'anonymous'): array
    {
        $queue = $this->getQueue($userId);
        $this->clear($userId);

        return $queue;
    }

    public function replay(string $userId, callable $handler): int
    {
        $queue = $this->dequeueAll($userId);
        $replayed = 0;

        foreach ($queue as $message) {
            try {
                $handler($message);
                $replayed++;
            } catch (\Throwable $e) {
                Log::warning('realtime.offline.replay_failed', [
                    'message_id' => $message['id'],
                    'error' => $e->getMessage(),
                ]);

                $message['retry_count']++;
                $this->enqueue(
                    $message['channel'],
                    $message['event'],
                    $message['payload'],
                    $userId,
                );
            }
        }

        Log::info('realtime.offline.replayed', [
            'user_id' => $userId,
            'total' => count($queue),
            'replayed' => $replayed,
            'failed' => count($queue) - $replayed,
        ]);

        return $replayed;
    }

    public function getQueue(string $userId = 'anonymous'): array
    {
        return Cache::get(self::QUEUE_PREFIX . $userId, []);
    }

    public function queueSize(string $userId = 'anonymous'): int
    {
        return count($this->getQueue($userId));
    }

    public function clear(string $userId = 'anonymous'): void
    {
        Cache::forget(self::QUEUE_PREFIX . $userId);
        $this->removeFromIndex($userId);
    }

    public function hasPending(string $userId = 'anonymous'): bool
    {
        return $this->queueSize($userId) > 0;
    }

    public function getAllQueuedUsers(): array
    {
        return Cache::get(self::INDEX_KEY, []);
    }

    public function pruneExpired(int $maxAgeHours = 24): int
    {
        $pruned = 0;

        foreach ($this->getAllQueuedUsers() as $userId) {
            $queue = $this->getQueue($userId);

            $queue = array_filter($queue, function ($message) use ($maxAgeHours) {
                $age = now()->diffInHours($message['timestamp']);

                return $age < $maxAgeHours;
            });

            if (empty($queue)) {
                $this->clear($userId);
                $pruned++;
            } else {
                Cache::put(self::QUEUE_PREFIX . $userId, array_values($queue), now()->addDays(7));
            }
        }

        return $pruned;
    }

    private function addToIndex(string $userId): void
    {
        $index = $this->getAllQueuedUsers();

        if (!in_array($userId, $index, true)) {
            $index[] = $userId;
            Cache::put(self::INDEX_KEY, $index, now()->addDays(7));
        }
    }

    private function removeFromIndex(string $userId): void
    {
        $index = $this->getAllQueuedUsers();
        $index = array_values(array_filter($index, fn ($id) => $id !== $userId));
        Cache::put(self::INDEX_KEY, $index, now()->addDays(7));
    }
}
