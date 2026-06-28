<?php

declare(strict_types=1);

namespace App\Events\Analytics;

use App\Services\Analytics\RealtimeAnalyticsChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AlertTriggered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly string $type,
        public readonly string $message,
        public readonly array $data = [],
        public readonly ?string $severity = 'warning',
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(RealtimeAnalyticsChannel::alerts()->name()),
        ];
    }

    public function broadcastAs(): string
    {
        return 'analytics.alert.triggered';
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'data' => $this->data,
            'severity' => $this->severity,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
