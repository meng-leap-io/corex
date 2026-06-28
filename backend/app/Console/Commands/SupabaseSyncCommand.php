<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\SyncContract;
use App\Models\SyncConflict;
use App\Models\SyncSnapshot;
use App\Services\Sync\SnapshotManager;
use Illuminate\Console\Command;

class SupabaseSyncCommand extends Command
{
    protected $signature = 'supabase:sync
        {action=full : Action: full, push, pull, status, conflicts, snapshots, rollback, retry-dead, clear-dead }
        {--table= : Specific table to sync }
        {--record= : Specific record ID }
        {--snapshot= : Snapshot ID for rollback }
        {--conflict= : Conflict ID to resolve }
        {--resolution= : JSON resolution data for conflict }
        {--force : Force sync even if offline }';

    protected $description = 'Manage Supabase sync operations';

    public function handle(SyncContract $sync): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'full' => $this->fullSync($sync),
            'push' => $this->pushChanges($sync),
            'pull' => $this->pullChanges($sync),
            'status' => $this->showStatus($sync),
            'conflicts' => $this->showConflicts(),
            'snapshots' => $this->showSnapshots(),
            'rollback' => $this->rollbackSnapshot($sync),
            'retry-dead' => $this->retryDead($sync),
            'clear-dead' => $this->clearDead($sync),
            default => $this->fullSync($sync),
        };
    }

    private function fullSync(SyncContract $sync): int
    {
        $table = $this->option('table');

        $this->info('Running full sync (push + pull)...');

        if (!$sync->verifyConnection() && !$this->option('force')) {
            $this->error('Cannot connect to Supabase. Use --force to sync anyway.');

            return Command::FAILURE;
        }

        $start = microtime(true);
        $stats = $sync->fullSync($table);
        $duration = round((microtime(true) - $start) * 1000);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Pushed', $stats['pushed']],
                ['Pulled', $stats['pulled']],
                ['Conflicts', $stats['conflicts']],
                ['Errors', $stats['errors']],
                ['Duration', "{$duration}ms"],
            ],
        );

        return Command::SUCCESS;
    }

    private function pushChanges(SyncContract $sync): int
    {
        $table = $this->option('table');

        $this->info('Pushing local changes...');

        if (!$sync->verifyConnection() && !$this->option('force')) {
            $this->error('Cannot connect to Supabase. Use --force to sync anyway.');

            return Command::FAILURE;
        }

        $synced = $sync->pushLocalChanges($table);
        $this->info("Pushed {$synced->count()} records.");

        return Command::SUCCESS;
    }

    private function pullChanges(SyncContract $sync): int
    {
        $table = $this->option('table');

        $this->info('Pulling remote changes...');

        if (!$sync->verifyConnection() && !$this->option('force')) {
            $this->error('Cannot connect to Supabase. Use --force to sync anyway.');

            return Command::FAILURE;
        }

        $lastSync = $table ? $sync->getLastSyncTime($table) : null;
        $changes = $sync->pullRemoteChanges($table, $lastSync);
        $this->info("Pulled {$changes->count()} records.");

        return Command::SUCCESS;
    }

    private function showStatus(SyncContract $sync): int
    {
        $table = $this->option('table');
        $record = $this->option('record');

        if ($table && $record) {
            $status = $sync->getSyncStatus($table, $record);

            if ($status) {
                $this->table(
                    ['Field', 'Value'],
                    collect($status)->map(fn ($v, $k) => [ucwords(str_replace('_', ' ', $k)), $v])->toArray()
                );
            } else {
                $this->warn("No sync status found for {$table}:{$record}");
            }
        } else {
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Pending', $sync->getPendingCount()],
                    ['Conflicts', $sync->getConflictCount()],
                    ['Queue Pending', $sync->getQueueStats()['pending'] ?? 0],
                    ['Queue Dead', $sync->getQueueStats()['dead'] ?? 0],
                    ['Sync Progress', $sync->getSyncProgress()['progress'] . '%'],
                ],
            );
        }

        return Command::SUCCESS;
    }

    private function showConflicts(): int
    {
        $table = $this->option('table');
        $conflictId = $this->option('conflict');
        $resolutionJson = $this->option('resolution');

        if ($conflictId && $resolutionJson) {
            $resolution = json_decode($resolutionJson, true);

            if (!$resolution) {
                $this->error('Invalid JSON resolution data.');

                return Command::FAILURE;
            }

            $sync = app(SyncContract::class);
            $resolved = $sync->resolveConflict($conflictId, $resolution);

            if ($resolved) {
                $this->info("Conflict {$conflictId} resolved.");
            } else {
                $this->error("Failed to resolve conflict {$conflictId}.");
            }

            return $resolved ? Command::SUCCESS : Command::FAILURE;
        }

        $query = SyncConflict::where('status', 'pending');

        if ($table) {
            $query->where('table_name', $table);
        }

        $conflicts = $query->latest()->get();

        if ($conflicts->isEmpty()) {
            $this->info('No pending conflicts.');

            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Table', 'Record', 'Local Ver', 'Remote Ver', 'Strategy', 'Reason'],
            $conflicts->map(fn ($c) => [
                $c->id,
                $c->table_name,
                $c->record_id,
                $c->local_version,
                $c->remote_version,
                $c->strategy,
                \Illuminate\Support\Str::limit($c->reason, 40),
            ])->toArray(),
        );

        return Command::SUCCESS;
    }

    private function showSnapshots(): int
    {
        $table = $this->option('table');
        $record = $this->option('record');

        $query = SyncSnapshot::query();

        if ($table) {
            $query->where('table_name', $table);
        }

        if ($record) {
            $query->where('record_id', $record);
        }

        $snapshots = $query->latest()->limit(20)->get();

        if ($snapshots->isEmpty()) {
            $this->info('No snapshots found.');

            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Table', 'Record', 'Version', 'Reason', 'Created'],
            $snapshots->map(fn ($s) => [
                \Illuminate\Support\Str::limit($s->id, 8),
                $s->table_name,
                \Illuminate\Support\Str::limit($s->record_id, 8),
                $s->version,
                $s->reason,
                $s->created_at?->diffForHumans(),
            ])->toArray(),
        );

        return Command::SUCCESS;
    }

    private function rollbackSnapshot(SyncContract $sync): int
    {
        $snapshotId = $this->option('snapshot');

        if (!$snapshotId) {
            $this->error('--snapshot option is required for rollback action.');

            return Command::FAILURE;
        }

        if ($sync->rollback($snapshotId)) {
            $this->info("Rolled back to snapshot {$snapshotId}.");

            return Command::SUCCESS;
        }

        $this->error("Failed to rollback snapshot {$snapshotId}.");

        return Command::FAILURE;
    }

    private function retryDead(SyncContract $sync): int
    {
        $queue = app(\App\Services\Sync\SyncQueue::class);
        $count = $queue->retryDead();
        $this->info("Retried {$count} dead queue jobs.");

        return Command::SUCCESS;
    }

    private function clearDead(SyncContract $sync): int
    {
        $queue = app(\App\Services\Sync\SyncQueue::class);
        $count = $queue->clearDead();
        $this->info("Cleared {$count} dead queue jobs.");

        return Command::SUCCESS;
    }
}
