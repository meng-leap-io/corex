<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(User::class, 'user');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $users = User::query()
                ->when($request->plan, fn ($q, $v) => $q->onPlan($v))
                ->when($request->verified, fn ($q, $v) => $v ? $q->verified() : $q->unverified())
                ->when($request->search, fn ($q, $v) => $q->where(function ($q) use ($v) {
                    $q->where('name', 'ilike', "%{$v}%")
                        ->orWhere('email', 'ilike', "%{$v}%");
                }))
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return $this->success(
                data: new UserCollection($users),
                message: 'Users retrieved successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'users_index_failed',
                'Failed to retrieve users.',
                $e,
                500,
            );
        }
    }

    public function show(User $user): JsonResponse
    {
        try {
            return $this->success(
                data: new UserResource($user),
                message: 'User retrieved successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'user_show_failed',
                'Failed to retrieve user.',
                $e,
                500,
                ['user_id' => $user->id],
            );
        }
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        try {
            $user->update($request->validated());

            Log::info('user_updated', ['user_id' => $user->id, 'updated_by' => $request->user()?->id]);

            return $this->success(
                data: new UserResource($user->fresh()),
                message: 'User updated successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'user_update_failed',
                'Failed to update user.',
                $e,
                500,
                ['user_id' => $user->id],
            );
        }
    }

    public function delete(Request $request, User $user): JsonResponse
    {
        try {
            Gate::authorize('delete-user', $user);

            $user->tokens()->delete();
            $user->delete();

            Log::info('user_deleted', ['user_id' => $user->id, 'deleted_by' => $request->user()?->id]);

            return $this->success(message: 'User deleted successfully.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'user_delete_failed',
                'Failed to delete user.',
                $e,
                500,
                ['user_id' => $user->id],
            );
        }
    }

    public function profile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->unauthenticated();
            }

            return $this->success(
                data: new UserResource($user),
                message: 'Profile retrieved successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'profile_fetch_failed',
                'Failed to retrieve profile.',
                $e,
                500,
            );
        }
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return $this->unauthenticated();
            }

            $user->update($request->validated());

            Log::info('profile_updated', ['user_id' => $user->id]);

            return $this->success(
                data: new UserResource($user->fresh()),
                message: 'Profile updated successfully.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'profile_update_failed',
                'Failed to update profile.',
                $e,
                500,
            );
        }
    }
}
