<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $teams = $request->user()->teams()
                ->withCount('members')
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return $this->success(
                data: $teams,
                message: 'Teams retrieved successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'teams_index_failed',
                'Failed to retrieve teams.',
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
                'settings' => ['nullable', 'array'],
            ]);

            $team = Team::create([
                'owner_id' => $request->user()->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'settings' => $validated['settings'] ?? [],
            ]);

            $team->members()->attach($request->user()->id, [
                'role' => 'owner',
                'permissions' => json_encode(['*']),
                'joined_at' => now(),
            ]);

            Log::info('team_created', [
                'team_id' => $team->id,
                'owner_id' => $request->user()->id,
            ]);

            return $this->success(
                data: $team,
                message: 'Team created successfully.',
                code: 201,
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'team_create_failed',
                'Failed to create team.',
                $e,
                500,
            );
        }
    }

    public function show(Team $team): JsonResponse
    {
        Gate::authorize('view', $team);
        try {
            $team->load('members', 'owner');

            return $this->success(
                data: $team,
                message: 'Team retrieved successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'team_show_failed',
                'Failed to retrieve team.',
                $e,
                500,
                ['team_id' => $team->id],
            );
        }
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        Gate::authorize('update', $team);
        try {
            $validated = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:5000'],
                'settings' => ['nullable', 'array'],
            ]);

            $team->update($validated);

            Log::info('team_updated', ['team_id' => $team->id]);

            return $this->success(
                data: $team->fresh(),
                message: 'Team updated successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'team_update_failed',
                'Failed to update team.',
                $e,
                500,
                ['team_id' => $team->id],
            );
        }
    }

    public function destroy(Team $team): JsonResponse
    {
        Gate::authorize('delete', $team);
        try {
            $team->members()->detach();
            $team->delete();

            Log::info('team_deleted', ['team_id' => $team->id]);

            return $this->success(message: 'Team deleted successfully.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'team_delete_failed',
                'Failed to delete team.',
                $e,
                500,
                ['team_id' => $team->id],
            );
        }
    }

    public function addMember(Request $request, Team $team): JsonResponse
    {
        Gate::authorize('addMember', $team);
        try {
            $validated = $request->validate([
                'user_id' => ['required', 'string', 'exists:users,id'],
                'role' => ['nullable', 'string', 'in:member,admin,owner'],
            ]);

            if ($team->members()->where('user_id', $validated['user_id'])->exists()) {
                return $this->error('User is already a member of this team.', 422);
            }

            $team->members()->attach($validated['user_id'], [
                'role' => $validated['role'] ?? 'member',
                'permissions' => json_encode([]),
                'joined_at' => now(),
            ]);

            Log::info('team_member_added', [
                'team_id' => $team->id,
                'user_id' => $validated['user_id'],
            ]);

            return $this->success(
                data: $team->fresh()->load('members'),
                message: 'Member added successfully.',
                code: 201,
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'team_add_member_failed',
                'Failed to add member.',
                $e,
                500,
                ['team_id' => $team->id],
            );
        }
    }

    public function removeMember(Team $team, User $user): JsonResponse
    {
        Gate::authorize('removeMember', $team);
        try {
            if (! $team->members()->where('user_id', $user->id)->exists()) {
                return $this->error('User is not a member of this team.', 404);
            }

            $team->members()->detach($user->id);

            Log::info('team_member_removed', [
                'team_id' => $team->id,
                'user_id' => $user->id,
            ]);

            return $this->success(
                data: $team->fresh()->load('members'),
                message: 'Member removed successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'team_remove_member_failed',
                'Failed to remove member.',
                $e,
                500,
                ['team_id' => $team->id, 'user_id' => $user->id],
            );
        }
    }

    public function updateMemberRole(Request $request, Team $team, User $user): JsonResponse
    {
        Gate::authorize('updateMemberRole', $team);
        try {
            $validated = $request->validate([
                'role' => ['required', 'string', 'in:member,admin,owner'],
            ]);

            if (! $team->members()->where('user_id', $user->id)->exists()) {
                return $this->error('User is not a member of this team.', 404);
            }

            $team->members()->updateExistingPivot($user->id, [
                'role' => $validated['role'],
            ]);

            Log::info('team_member_role_updated', [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'role' => $validated['role'],
            ]);

            return $this->success(
                data: $team->fresh()->load('members'),
                message: 'Member role updated successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'team_update_member_role_failed',
                'Failed to update member role.',
                $e,
                500,
                ['team_id' => $team->id, 'user_id' => $user->id],
            );
        }
    }

    public function addProject(Request $request, Team $team, string $project): JsonResponse
    {
        try {
            $validated = $request->validate([
                'access_level' => ['nullable', 'string', 'in:view,edit,admin'],
            ]);

            $team->projects()->attach($project, [
                'access_level' => $validated['access_level'] ?? 'view',
            ]);

            Log::info('team_project_added', [
                'team_id' => $team->id,
                'project_id' => $project,
            ]);

            return $this->success(message: 'Project added to team successfully.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'team_add_project_failed',
                'Failed to add project to team.',
                $e,
                500,
                ['team_id' => $team->id, 'project_id' => $project],
            );
        }
    }

    public function removeProject(Team $team, string $project): JsonResponse
    {
        try {
            $team->projects()->detach($project);

            Log::info('team_project_removed', [
                'team_id' => $team->id,
                'project_id' => $project,
            ]);

            return $this->success(message: 'Project removed from team successfully.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'team_remove_project_failed',
                'Failed to remove project from team.',
                $e,
                500,
                ['team_id' => $team->id, 'project_id' => $project],
            );
        }
    }
}
