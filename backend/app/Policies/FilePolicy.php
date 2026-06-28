<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\File;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FilePolicy
{
    public function before(User $user): ?Response
    {
        if ($user->is_admin) {
            return Response::allow();
        }

        return null;
    }

    public function viewAny(User $user, Project $project): Response
    {
        return $project->isAccessibleBy($user)
            ? Response::allow()
            : Response::deny('You are not a member of this project.');
    }

    public function view(User $user, File $file): Response
    {
        if ($file->user_id === $user->id) {
            return Response::allow();
        }

        if ($file->project && $file->project->isAccessibleBy($user)) {
            return Response::allow();
        }

        return Response::deny('You cannot view this file.');
    }

    public function create(User $user, Project $project): Response
    {
        return $project->isAccessibleBy($user)
            ? Response::allow()
            : Response::deny('You are not a member of this project.');
    }

    public function update(User $user, File $file): Response
    {
        return $file->user_id === $user->id
            ? Response::allow()
            : Response::deny('You cannot update this file.');
    }

    public function delete(User $user, File $file): Response
    {
        if ($file->user_id === $user->id) {
            return Response::allow();
        }

        return Response::deny('You cannot delete this file.');
    }

    public function download(User $user, File $file): Response
    {
        if ($file->user_id === $user->id) {
            return Response::allow();
        }

        if ($file->project && $file->project->isAccessibleBy($user)) {
            return Response::allow();
        }

        return Response::deny('You cannot download this file.');
    }
}
