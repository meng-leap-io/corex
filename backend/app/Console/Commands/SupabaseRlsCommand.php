<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Supabase\RlsContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SupabaseRlsCommand extends Command
{
    protected $signature = 'supabase:rls
        {action=status : Action: status, set-admin, remove-admin, list-admins, enable, disable, verify }
        {--user= : User ID or email for role management }
        {--connection=pgsql : Database connection to use }';

    protected $description = 'Manage Supabase Row Level Security';

    public function handle(): int
    {
        $action = $this->argument('action');
        $connection = $this->option('connection');

        return match ($action) {
            'status' => $this->showStatus($connection),
            'set-admin' => $this->setAdminRole(),
            'remove-admin' => $this->removeAdminRole(),
            'list-admins' => $this->listAdmins(),
            'enable' => $this->toggleRls(true, $connection),
            'disable' => $this->toggleRls(false, $connection),
            'verify' => $this->verifyRls($connection),
            default => $this->showStatus($connection),
        };
    }

    private function showStatus(string $connection): int
    {
        $this->info('Supabase RLS Status');
        $this->newLine();

        try {
            $enabled = DB::connection($connection)
                ->select("SELECT tablename FROM pg_tables WHERE tablename = 'users'");

            if (empty($enabled)) {
                $this->warn('Database connection failed or tables not found.');

                return Command::FAILURE;
            }

            $rlsStatus = DB::connection($connection)
                ->select('
                    SELECT relname as table_name, relrowsecurity as rls_enabled, relforcerowsecurity as rls_forced
                    FROM pg_class
                    WHERE relrowsecurity = true OR relforcerowsecurity = true
                    ORDER BY relname
                ');

            if (empty($rlsStatus)) {
                $this->warn('No tables with RLS enabled found.');
            } else {
                $this->table(
                    ['Table', 'RLS Enabled', 'RLS Forced'],
                    collect($rlsStatus)->map(fn ($row) => [
                        $row->table_name,
                        $row->rls_enabled ? 'Yes' : 'No',
                        $row->rls_forced ? 'Yes' : 'No',
                    ])->toArray(),
                );
            }

            $rlsService = app(RlsContextService::class);

            $this->newLine();
            $this->line('Session Context:');

            $userId = $rlsService->getCurrentUserId();
            $userRole = $rlsService->getCurrentUserRole();

            $this->line("  Current User ID: {$userId}");
            $this->line("  Current User Role: {$userRole}");
        } catch (\Throwable $e) {
            $this->error("Failed to check RLS status: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function setAdminRole(): int
    {
        $userRef = $this->option('user');

        if (! $userRef) {
            $this->error('--user option is required (user ID or email).');

            return Command::FAILURE;
        }

        $user = User::where('id', $userRef)->orWhere('email', $userRef)->first();

        if (! $user) {
            $this->error("User not found: {$userRef}");

            return Command::FAILURE;
        }

        $user->update(['role' => User::ROLE_ADMIN]);
        $this->info("Set admin role for {$user->name} ({$user->email})");

        return Command::SUCCESS;
    }

    private function removeAdminRole(): int
    {
        $userRef = $this->option('user');

        if (! $userRef) {
            $this->error('--user option is required (user ID or email).');

            return Command::FAILURE;
        }

        $user = User::where('id', $userRef)->orWhere('email', $userRef)->first();

        if (! $user) {
            $this->error("User not found: {$userRef}");

            return Command::FAILURE;
        }

        $user->update(['role' => User::ROLE_USER]);
        $this->info("Removed admin role from {$user->name} ({$user->email})");

        return Command::SUCCESS;
    }

    private function listAdmins(): int
    {
        $admins = User::admin()->get();

        if ($admins->isEmpty()) {
            $this->warn('No admin users found.');
            $this->line('Set an admin user with: php artisan supabase:rls set-admin --user=<email>');

            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Email', 'Role', 'Plan'],
            $admins->map(fn ($u) => [
                $u->id,
                $u->name,
                $u->email,
                $u->role,
                $u->plan,
            ])->toArray(),
        );

        return Command::SUCCESS;
    }

    private function toggleRls(bool $enable, string $connection): int
    {
        $action = $enable ? 'ENABLE' : 'DISABLE';

        try {
            $tables = [
                'users', 'profiles', 'projects', 'conversations', 'files',
                'notifications', 'api_keys', 'subscriptions', 'ai_usage_logs',
                'code_generations', 'teams', 'team_user', 'project_user', 'project_team',
            ];

            foreach ($tables as $table) {
                DB::connection($connection)->statement(
                    "ALTER TABLE {$table} {$action} ROW LEVEL SECURITY;"
                );
            }

            $this->info("{$action}D RLS on all tables.");
        } catch (\Throwable $e) {
            $this->error("Failed to {$action} RLS: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function verifyRls(string $connection): int
    {
        $this->info('Verifying RLS policies...');
        $this->newLine();

        try {
            $policies = DB::connection($connection)->select("
                SELECT
                    schemaname,
                    tablename,
                    policyname,
                    permissive,
                    roles,
                    cmd,
                    qual,
                    with_check
                FROM pg_policies
                WHERE schemaname = 'public'
                ORDER BY tablename, policyname
            ");

            if (empty($policies)) {
                $this->warn('No RLS policies found.');

                return Command::FAILURE;
            }

            $this->table(
                ['Table', 'Policy', 'Command', 'Roles', 'Permissive'],
                collect($policies)->map(fn ($p) => [
                    $p->tablename,
                    $p->policyname,
                    $p->cmd,
                    $p->roles,
                    $p->permissive,
                ])->toArray(),
            );

            $this->newLine();

            $adminBypass = config('supabase.rls.admin_bypass', true);
            $this->line('Admin bypass: '.($adminBypass ? 'Enabled' : 'Disabled'));
            $this->line('Total policies: '.count($policies));
        } catch (\Throwable $e) {
            $this->error("Failed to verify RLS: {$e->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
