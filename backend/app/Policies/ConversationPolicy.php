<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ConversationPolicy
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

    public function view(User $user, Conversation $conversation): Response
    {
        if ($conversation->user_id === $user->id) {
            return Response::allow();
        }

        if ($conversation->project && $conversation->project->isOwnedBy($user)) {
            return Response::allow();
        }

        return Response::deny('You cannot view this conversation.');
    }

    public function create(User $user, Project $project): Response
    {
        return $project->isAccessibleBy($user)
            ? Response::allow()
            : Response::deny('You are not a member of this project.');
    }

    public function update(User $user, Conversation $conversation): Response
    {
        return $conversation->user_id === $user->id
            ? Response::allow()
            : Response::deny('You cannot update this conversation.');
    }

    public function delete(User $user, Conversation $conversation): Response
    {
        if ($conversation->user_id === $user->id) {
            return Response::allow();
        }

        return Response::deny('You cannot delete this conversation.');
    }
}
