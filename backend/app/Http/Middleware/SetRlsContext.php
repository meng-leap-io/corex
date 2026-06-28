<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Supabase\RlsContextService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetRlsContext
{
    public function __construct(
        private readonly RlsContextService $rlsContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $connectionName = config('supabase.rls.db_connection', 'pgsql');

        try {
            $connection = DB::connection($connectionName);
            $connection->getPdo();
        } catch (\Throwable) {
            return $next($request);
        }

        $user = $request->user();

        if ($user) {
            $this->rlsContext->setUserContext(
                $user,
                $request->ip(),
            );
        } else {
            $this->rlsContext->setGuestContext($request->ip());
        }

        $response = $next($request);

        $this->rlsContext->clearContext();

        return $response;
    }
}
