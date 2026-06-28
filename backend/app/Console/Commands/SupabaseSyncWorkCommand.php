<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\SyncContract;
use App\Events\Sync\SyncCompleted;
use App\Events\Sync\SyncFailed;
use App\Events\Sync\SyncStarted;
use App\Services\Sync\SyncQueue;
use Illuminate\Console\Command;

class SupabaseSyncWorkCommand extends Command
{
    protected $signature = 'supabase:sync-work
        {--stop-when-empty : Exit when queue is empty }
        {--max-jobs=500 : Maximum jobs to process before exiting }
        {--sleep=5 : Seconds to sleep when queue is empty }';

    protected $description = 'Process the sync queue as a daemon worker';

    public function handle(SyncContract $sync): int
    {
        $stopWhenEmpty = $this->option('stop-when-empty');
        $maxJobs = (int) $this->option('max-jobs');
        $sleepOnEmpty = (int) $this->option('sleep');
        $processed = 0;

        $this->info('Starting sync queue worker...');
        $this->info("Max jobs: {$maxJobs}");
        $this->line('');

        while ($processed < $maxJobs) {
            $queue = app(SyncQueue::class);
            $pendingCount = $queue->pendingCount();

            if ($pendingCount === 0) {
                if ($stopWhenEmpty) {
                    $this->info('Queue empty. Stopping (--stop-when-empty).');

                    break;
                }

                $this->line("Queue empty. Sleeping {$sleepOnEmpty}s...");
                sleep($sleepOnEmpty);

                continue;
            }

            if (!$sync->verifyConnection()) {
                $this->warn('Connection lost. Retrying in 10s...');
                sleep(10);

                continue;
            }

            SyncStarted::dispatch('queue', ['all']);

            $batchStart = microtime(true);
            $synced = $sync->syncPending();

            if ($synced > 0) {
                $processed += $synced;
                $duration = round((microtime(true) - $batchStart) * 1000);
                $this->line("Synced {$synced} records ({$duration}ms) | Total: {$processed}");

                SyncCompleted::dispatch('queue', $synced, 0, 0, 0, $duration);
            }

            $remaining = $queue->pendingCount();
            $dead = $queue->deadCount();

            if ($dead > 0) {
                $this->warn("{$dead} jobs in dead queue");
            }

            if ($processed >= $maxJobs) {
                $this->info("Reached max jobs ({$maxJobs}). Exiting.");

                break;
            }

            usleep(100000);
        }

        $this->newLine();
        $this->info("Worker finished. Processed {$processed} jobs.");

        return Command::SUCCESS;
    }
}
