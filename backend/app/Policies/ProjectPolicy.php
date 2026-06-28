<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function before(User $user): ?Response
    {
        if ($user->is_admin) {
            return Response::allow();
        }

        return null;
    }

    public function viewAny(User $user): Response
    {
        return Response::allow();
    }

    public function view(User $user, Project $project): Response
    {
        return $project->isAccessibleBy($user)
            ? Response::allow()
            : Response::deny('You cannot view this project.');
    }

    public function create(User $user): Response
    {
        return Response::allow();
    }

    public function update(User $user, Project $project): Response
    {
        return $project->isOwnedBy($user)
            ? Response::allow()
            : Response::deny('You cannot update this project.');
    }

    public function delete(User $user, Project $project): Response
    {
        return $project->isOwnedBy($user)
            ? Response::allow()
            : Response::deny('You cannot delete this project.');
    }

    public function duplicate(User $user, Project $project): Response
    {
        return $project->isOwnedBy($user)
            ? Response::allow()
            : Response::deny('You can only duplicate your own projects.');
    }
}
