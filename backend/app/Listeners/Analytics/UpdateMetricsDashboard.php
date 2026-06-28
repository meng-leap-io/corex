<?php

declare(strict_types=1);

namespace App\Listeners\Analytics;

use App\Events\Analytics\MetricsUpdated;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UpdateMetricsDashboard
{
    public function handle(MetricsUpdated $event): void
    {
        Cache::put('analytics:dashboard:metrics', $event->metrics, 300);

        if ($event->userId !== null) {
            Cache::put("analytics:user:{$event->userId}:metrics", $event->metrics, 300);
        }

        Log::debug('Metrics dashboard updated', [
            'metric_keys' => array_keys($event->metrics),
            'user_id' => $event->userId,
        ]);
    }
}
