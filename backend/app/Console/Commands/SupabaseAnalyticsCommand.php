<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AnalyticsEvent;
use App\Models\CustomMetric;
use App\Models\FeatureUsage;
use App\Models\PageView;
use App\Models\PerformanceSnapshot;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\PerformanceService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SupabaseAnalyticsCommand extends Command
{
    protected $signature = 'supabase:analytics
        {action : Action to perform (aggregate|prune|report|snapshot|refresh|status)}
        {--period=7d : Period for report (24h|7d|30d|90d)}
        {--retention=90 : Retention days for prune}
        {--date= : Specific date for report (Y-m-d)}';

    protected $description = 'Manage analytics data, aggregation, and pruning';

    public function handle(
        AnalyticsService $analytics,
        PerformanceService $performance,
    ): int {
        $action = $this->argument('action');

        return match ($action) {
            'aggregate' => $this->aggregate($analytics),
            'prune' => $this->prune($analytics),
            'report' => $this->report($analytics),
            'snapshot' => $this->snapshot($performance),
            'refresh' => $this->refresh($analytics),
            'status' => $this->status(),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    protected function aggregate(AnalyticsService $analytics): int
    {
        $this->info('Refreshing materialized views...');
        $analytics->refreshMaterializedViews();
        $this->info('Materialized views refreshed.');

        return self::SUCCESS;
    }

    protected function prune(AnalyticsService $analytics): int
    {
        $retention = (int) $this->option('retention');
        $this->info("Pruning data older than {$retention} days...");

        $deleted = $analytics->prune($retention);

        foreach ($deleted as $table => $count) {
            $this->line("  {$table}: {$count} rows deleted");
        }

        $this->info('Prune complete.');

        return self::SUCCESS;
    }

    protected function report(AnalyticsService $analytics): int
    {
        $dateStr = $this->option('date');
        $period = $this->option('period');

        if ($dateStr) {
            $date = Carbon::parse($dateStr);
            $report = $analytics->getDailyReport($date);
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Date', $report['date']],
                    ['Events', $report['events']],
                    ['Page Views', $report['page_views']],
                    ['Unique Users', $report['unique_users']],
                    ['Avg Response Time', round($report['avg_response_time'], 2) . 'ms'],
                    ['Errors', $report['errors']],
                ]
            );

            if (count($report['top_events']) > 0) {
                $this->newLine();
                $this->info('Top Events:');
                $this->table(['Event Type', 'Count'], $report['top_events']->toArray());
            }
        } else {
            $data = $analytics->getDashboardData($period);
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Events', $data['overview']['total_events']],
                    ['Unique Users', $data['overview']['unique_users']],
                    ['Page Views', $data['overview']['total_page_views']],
                    ['Avg Response Time', round($data['overview']['avg_response_time'], 2) . 'ms'],
                    ['Errors', $data['overview']['total_errors']],
                    ['Feature Usage', $data['overview']['feature_usage_count']],
                ]
            );
        }

        return self::SUCCESS;
    }

    protected function snapshot(PerformanceService $performance): int
    {
        $this->info('Recording performance snapshot...');
        $snapshot = $performance->recordSnapshot();
        $this->info("Snapshot {$snapshot->id} recorded at {$snapshot->recorded_at}.");

        return self::SUCCESS;
    }

    protected function refresh(AnalyticsService $analytics): int
    {
        $this->info('Refreshing materialized views...');
        $analytics->refreshMaterializedViews();
        $this->info('Done.');

        return self::SUCCESS;
    }

    protected function status(): int
    {
        $this->info('Analytics Status');
        $this->newLine();

        $driver = DB::connection()->getDriverName();

        $tables = [
            'analytics_events' => AnalyticsEvent::count(),
            'feature_usage' => FeatureUsage::count(),
            'page_views' => PageView::count(),
            'custom_metrics' => CustomMetric::count(),
            'performance_snapshots' => PerformanceSnapshot::count(),
        ];

        $this->table(
            ['Table', 'Row Count'],
            collect($tables)->map(fn ($count, $name) => [$name, number_format($count)])->toArray()
        );

        $this->newLine();

        $oldest = AnalyticsEvent::oldest()->first();
        $newest = AnalyticsEvent::latest()->first();

        if ($oldest && $newest) {
            $this->line("Date range: {$oldest->created_at} to {$newest->created_at}");
        }

        if ($driver === 'pgsql' || $driver === 'supabase') {
            $this->newLine();
            $this->line('PostgreSQL materialized views:');

            $views = DB::select("
                SELECT schemaname, matviewname, pg_size_pretty(pg_total_relation_size(schemaname||'.'||matviewname)) as size
                FROM pg_matviews
                WHERE matviewname IN ('mv_daily_metrics', 'mv_feature_usage_daily')
            ");
            foreach ($views as $view) {
                $this->line("  {$view->matviewname}: {$view->size}");
            }
        }

        return self::SUCCESS;
    }
}
