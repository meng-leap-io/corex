<?php

declare(strict_types=1);

namespace App\Events\Analytics;

use App\Services\Analytics\RealtimeAnalyticsChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MetricsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly array $metrics,
        public readonly ?string $userId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(RealtimeAnalyticsChannel::admin($this->userId)->name()),
        ];
    }

    public function broadcastAs(): string
    {
        return 'analytics.metrics.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'metrics' => $this->metrics,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
