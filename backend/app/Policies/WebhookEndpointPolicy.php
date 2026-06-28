<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Auth\Access\Response;

class WebhookEndpointPolicy
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

    public function view(User $user, WebhookEndpoint $webhookEndpoint): Response
    {
        return $webhookEndpoint->user_id === $user->id
            ? Response::allow()
            : Response::deny('You cannot view this webhook endpoint.');
    }

    public function create(User $user): Response
    {
        return Response::allow();
    }

    public function update(User $user, WebhookEndpoint $webhookEndpoint): Response
    {
        return $webhookEndpoint->user_id === $user->id
            ? Response::allow()
            : Response::deny('You cannot update this webhook endpoint.');
    }

    public function delete(User $user, WebhookEndpoint $webhookEndpoint): Response
    {
        return $webhookEndpoint->user_id === $user->id
            ? Response::allow()
            : Response::deny('You cannot delete this webhook endpoint.');
    }
}
