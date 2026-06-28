<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SubscriptionPolicy
{
    public function before(User $user): ?Response
    {
        if ($user->is_admin) {
            return Response::allow();
        }

        return null;
    }

    public function view(User $user, Subscription $subscription): Response
    {
        return $subscription->user_id === $user->id
            ? Response::allow()
            : Response::deny('You cannot view this subscription.');
    }

    public function create(User $user): Response
    {
        return Response::allow();
    }

    public function update(User $user, Subscription $subscription): Response
    {
        if ($subscription->user_id === $user->id) {
            return Response::allow();
        }

        return Response::deny('You cannot update this subscription.');
    }

    public function cancel(User $user, Subscription $subscription): Response
    {
        return $subscription->user_id === $user->id
            ? Response::allow()
            : Response::deny('You cannot cancel this subscription.');
    }
}
