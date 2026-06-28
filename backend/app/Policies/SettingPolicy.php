<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SettingPolicy
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
            : Response::deny('Only administrators can view settings.');
    }

    public function view(User $user, Setting $setting): Response
    {
        return $user->is_admin
            ? Response::allow()
            : Response::deny('Only administrators can view settings.');
    }

    public function update(User $user, Setting $setting): Response
    {
        return $user->is_admin
            ? Response::allow()
            : Response::deny('Only administrators can update settings.');
    }

    public function create(User $user): Response
    {
        return $user->is_admin
            ? Response::allow()
            : Response::deny('Only administrators can create settings.');
    }

    public function delete(User $user, Setting $setting): Response
    {
        return $user->is_admin
            ? Response::allow()
            : Response::deny('Only administrators can delete settings.');
    }
}
