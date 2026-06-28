<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Services\Supabase\RlsContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

class RlsScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $table = $model->getTable();

        $connectionName = $model->getConnectionName() ?? config('database.default');

        $rlsEnabled = config("supabase.rls.connection.{$connectionName}", false);

        if (! $rlsEnabled) {
            return;
        }

        if (! config("supabase.rls.tables.{$table}", true)) {
            return;
        }

        $userIdField = config("supabase.rls.user_id_field.{$table}", 'user_id');

        try {
            $rlsService = app(RlsContextService::class);
            $currentUserId = $rlsService->getCurrentUserId();

            if ($currentUserId) {
                $builder->where(function (Builder $q) use ($currentUserId, $userIdField, $table) {
                    $q->where("{$table}.{$userIdField}", $currentUserId);

                    if ($table === 'projects') {
                        $q->orWhere('is_public', true);
                    }

                    if ($rlsService->isAdmin()) {
                        $q->orWhereRaw('1=1');
                    }
                });
            }
        } catch (\Throwable $e) {
            Log::warning('rls.scope.failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
