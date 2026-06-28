<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class CacheService
{
    private const TTL_5MIN = 300;

    private const TTL_15MIN = 900;

    private const TTL_1HOUR = 3600;

    private const TTL_1DAY = 86400;

    public const TAG_USER = 'user';

    public const TAG_PROJECT = 'project';

    public const TAG_AI = 'ai';

    public const TAG_USAGE = 'usage';

    public const TAG_MODELS = 'models';

    public function getUserProjects(User $user, array $filters = []): mixed
    {
        $key = $this->projectListKey($user, $filters);

        return Cache::tags([self::TAG_USER, self::TAG_PROJECT])
            ->remember($key, self::TTL_15MIN, function () use ($user, $filters) {
                return app(ProjectService::class)->getUserProjects($user, $filters);
            });
    }

    public function getProjectStats(User $user): mixed
    {
        $key = "project_stats:{$user->id}";

        return Cache::tags([self::TAG_USER, self::TAG_PROJECT])
            ->remember($key, self::TTL_5MIN, function () use ($user) {
                return app(ProjectService::class)->getProjectStats($user);
            });
    }

    public function getRecentProjects(User $user, int $limit = 5): mixed
    {
        $key = "recent_projects:{$user->id}:{$limit}";

        return Cache::tags([self::TAG_USER, self::TAG_PROJECT])
            ->remember($key, self::TTL_5MIN, function () use ($user, $limit) {
                return app(ProjectService::class)->getRecentProjects($user, $limit);
            });
    }

    public function getAiUsageStats(User $user, ?string $from = null, ?string $to = null): mixed
    {
        $key = "ai_usage:{$user->id}:{$from}:{$to}";

        return Cache::tags([self::TAG_USER, self::TAG_USAGE])
            ->remember($key, self::TTL_5MIN, function () use ($user, $from, $to) {
                return app(AIService::class)->getUsageStats($user, $from, $to);
            });
    }

    public function getDailyUsage(User $user, int $days = 30): mixed
    {
        $key = "ai_daily:{$user->id}:{$days}";

        return Cache::tags([self::TAG_USER, self::TAG_USAGE])
            ->remember($key, self::TTL_15MIN, function () use ($user, $days) {
                return $user->aiUsageLogs()
                    ->selectRaw('DATE(created_at) as date, SUM(prompt_tokens + completion_tokens) as total_tokens, SUM(cost) as total_cost, COUNT(*) as total_requests, COUNT(CASE WHEN success = false THEN 1 END) as failed_requests')
                    ->where('created_at', '>=', now()->subDays($days))
                    ->groupBy('date')
                    ->orderBy('date')
                    ->get();
            });
    }

    public function getModelsList(): mixed
    {
        return Cache::tags([self::TAG_MODELS])
            ->remember('ai_models_list', self::TTL_1HOUR, function () {
                return config('ai.models', []);
            });
    }

    public function getUser(User $userId): mixed
    {
        $key = "user:{$userId}";

        return Cache::tags([self::TAG_USER])
            ->remember($key, self::TTL_1HOUR, function () use ($userId) {
                return User::find($userId);
            });
    }

    public function getProject(string $projectId): mixed
    {
        $key = "project:{$projectId}";

        return Cache::tags([self::TAG_PROJECT])
            ->remember($key, self::TTL_15MIN, function () use ($projectId) {
                return Project::with('user')->find($projectId);
            });
    }

    public function invalidateUser(User $user): void
    {
        Cache::tags([self::TAG_USER])->flush();
    }

    public function invalidateProject(Project $project): void
    {
        Cache::tags([self::TAG_PROJECT])->flush();
    }

    public function invalidateAiUsage(User $user): void
    {
        Cache::tags([self::TAG_USAGE, self::TAG_AI])->flush();
    }

    public function invalidateAll(): void
    {
        Cache::tags([
            self::TAG_USER,
            self::TAG_PROJECT,
            self::TAG_AI,
            self::TAG_USAGE,
            self::TAG_MODELS,
        ])->flush();
    }

    private function projectListKey(User $user, array $filters): string
    {
        $parts = ["projects:{$user->id}"];
        if (! empty($filters['status'])) {
            $parts[] = "status:{$filters['status']}";
        }
        if (! empty($filters['language'])) {
            $parts[] = "lang:{$filters['language']}";
        }
        if (! empty($filters['search'])) {
            $parts[] = 'search:'.md5($filters['search']);
        }
        $parts[] = ($filters['sort'] ?? 'updated_at').':'.($filters['direction'] ?? 'desc');

        return implode(':', $parts);
    }
}
