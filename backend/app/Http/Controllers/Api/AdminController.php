<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\AiUsageLog;
use App\Models\AnalyticsEvent;
use App\Models\FeatureUsage;
use App\Models\PageView;
use App\Models\PerformanceSnapshot;
use App\Models\User;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\PerformanceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly PerformanceService $performance,
    ) {}

    public function stats(Request $request): JsonResponse
    {
        $period = $request->get('period', '7d');

        $data = $this->analytics->getDashboardData($period);

        return response()->json([
            'data' => $data,
            'period' => $period,
        ]);
    }

    public function usage(Request $request): JsonResponse
    {
        $period = $request->get('period', '7d');
        $since = match ($period) {
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(7),
        };

        $aiUsage = AiUsageLog::where('created_at', '>=', $since)
            ->select(
                DB::raw("date_trunc('day', created_at) as date"),
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('SUM(prompt_tokens) as total_prompt_tokens'),
                DB::raw('SUM(completion_tokens) as total_completion_tokens'),
                DB::raw('SUM(cost) as total_cost'),
                DB::raw('AVG(duration) as avg_duration_ms'),
                DB::raw("COUNT(*) FILTER (WHERE success = false) as error_count"),
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $byProvider = AiUsageLog::where('created_at', '>=', $since)
            ->select('provider', DB::raw('COUNT(*) as count'), DB::raw('SUM(cost) as total_cost'))
            ->groupBy('provider')
            ->orderByDesc('count')
            ->get();

        $byModel = AiUsageLog::where('created_at', '>=', $since)
            ->select('model', DB::raw('COUNT(*) as count'), DB::raw('SUM(cost) as total_cost'))
            ->groupBy('model')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $topUsers = AiUsageLog::where('created_at', '>=', $since)
            ->select('user_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(cost) as total_cost'))
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->load('user:id,name,email');

        return response()->json([
            'data' => [
                'daily' => $aiUsage,
                'by_provider' => $byProvider,
                'by_model' => $byModel,
                'top_users' => $topUsers,
            ],
            'period' => $period,
        ]);
    }

    public function health(): JsonResponse
    {
        $services = $this->performance->getServiceStatus();
        $snapshot = $this->performance->recordSnapshot();

        return response()->json([
            'data' => [
                'status' => collect($services)->every(fn ($s) => $s['status'] === 'healthy') ? 'healthy' : 'degraded',
                'services' => $services,
                'performance' => [
                    'request_rate_per_min' => $this->performance->getRequestRatePerMin(),
                    'avg_response_time_ms' => $this->performance->getAverageResponseTime(),
                    'p95_response_time_ms' => $this->performance->getPercentileResponseTime(0.95),
                    'p99_response_time_ms' => $this->performance->getPercentileResponseTime(0.99),
                    'error_count_5m' => $this->performance->getErrorCount(),
                ],
                'snapshot' => $snapshot,
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    public function analyticsEvents(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'user_id' => 'nullable|string|exists:users,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AnalyticsEvent::query();

        if (! empty($validated['event_type'])) {
            $query->ofType($validated['event_type']);
        }
        if (! empty($validated['category'])) {
            $query->inCategory($validated['category']);
        }
        if (! empty($validated['user_id'])) {
            $query->forUser($validated['user_id']);
        }
        if (! empty($validated['from'])) {
            $query->since($validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to']);
        }

        $events = $query->latest()->paginate($validated['per_page'] ?? 50);

        return response()->json($events);
    }

    public function featureUsage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'feature' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = FeatureUsage::query();

        if (! empty($validated['feature'])) {
            $query->forFeature($validated['feature']);
        }
        if (! empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to']);
        }

        $usage = $query->latest()->paginate($validated['per_page'] ?? 50);

        return response()->json($usage);
    }

    public function performanceSnapshots(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = PerformanceSnapshot::query();

        if (! empty($validated['from'])) {
            $query->where('recorded_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->where('recorded_at', '<=', $validated['to']);
        }

        $snapshots = $query->latest('recorded_at')->paginate($validated['per_page'] ?? 50);

        return response()->json($snapshots);
    }

    public function pageViews(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'nullable|string|max:500',
            'status_code' => 'nullable|integer|min:100|max:599',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = PageView::query();

        if (! empty($validated['path'])) {
            $query->forPath($validated['path']);
        }
        if (! empty($validated['status_code'])) {
            $query->where('status_code', $validated['status_code']);
        }
        if (! empty($validated['from'])) {
            $query->where('created_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->where('created_at', '<=', $validated['to']);
        }

        $views = $query->latest()->paginate($validated['per_page'] ?? 50);

        return response()->json($views);
    }
}
