<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Analytics\AlertTriggered;
use App\Events\Analytics\MetricsUpdated;
use App\Events\Sync\ConflictDetected;
use App\Events\Sync\SyncCompleted;
use App\Events\Sync\SyncFailed;
use App\Events\Sync\SyncStarted;
use App\Listeners\Analytics\SendAlertNotification;
use App\Listeners\Analytics\UpdateMetricsDashboard;
use App\Listeners\Sync\HandleSyncConflict;
use App\Listeners\Sync\LogSyncCompletion;
use App\Listeners\Sync\LogSyncFailure;
use App\Listeners\Sync\LogSyncStart;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        SyncStarted::class => [
            LogSyncStart::class,
        ],

        SyncCompleted::class => [
            LogSyncCompletion::class,
        ],

        SyncFailed::class => [
            LogSyncFailure::class,
        ],

        ConflictDetected::class => [
            HandleSyncConflict::class,
        ],

        AlertTriggered::class => [
            SendAlertNotification::class,
        ],

        MetricsUpdated::class => [
            UpdateMetricsDashboard::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
