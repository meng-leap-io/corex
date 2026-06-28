<?php

declare(strict_types=1);

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\CustomMetric;
use App\Models\FeatureUsage;
use App\Models\PageView;
use App\Models\PerformanceSnapshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    protected const int CACHE_TTL = 300;

    public function trackEvent(
        string $eventType,
        ?string $category = null,
        ?string $label = null,
        ?float $value = null,
        ?array $metadata = null,
        ?string $userId = null,
        ?string $sessionId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AnalyticsEvent {
        $data = [
            'event_type' => $eventType,
            'category' => $category,
            'label' => $label,
            'value' => $value,
            'metadata' => $metadata ?? [],
            'session_id' => $sessionId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ];

        if ($userId) {
            $data['user_id'] = $userId;
        }

        return AnalyticsEvent::create($data);
    }

    public function trackFeatureUsage(
        string $feature,
        string $action,
        bool $success = true,
        ?float $durationMs = null,
        ?array $context = null,
        ?string $userId = null,
    ): FeatureUsage {
        $data = [
            'feature' => $feature,
            'action' => $action,
            'success' => $success,
            'duration_ms' => $durationMs,
            'context' => $context ?? [],
        ];

        if ($userId) {
            $data['user_id'] = $userId;
        }

        return FeatureUsage::create($data);
    }

    public function recordPageView(
        Request $request,
        float $durationMs,
        int $statusCode = 200,
        ?float $queryTimeMs = null,
        ?int $memoryBytes = null,
        ?array $queryLog = null,
    ): PageView {
        return PageView::create([
            'user_id' => $request->user()?->id,
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
            'query_time_ms' => $queryTimeMs,
            'memory_bytes' => $memoryBytes,
            'query_log' => $queryLog,
            'session_id' => $request->session()?->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('referer'),
        ]);
    }

    public function recordCustomMetric(
        string $key,
        float $value,
        string $type = CustomMetric::TYPE_GAUGE,
        ?array $tags = null,
        ?array $metadata = null,
        ?string $source = null,
    ): CustomMetric {
        return CustomMetric::create([
            'metric_key' => $key,
            'metric_type' => $type,
            'value' => $value,
            'tags' => $tags ?? [],
            'metadata' => $metadata ?? [],
            'source' => $source,
        ]);
    }

    public function recordPerformanceSnapshot(array $data): PerformanceSnapshot
    {
        return PerformanceSnapshot::create($data);
    }

    public function getDashboardData(string $period = '7d'): array
    {
        $cacheKey = "analytics:dashboard:{$period}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($period) {
            $since = match ($period) {
                '24h' => now()->subDay(),
                '7d' => now()->subDays(7),
                '30d' => now()->subDays(30),
                '90d' => now()->subDays(90),
                default => now()->subDays(7),
            };

            return [
                'overview' => $this->getOverview($since),
                'events' => $this->getEventBreakdown($since),
                'pages' => $this->getTopPages($since),
                'features' => $this->getFeatureUsage($since),
                'performance' => $this->getPerformanceStats($since),
                'errors' => $this->getErrorSummary($since),
            ];
        });
    }

    protected function getOverview(Carbon $since): array
    {
        return [
            'total_events' => AnalyticsEvent::where('created_at', '>=', $since)->count(),
            'unique_users' => AnalyticsEvent::where('created_at', '>=', $since)->distinct('user_id')->count('user_id'),
            'total_page_views' => PageView::where('created_at', '>=', $since)->count(),
            'avg_response_time' => PageView::where('created_at', '>=', $since)->avg('duration_ms'),
            'total_errors' => PageView::where('created_at', '>=', $since)->where('status_code', '>=', 400)->count(),
            'feature_usage_count' => FeatureUsage::where('created_at', '>=', $since)->count(),
        ];
    }

    protected function getEventBreakdown(Carbon $since): array
    {
        return AnalyticsEvent::where('created_at', '>=', $since)
            ->select('event_type', DB::raw('COUNT(*) as count'))
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->toArray();
    }

    protected function getTopPages(Carbon $since): array
    {
        return PageView::where('created_at', '>=', $since)
            ->select('path', DB::raw('COUNT(*) as views'), DB::raw('AVG(duration_ms) as avg_duration'))
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(20)
            ->get()
            ->toArray();
    }

    protected function getFeatureUsage(Carbon $since): array
    {
        return FeatureUsage::where('created_at', '>=', $since)
            ->select('feature', 'action', DB::raw('COUNT(*) as count'), DB::raw('AVG(duration_ms) as avg_duration'))
            ->groupBy('feature', 'action')
            ->orderByDesc('count')
            ->limit(30)
            ->get()
            ->toArray();
    }

    protected function getPerformanceStats(Carbon $since): array
    {
        return [
            'hourly' => PageView::where('created_at', '>=', $since)
                ->select(
                    DB::raw("date_trunc('hour', created_at) as hour"),
                    DB::raw('COUNT(*) as requests'),
                    DB::raw('AVG(duration_ms) as avg_duration'),
                    DB::raw("percentile_cont(0.95) WITHIN GROUP (ORDER BY duration_ms) as p95"),
                    DB::raw("percentile_cont(0.99) WITHIN GROUP (ORDER BY duration_ms) as p99"),
                )
                ->groupBy('hour')
                ->orderBy('hour')
                ->get()
                ->toArray(),
            'snapshots' => PerformanceSnapshot::where('recorded_at', '>=', $since)
                ->orderByDesc('recorded_at')
                ->limit(100)
                ->get()
                ->toArray(),
        ];
    }

    protected function getErrorSummary(Carbon $since): array
    {
        return PageView::where('created_at', '>=', $since)
            ->where('status_code', '>=', 400)
            ->select('path', 'status_code', DB::raw('COUNT(*) as count'))
            ->groupBy('path', 'status_code')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->toArray();
    }

    public function getDailyReport(Carbon $date): array
    {
        $start = $date->copy()->startOfDay();
        $end = $date->copy()->endOfDay();

        return [
            'date' => $date->toDateString(),
            'events' => AnalyticsEvent::whereBetween('created_at', [$start, $end])->count(),
            'page_views' => PageView::whereBetween('created_at', [$start, $end])->count(),
            'unique_users' => AnalyticsEvent::whereBetween('created_at', [$start, $end])->distinct('user_id')->count('user_id'),
            'avg_response_time' => PageView::whereBetween('created_at', [$start, $end])->avg('duration_ms'),
            'errors' => PageView::whereBetween('created_at', [$start, $end])->where('status_code', '>=', 400)->count(),
            'top_events' => AnalyticsEvent::whereBetween('created_at', [$start, $end])
                ->select('event_type', DB::raw('COUNT(*) as count'))
                ->groupBy('event_type')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
            'top_features' => FeatureUsage::whereBetween('created_at', [$start, $end])
                ->select('feature', DB::raw('COUNT(*) as count'))
                ->groupBy('feature')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ];
    }

    public function getUserActivity(string $userId, int $days = 30): array
    {
        $since = now()->subDays($days);

        return [
            'events' => AnalyticsEvent::forUser($userId)->since($since)->count(),
            'page_views' => PageView::forUser($userId)->since($since)->count(),
            'features' => FeatureUsage::forUser($userId)->since($since)
                ->select('feature', DB::raw('COUNT(*) as count'))
                ->groupBy('feature')
                ->orderByDesc('count')
                ->get(),
            'daily_activity' => AnalyticsEvent::forUser($userId)->since($since)
                ->select(DB::raw('created_at::date as date'), DB::raw('COUNT(*) as count'))
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ];
    }

    public function prune(int $retentionDays = 90): array
    {
        $cutoff = now()->subDays($retentionDays);

        $deleted = [
            'analytics_events' => AnalyticsEvent::where('created_at', '<', $cutoff)->delete(),
            'feature_usage' => FeatureUsage::where('created_at', '<', $cutoff)->delete(),
            'page_views' => PageView::where('created_at', '<', $cutoff)->delete(),
            'custom_metrics' => CustomMetric::where('recorded_at', '<', $cutoff)->delete(),
            'performance_snapshots' => PerformanceSnapshot::where('recorded_at', '<', $cutoff)->delete(),
        ];

        return $deleted;
    }

    public function refreshMaterializedViews(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql' || $driver === 'supabase') {
            DB::unprepared('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_daily_metrics');
            DB::unprepared('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_feature_usage_daily');
        }
    }
}
