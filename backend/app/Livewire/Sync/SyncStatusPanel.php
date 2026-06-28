<?php

declare(strict_types=1);

namespace App\Livewire\Sync;

use App\Contracts\SyncContract;
use App\Models\SyncConflict;
use App\Models\SyncSnapshot;
use App\Services\Sync\SyncQueue;
use Livewire\Component;

class SyncStatusPanel extends Component
{
    public string $activeTab = 'overview';

    public bool $syncing = false;

    public array $syncProgress = [];

    public array $queueStats = [];

    public array $recentConflicts = [];

    public array $recentSnapshots = [];

    public ?string $selectedConflictId = null;

    public array $conflictDetail = [];

    public string $resolutionJson = '';

    protected SyncContract $sync;

    public function boot(SyncContract $sync): void
    {
        $this->sync = $sync;
    }

    public function mount(): void
    {
        $this->refreshData();
    }

    public function refreshData(): void
    {
        $this->syncProgress = $this->sync->getSyncProgress();
        $this->queueStats = $this->sync->getQueueStats();

        $this->recentConflicts = SyncConflict::where('status', 'pending')
            ->latest()
            ->limit(10)
            ->get()
            ->toArray();

        $this->recentSnapshots = SyncSnapshot::latest()
            ->limit(5)
            ->get()
            ->toArray();
    }

    public function startSync(): void
    {
        if ($this->syncing) {
            return;
        }

        $this->syncing = true;

        try {
            $this->sync->fullSync();
            $this->dispatch('sync-completed', message: 'Sync completed successfully.');
        } catch (\Throwable $e) {
            $this->dispatch('sync-error', message: $e->getMessage());
        } finally {
            $this->syncing = false;
            $this->refreshData();
        }
    }

    public function resolveConflict(): void
    {
        if (! $this->selectedConflictId || ! $this->resolutionJson) {
            return;
        }

        $resolution = json_decode($this->resolutionJson, true);

        if (! $resolution) {
            $this->dispatch('sync-error', message: 'Invalid JSON resolution data.');

            return;
        }

        $resolved = $this->sync->resolveConflict($this->selectedConflictId, $resolution);

        if ($resolved) {
            $this->dispatch('sync-completed', message: 'Conflict resolved.');
            $this->selectedConflictId = null;
            $this->resolutionJson = '';
        } else {
            $this->dispatch('sync-error', message: 'Failed to resolve conflict.');
        }

        $this->refreshData();
    }

    public function viewConflict(string $conflictId): void
    {
        $this->selectedConflictId = $conflictId;
        $this->activeTab = 'conflict';

        $conflict = SyncConflict::find($conflictId);

        if ($conflict) {
            $this->conflictDetail = $conflict->toArray();
            $this->resolutionJson = json_encode($conflict->remote_data, JSON_PRETTY_PRINT);
        }
    }

    public function retryDead(): void
    {
        $queue = app(SyncQueue::class);
        $count = $queue->retryDead();
        $this->dispatch('sync-completed', message: "Retried {$count} dead jobs.");
        $this->refreshData();
    }

    public function render()
    {
        return view('livewire.sync.sync-status-panel');
    }
}
