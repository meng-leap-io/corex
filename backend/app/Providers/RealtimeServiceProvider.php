<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Supabase\MessageSent;
use App\Events\Supabase\NotificationCreated;
use App\Events\Supabase\PresenceUpdated;
use App\Events\Supabase\ProjectUpdated;
use App\Services\Supabase\SupabaseRealtimeService;
use App\Services\Supabase\SupabaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class RealtimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SupabaseRealtimeService::class, function ($app) {
            return new SupabaseRealtimeService(
                $app->make(SupabaseService::class),
            );
        });
    }

    public function boot(): void
    {
        $this->registerEventChannelBindings();

        if (! $this->app->runningInConsole()) {
            $this->provideRealtimeConfigToViews();
        }
    }

    private function registerEventChannelBindings(): void
    {
        $this->app->booted(function () {
            $realtime = $this->app->make(SupabaseRealtimeService::class);

            MessageSent::resolveChannelUsing(function (MessageSent $event) {
                return "chat:{$event->conversationId}";
            });

            ProjectUpdated::resolveChannelUsing(function (ProjectUpdated $event) {
                return "project:{$event->projectId}";
            });

            NotificationCreated::resolveChannelUsing(function (NotificationCreated $event) {
                return "notifications:{$event->userId}";
            });

            PresenceUpdated::resolveChannelUsing(function (PresenceUpdated $event) {
                return "team:{$event->teamId}";
            });

            if ($realtime->isEnabled()) {
                $this->autoSubscribeUserChannels($realtime);
            }
        });
    }

    private function autoSubscribeUserChannels(SupabaseRealtimeService $realtime): void
    {
        try {
            if (Auth::check()) {
                $user = Auth::user();

                $realtime->channel("notifications:{$user->id}");
                $realtime->channel("user:{$user->id}");

                $teams = $user->teams ?? [];

                foreach ($teams as $team) {
                    $realtime->channel("team:{$team->id}");
                }
            }
        } catch (\Throwable $e) {
            // Session may not be available in console/testing contexts
        }
    }

    private function provideRealtimeConfigToViews(): void
    {
        View::composer(['console.index', 'desktop.layout'], function ($view) {
            $realtime = $this->app->make(SupabaseRealtimeService::class);

            $view->with('realtimeConfig', $realtime->isEnabled() ? $realtime->getClientConfig() : null);
            $view->with('realtimeChannels', $realtime->isEnabled() ? $realtime->getChannels() : []);
        });
    }
}
