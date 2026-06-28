<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
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
        return $user->is_admin
            ? Response::allow()
            : Response::deny('Only administrators can view all users.');
    }

    public function view(User $user, User $target): Response
    {
        return $user->id === $target->id
            ? Response::allow()
            : Response::deny('You cannot view this user.');
    }

    public function create(User $user): Response
    {
        return Response::allow();
    }

    public function update(User $user, User $target): Response
    {
        return $user->id === $target->id
            ? Response::allow()
            : Response::deny('You cannot update this user.');
    }

    public function delete(User $user, User $target): Response
    {
        if ($user->id === $target->id) {
            return Response::deny('You cannot delete your own account.');
        }

        return Response::deny('Only administrators can delete users.');
    }
}
