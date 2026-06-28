<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeamPolicy
{
    public function viewAny(User $user): Response
    {
        return Response::allow();
    }

    public function view(User $user, Team $team): Response
    {
        return $team->isMember($user)
            ? Response::allow()
            : Response::deny('You are not a member of this team.');
    }

    public function create(User $user): Response
    {
        return Response::allow();
    }

    public function update(User $user, Team $team): Response
    {
        return $team->hasAdmin($user)
            ? Response::allow()
            : Response::deny('You do not have permission to update this team.');
    }

    public function delete(User $user, Team $team): Response
    {
        return $team->isOwner($user)
            ? Response::allow()
            : Response::deny('Only the team owner can delete this team.');
    }

    public function addMember(User $user, Team $team): Response
    {
        return $team->hasAdmin($user)
            ? Response::allow()
            : Response::deny('You do not have permission to add members.');
    }

    public function removeMember(User $user, Team $team): Response
    {
        return $team->hasAdmin($user)
            ? Response::allow()
            : Response::deny('You do not have permission to remove members.');
    }

    public function updateMemberRole(User $user, Team $team): Response
    {
        return $team->isOwner($user)
            ? Response::allow()
            : Response::deny('Only the team owner can change member roles.');
    }
}
