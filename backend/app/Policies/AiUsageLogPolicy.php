<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AiUsageLogPolicy
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

    public function view(User $user, AiUsageLog $aiUsageLog): Response
    {
        return $aiUsageLog->user_id === $user->id
            ? Response::allow()
            : Response::deny('You cannot view this usage log.');
    }
}
