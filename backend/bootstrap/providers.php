<?php

use App\Providers\AnalyticsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\RealtimeServiceProvider;
use App\Providers\RouteServiceProvider;
use App\Providers\SupabaseServiceProvider;
use App\Providers\SyncServiceProvider;
use App\Providers\WebhookServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    RouteServiceProvider::class,
    EventServiceProvider::class,
    SupabaseServiceProvider::class,
    RealtimeServiceProvider::class,
    SyncServiceProvider::class,
    WebhookServiceProvider::class,
    AnalyticsServiceProvider::class,
];
