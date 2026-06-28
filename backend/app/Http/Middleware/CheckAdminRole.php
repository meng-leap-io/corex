<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminRole
{
    public const string KEY = 'admin';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->denied($request);
        }

        if (!$this->isAdmin($user)) {
            return $this->denied($request);
        }

        return $next($request);
    }

    public static function isAdmin(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        if ($user->email === config('app.admin_email')) {
            return true;
        }

        return false;
    }

    private function denied(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Forbidden. Admin access required.',
            ], 403);
        }

        abort(403, 'Admin access required.');
    }
}
