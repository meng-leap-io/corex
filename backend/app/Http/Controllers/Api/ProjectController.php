<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Project::class, 'project');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $projects = $request->user()
                ->projects()
                ->when($request->status, fn ($q, $v) => $q->byStatus($v))
                ->when($request->language, fn ($q, $v) => $q->byLanguage($v))
                ->when($request->search, fn ($q, $v) => $q->search($v))
                ->orderBy('last_accessed_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return $this->success(
                data: ProjectResource::collection($projects),
                message: 'Projects retrieved successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'projects_index_failed',
                'Failed to retrieve projects.',
                $e,
                500,
            );
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
                'language' => ['nullable', 'string', 'max:50'],
                'framework' => ['nullable', 'string', 'max:50'],
                'files' => ['nullable', 'array'],
                'structure' => ['nullable', 'array'],
            ]);

            $project = $request->user()->projects()->create($validated);

            Log::info('project_created', [
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
            ]);

            return $this->success(
                data: new ProjectResource($project),
                message: 'Project created successfully.',
                code: 201,
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'project_create_failed',
                'Failed to create project.',
                $e,
                500,
            );
        }
    }

    public function show(Project $project): JsonResponse
    {
        try {
            $project->load(['conversations' => fn ($q) => $q->recent(5)]);
            $project->touchLastAccessed();

            return $this->success(
                data: new ProjectResource($project),
                message: 'Project retrieved successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'project_show_failed',
                'Failed to retrieve project.',
                $e,
                500,
                ['project_id' => $project->id],
            );
        }
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
                'language' => ['nullable', 'string', 'max:50'],
                'framework' => ['nullable', 'string', 'max:50'],
                'files' => ['nullable', 'array'],
                'structure' => ['nullable', 'array'],
                'status' => ['sometimes', 'string', 'in:'.implode(',', Project::STATUSES)],
            ]);

            $project->update($validated);

            Log::info('project_updated', ['project_id' => $project->id]);

            return $this->success(
                data: new ProjectResource($project->fresh()),
                message: 'Project updated successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'project_update_failed',
                'Failed to update project.',
                $e,
                500,
                ['project_id' => $project->id],
            );
        }
    }

    public function destroy(Project $project): JsonResponse
    {
        try {
            $project->conversations()->delete();
            $project->delete();

            Log::info('project_deleted', ['project_id' => $project->id]);

            return $this->success(message: 'Project deleted successfully.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'project_delete_failed',
                'Failed to delete project.',
                $e,
                500,
                ['project_id' => $project->id],
            );
        }
    }

    public function duplicate(Request $request, Project $project): JsonResponse
    {
        try {
            $duplicated = $project->replicate(['slug', 'last_accessed_at', 'status']);
            $duplicated->name = $project->name.' (Copy)';
            $duplicated->status = Project::STATUS_DRAFT;

            $request->user()->projects()->save($duplicated);

            Log::info('project_duplicated', [
                'original_id' => $project->id,
                'new_id' => $duplicated->id,
            ]);

            return $this->success(
                data: new ProjectResource($duplicated),
                message: 'Project duplicated successfully.',
                code: 201,
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'project_duplicate_failed',
                'Failed to duplicate project.',
                $e,
                500,
                ['project_id' => $project->id],
            );
        }
    }
}
