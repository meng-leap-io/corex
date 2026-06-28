<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AIController extends Controller
{
    public function usage(Request $request): JsonResponse
    {
        try {
            $usage = $request->user()
                ->aiUsageLogs()
                ->select(
                    DB::raw('SUM(prompt_tokens + completion_tokens) as total_tokens'),
                    DB::raw('SUM(cost) as total_cost'),
                    DB::raw('COUNT(*) as total_requests'),
                    DB::raw('AVG(duration) as avg_duration_ms'),
                    'provider',
                    'model',
                )
                ->when($request->from, fn ($q, $v) => $q->byDateRange($v, $request->to ?? now()))
                ->groupBy('provider', 'model')
                ->orderByDesc('total_tokens')
                ->get()
                ->map(fn ($row) => [
                    'provider' => $row->provider,
                    'model' => $row->model,
                    'total_tokens' => (int) $row->total_tokens,
                    'total_cost' => (float) $row->total_cost,
                    'total_requests' => (int) $row->total_requests,
                    'avg_duration_ms' => (float) $row->avg_duration_ms,
                ]);

            return $this->success([
                'usage' => $usage,
                'summary' => [
                    'total_tokens' => $usage->sum('total_tokens'),
                    'total_cost' => round($usage->sum('total_cost'), 6),
                    'total_requests' => $usage->sum('total_requests'),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->logAndError(
                'ai_usage_failed',
                'Failed to retrieve AI usage.',
                $e,
                500,
            );
        }
    }

    public function dailyUsage(Request $request): JsonResponse
    {
        try {
            $days = min((int) ($request->input('days', 30)), 90);

            $daily = $request->user()
                ->aiUsageLogs()
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(prompt_tokens + completion_tokens) as total_tokens'),
                    DB::raw('SUM(cost) as total_cost'),
                    DB::raw('COUNT(*) as total_requests'),
                    DB::raw('COUNT(CASE WHEN success = false THEN 1 END) as failed_requests'),
                )
                ->where('created_at', '>=', now()->subDays($days))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'total_tokens' => (int) $row->total_tokens,
                    'total_cost' => (float) $row->total_cost,
                    'total_requests' => (int) $row->total_requests,
                    'failed_requests' => (int) $row->failed_requests,
                ]);

            return $this->success([
                'daily' => $daily,
                'days' => $days,
            ]);
        } catch (\Throwable $e) {
            return $this->logAndError(
                'ai_daily_usage_failed',
                'Failed to retrieve daily AI usage.',
                $e,
                500,
            );
        }
    }
}
