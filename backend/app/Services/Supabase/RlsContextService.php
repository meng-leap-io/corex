<?php

declare(strict_types=1);

namespace App\Services\Supabase;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RlsContextService
{
    private const SESSION_USER_ID = 'app.current_user_id';

    private const SESSION_USER_ROLE = 'app.current_user_role';

    private const SESSION_USER_EMAIL = 'app.current_user_email';

    private const SESSION_IP_ADDRESS = 'app.ip_address';

    private bool $contextSet = false;

    public function setUserContext(User $user, ?string $ipAddress = null): void
    {
        $connection = $this->getConnection();
        $userId = (string) $user->getKey();
        $role = $this->resolveRole($user);

        $statements = [
            "SET SESSION \"app.current_user_id\" = '{$userId}'",
            "SET SESSION \"app.current_user_role\" = '{$role}'",
            "SET SESSION \"app.current_user_email\" = '{$this->escape($user->email)}'",
        ];

        if ($ipAddress) {
            $statements[] = "SET SESSION \"app.ip_address\" = '{$this->escape($ipAddress)}'";
        }

        foreach ($statements as $sql) {
            $connection->statement($sql);
        }

        $this->contextSet = true;

        Log::debug('rls.context.set', [
            'user_id' => $userId,
            'role' => $role,
            'connection' => $connection->getName(),
        ]);
    }

    public function setGuestContext(?string $ipAddress = null): void
    {
        $connection = $this->getConnection();

        $statements = [
            "SET SESSION \"app.current_user_id\" = ''",
            "SET SESSION \"app.current_user_role\" = 'anonymous'",
        ];

        if ($ipAddress) {
            $statements[] = "SET SESSION \"app.ip_address\" = '{$this->escape($ipAddress)}'";
        }

        foreach ($statements as $sql) {
            $connection->statement($sql);
        }

        $this->contextSet = true;

        Log::debug('rls.context.set_guest');
    }

    public function setSystemContext(): void
    {
        $connection = $this->getConnection();

        $statements = [
            "SET SESSION \"app.current_user_id\" = '00000000-0000-0000-0000-000000000000'",
            "SET SESSION \"app.current_user_role\" = 'system'",
        ];

        foreach ($statements as $sql) {
            $connection->statement($sql);
        }

        $this->contextSet = true;

        Log::debug('rls.context.set_system');
    }

    public function clearContext(): void
    {
        $connection = $this->getConnection();

        $statements = [
            'RESET SESSION "app.current_user_id"',
            'RESET SESSION "app.current_user_role"',
            'RESET SESSION "app.current_user_email"',
            'RESET SESSION "app.ip_address"',
        ];

        foreach ($statements as $sql) {
            try {
                $connection->statement($sql);
            } catch (\Throwable) {
            }
        }

        $this->contextSet = false;
    }

    public function isContextSet(): bool
    {
        return $this->contextSet;
    }

    public function getCurrentUserId(): ?string
    {
        try {
            $value = $this->getConnection()->selectOne(
                'SELECT current_setting(?, true) as value',
                [self::SESSION_USER_ID]
            );

            $val = $value?->value;

            return $val && $val !== '' ? $val : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getCurrentUserRole(): ?string
    {
        try {
            $value = $this->getConnection()->selectOne(
                'SELECT current_setting(?, true) as value',
                [self::SESSION_USER_ROLE]
            );

            $val = $value?->value;

            return $val && $val !== '' ? $val : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getEffectiveUserId(): ?string
    {
        $userId = $this->getCurrentUserId();

        if ($userId) {
            return $userId;
        }

        try {
            $result = $this->getConnection()->selectOne("SELECT auth.uid() as id");

            return $result?->id;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isAdmin(): bool
    {
        return $this->getCurrentUserRole() === 'admin';
    }

    public function executeWithContext(User $user, callable $callback, ?string $connection = null): mixed
    {
        $previousContext = $this->isContextSet();

        try {
            $this->setUserContext($user);

            return $callback();
        } finally {
            if (!$previousContext) {
                $this->clearContext();
            }
        }
    }

    public function executeAsSystem(callable $callback): mixed
    {
        $previousContext = $this->isContextSet();

        try {
            $this->setSystemContext();

            return $callback();
        } finally {
            if (!$previousContext) {
                $this->clearContext();
            }
        }
    }

    public function getConnection(): \Illuminate\Database\Connection
    {
        $connectionName = config('supabase.rls.db_connection', 'pgsql');

        try {
            return DB::connection($connectionName);
        } catch (\Throwable) {
            return DB::connection('pgsql');
        }
    }

    private function resolveRole(User $user): string
    {
        $role = $user->role ?? 'user';

        if ($role === 'admin') {
            return 'admin';
        }

        if ($user->email === config('app.admin_email')) {
            return 'admin';
        }

        return $role;
    }

    private function escape(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
