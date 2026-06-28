<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProjectService
{
    private const CACHE_TTL_PROJECTS = 900;

    private const CACHE_TTL_STATS = 300;

    public function __construct(
        private readonly CacheService $cacheService,
    ) {}

    public function create(User $user, array $data): Project
    {
        return DB::transaction(function () use ($user, $data) {
            $project = $user->projects()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'slug' => $this->generateUniqueSlug($data['name']),
                'language' => $data['language'] ?? null,
                'framework' => $data['framework'] ?? null,
                'files' => $data['files'] ?? [],
                'structure' => $data['structure'] ?? [],
                'status' => $data['status'] ?? Project::STATUS_DRAFT,
            ]);

            $this->cacheService->invalidateUser($user);

            Log::info('project.created', [
                'project_id' => $project->id,
                'user_id' => $user->id,
            ]);

            return $this->loadWithRelations($project);
        });
    }

    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            if (isset($data['name']) && $data['name'] !== $project->name) {
                $data['slug'] = $this->generateUniqueSlug($data['name'], $project->id);
            }

            $project->update($data);
            $this->cacheService->invalidateProject($project);

            Log::info('project.updated', [
                'project_id' => $project->id,
                'user_id' => $project->user_id,
            ]);

            return $this->loadWithRelations($project);
        });
    }

    public function archive(Project $project): void
    {
        $project->markAsArchived();
        $this->cacheService->invalidateProject($project);

        Log::info('project.archived', [
            'project_id' => $project->id,
        ]);
    }

    public function delete(Project $project): void
    {
        $project->delete();
        $this->cacheService->invalidateProject($project);

        Log::info('project.deleted', [
            'project_id' => $project->id,
        ]);
    }

    public function forceDelete(Project $project): void
    {
        $project->forceDelete();
        $this->cacheService->invalidateProject($project);

        Log::info('project.force_deleted', [
            'project_id' => $project->id,
        ]);
    }

    public function restore(Project $project): void
    {
        $project->restore();
        $this->cacheService->invalidateProject($project);

        Log::info('project.restored', [
            'project_id' => $project->id,
        ]);
    }

    public function getUserProjects(User $user, array $filters = []): Collection
    {
        $query = $user->projects()
            ->with('user:id,name,email')
            ->select('id', 'user_id', 'name', 'slug', 'language', 'framework', 'status', 'last_accessed_at', 'created_at', 'updated_at');

        if (! empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (! empty($filters['language'])) {
            $query->byLanguage($filters['language']);
        }

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        $sortField = in_array($filters['sort'] ?? 'updated_at', ['name', 'created_at', 'updated_at', 'last_accessed_at'])
            ? $filters['sort']
            : 'updated_at';
        $sortDir = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortField, $sortDir)->get();
    }

    public function findProject(User $user, string $projectId): ?Project
    {
        return $user->projects()
            ->with('user:id,name,email')
            ->find($projectId);
    }

    public function findBySlug(User $user, string $slug): ?Project
    {
        return $user->projects()
            ->with('user:id,name,email')
            ->where('slug', $slug)
            ->first();
    }

    public function generateUniqueSlug(string $name, ?string $excludeId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $count = 0;

        while (true) {
            $query = Project::where('slug', $slug);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (! $query->exists()) {
                break;
            }

            $count++;
            $slug = $original.'-'.$count;
        }

        return $slug;
    }

    public function addFiles(Project $project, array $files): Project
    {
        $existing = $project->files ?? [];
        $merged = array_merge($existing, $files);

        $project->update(['files' => $merged]);
        $this->cacheService->invalidateProject($project);

        return $this->loadWithRelations($project);
    }

    public function removeFile(Project $project, string $filePath): Project
    {
        $files = array_filter($project->files ?? [], fn (array $file) => ($file['path'] ?? '') !== $filePath);

        $project->update(['files' => array_values($files)]);
        $this->cacheService->invalidateProject($project);

        return $this->loadWithRelations($project);
    }

    public function updateStructure(Project $project, array $structure): Project
    {
        $project->update(['structure' => $structure]);
        $this->cacheService->invalidateProject($project);

        return $this->loadWithRelations($project);
    }

    public function access(Project $project): void
    {
        $project->touchLastAccessed();
    }

    public function duplicate(Project $project, ?User $owner = null): Project
    {
        return DB::transaction(function () use ($project, $owner) {
            $newProject = $project->replicate(['slug', 'last_accessed_at']);
            $newProject->user_id = $owner?->id ?? $project->user_id;
            $newProject->name = $project->name.' (Copy)';
            $newProject->slug = $this->generateUniqueSlug($newProject->name);
            $newProject->status = Project::STATUS_DRAFT;
            $newProject->save();

            $this->cacheService->invalidateUser($newProject->user);

            Log::info('project.duplicated', [
                'original_id' => $project->id,
                'new_id' => $newProject->id,
            ]);

            return $this->loadWithRelations($newProject);
        });
    }

    public function getRecentProjects(User $user, int $limit = 5): Collection
    {
        return $user->projects()
            ->active()
            ->select('id', 'user_id', 'name', 'slug', 'language', 'framework', 'status', 'last_accessed_at', 'created_at', 'updated_at')
            ->whereNotNull('last_accessed_at')
            ->orderBy('last_accessed_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getProjectStats(User $user): array
    {
        return $this->cacheService->getProjectStats($user);
    }

    private function loadWithRelations(Project $project): Project
    {
        return $project->load('user:id,name,email');
    }
}
