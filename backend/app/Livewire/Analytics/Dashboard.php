<?php

declare(strict_types=1);

namespace App\Livewire\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\FeatureUsage;
use App\Models\PageView;
use App\Models\PerformanceSnapshot;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\PerformanceService;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class Dashboard extends Component
{
    public string $period = '7d';

    public array $overview = [];

    public array $eventBreakdown = [];

    public array $topPages = [];

    public array $featureUsage = [];

    public array $performanceHistory = [];

    public array $latestSnapshot = [];

    public array $errorSummary = [];

    public bool $loading = true;

    protected AnalyticsService $analytics;

    protected PerformanceService $performance;

    public function boot(
        AnalyticsService $analytics,
        PerformanceService $performance,
    ): void {
        $this->analytics = $analytics;
        $this->performance = $performance;
    }

    public function mount(): void
    {
        $this->loadData();
    }

    public function updatedPeriod(): void
    {
        $this->loadData();
    }

    public function refresh(): void
    {
        $this->loadData();
        $this->dispatch('analytics-refreshed');
    }

    public function recordSnapshot(): void
    {
        $snapshot = $this->performance->recordSnapshot();
        $this->latestSnapshot = $snapshot->toArray();
        $this->dispatch('snapshot-recorded', message: 'Performance snapshot recorded.');
        $this->loadData();
    }

    protected function loadData(): void
    {
        $this->loading = true;

        $since = match ($this->period) {
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(7),
        };

        $this->overview = [
            'total_events' => AnalyticsEvent::where('created_at', '>=', $since)->count(),
            'unique_users' => AnalyticsEvent::where('created_at', '>=', $since)->distinct('user_id')->count('user_id'),
            'total_page_views' => PageView::where('created_at', '>=', $since)->count(),
            'avg_response_time' => round(PageView::where('created_at', '>=', $since)->avg('duration_ms') ?? 0, 2),
            'total_errors' => PageView::where('created_at', '>=', $since)->where('status_code', '>=', 400)->count(),
            'feature_usage_count' => FeatureUsage::where('created_at', '>=', $since)->count(),
            'request_rate' => $this->performance->getRequestRatePerMin(),
            'p95_response' => round($this->performance->getPercentileResponseTime(0.95), 2),
            'p99_response' => round($this->performance->getPercentileResponseTime(0.99), 2),
            'error_count_5m' => $this->performance->getErrorCount(),
        ];

        $this->eventBreakdown = AnalyticsEvent::where('created_at', '>=', $since)
            ->select('event_type', DB::raw('COUNT(*) as count'))
            ->groupBy('event_type')
            ->orderByDesc('count')
            ->limit(15)
            ->get()
            ->toArray();

        $this->topPages = PageView::where('created_at', '>=', $since)
            ->select('path', DB::raw('COUNT(*) as views'), DB::raw('AVG(duration_ms) as avg_duration'))
            ->groupBy('path')
            ->orderByDesc('views')
            ->limit(15)
            ->get()
            ->toArray();

        $this->featureUsage = FeatureUsage::where('created_at', '>=', $since)
            ->select('feature', 'action', DB::raw('COUNT(*) as count'), DB::raw('AVG(duration_ms) as avg_duration'))
            ->groupBy('feature', 'action')
            ->orderByDesc('count')
            ->limit(20)
            ->get()
            ->toArray();

        $this->performanceHistory = PageView::where('created_at', '>=', $since)
            ->select(
                DB::raw("date_trunc('hour', created_at) as hour"),
                DB::raw('COUNT(*) as requests'),
                DB::raw('AVG(duration_ms) as avg_duration'),
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->limit(168)
            ->get()
            ->toArray();

        $this->latestSnapshot = PerformanceSnapshot::latest('recorded_at')->first()?->toArray() ?? [];

        $this->errorSummary = PageView::where('created_at', '>=', $since)
            ->where('status_code', '>=', 400)
            ->select('path', 'status_code', DB::raw('COUNT(*) as count'))
            ->groupBy('path', 'status_code')
            ->orderByDesc('count')
            ->limit(15)
            ->get()
            ->toArray();

        $this->loading = false;
    }

    public function render()
    {
        return view('livewire.analytics.dashboard');
    }
}
