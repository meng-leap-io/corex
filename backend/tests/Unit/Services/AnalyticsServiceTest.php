<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AnalyticsEvent;
use App\Models\FeatureUsage;
use App\Models\PageView;
use App\Models\PerformanceSnapshot;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\PerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsService $analytics;

    private PerformanceService $performance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analytics = $this->app->make(AnalyticsService::class);
        $this->performance = $this->app->make(PerformanceService::class);
    }

    public function test_track_event(): void
    {
        $event = $this->analytics->trackEvent(
            eventType: 'test_event',
            category: 'testing',
            label: 'unit_test',
            value: 42.0,
            metadata: ['key' => 'value'],
        );

        $this->assertDatabaseHas('analytics_events', [
            'id' => $event->id,
            'event_type' => 'test_event',
            'category' => 'testing',
            'label' => 'unit_test',
            'value' => 42.0,
        ]);
    }

    public function test_track_feature_usage(): void
    {
        $usage = $this->analytics->trackFeatureUsage(
            feature: 'code_generation',
            action: 'generate',
            success: true,
            durationMs: 1500.5,
            context: ['model' => 'gpt-4o'],
        );

        $this->assertDatabaseHas('feature_usage', [
            'id' => $usage->id,
            'feature' => 'code_generation',
            'action' => 'generate',
            'success' => true,
            'duration_ms' => 1500.5,
        ]);
    }

    public function test_record_custom_metric(): void
    {
        $metric = $this->analytics->recordCustomMetric(
            key: 'test_metric',
            value: 100.0,
            type: 'gauge',
            tags: ['env' => 'test'],
            source: 'phpunit',
        );

        $this->assertDatabaseHas('custom_metrics', [
            'id' => $metric->id,
            'metric_key' => 'test_metric',
            'metric_type' => 'gauge',
            'value' => 100.0,
            'source' => 'phpunit',
        ]);
    }

    public function test_get_dashboard_data(): void
    {
        AnalyticsEvent::factory()->count(10)->create([
            'event_type' => 'page_view',
            'created_at' => now()->subHours(2),
        ]);

        $data = $this->analytics->getDashboardData('7d');

        $this->assertArrayHasKey('overview', $data);
        $this->assertArrayHasKey('events', $data);
        $this->assertArrayHasKey('performance', $data);
        $this->assertEquals(10, $data['overview']['total_events']);
    }

    public function test_get_user_activity(): void
    {
        $user = \App\Models\User::factory()->create();

        AnalyticsEvent::factory()->count(5)->create([
            'user_id' => $user->id,
            'event_type' => 'login',
        ]);

        FeatureUsage::factory()->count(3)->create([
            'user_id' => $user->id,
            'feature' => 'chat',
        ]);

        $activity = $this->analytics->getUserActivity($user->id, 30);

        $this->assertEquals(5, $activity['events']);
        $this->assertEquals(3, $activity['features']->first()['count'] ?? 0);
    }

    public function test_prune_old_data(): void
    {
        AnalyticsEvent::factory()->create([
            'created_at' => now()->subDays(100),
        ]);
        AnalyticsEvent::factory()->create([
            'created_at' => now()->subDays(10),
        ]);

        $deleted = $this->analytics->prune(30);

        $this->assertEquals(1, $deleted['analytics_events']);
        $this->assertEquals(1, AnalyticsEvent::count());
    }

    public function test_record_performance_snapshot(): void
    {
        $snapshot = $this->performance->recordSnapshot();

        $this->assertDatabaseHas('performance_snapshots', [
            'id' => $snapshot->id,
        ]);
        $this->assertNotNull($snapshot->recorded_at);
    }

    public function test_get_percentile_response_time(): void
    {
        PageView::factory()->count(10)->create([
            'duration_ms' => 100,
            'created_at' => now()->subMinutes(2),
        ]);
        PageView::factory()->count(10)->create([
            'duration_ms' => 500,
            'created_at' => now()->subMinutes(2),
        ]);

        $p50 = $this->performance->getPercentileResponseTime(0.50);
        $p95 = $this->performance->getPercentileResponseTime(0.95);

        $this->assertGreaterThanOrEqual(100, $p50);
        $this->assertGreaterThanOrEqual(500, $p95);
    }

    public function test_get_error_count(): void
    {
        PageView::factory()->count(3)->create([
            'status_code' => 500,
            'created_at' => now()->subMinute(),
        ]);
        PageView::factory()->count(10)->create([
            'status_code' => 200,
            'created_at' => now()->subMinute(),
        ]);

        $errors = $this->performance->getErrorCount(5);

        $this->assertEquals(3, $errors);
    }

    public function test_daily_report(): void
    {
        AnalyticsEvent::factory()->count(5)->create([
            'event_type' => 'test_event',
            'created_at' => now(),
        ]);

        $report = $this->analytics->getDailyReport(now());

        $this->assertArrayHasKey('date', $report);
        $this->assertArrayHasKey('events', $report);
        $this->assertEquals(5, $report['events']);
    }
}
