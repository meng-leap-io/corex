<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NotificationPolicy
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

    public function view(User $user, Notification $notification): Response
    {
        return $notification->user_id === $user->id
            ? Response::allow()
            : Response::deny('You cannot view this notification.');
    }

    public function markAsRead(User $user, Notification $notification): Response
    {
        return $notification->user_id === $user->id
            ? Response::allow()
            : Response::deny('You cannot mark this notification as read.');
    }

    public function delete(User $user, Notification $notification): Response
    {
        return $notification->user_id === $user->id
            ? Response::allow()
            : Response::deny('You cannot delete this notification.');
    }
}
