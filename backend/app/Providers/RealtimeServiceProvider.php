<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Supabase\MessageSent;
use App\Events\Supabase\NotificationCreated;
use App\Events\Supabase\PresenceUpdated;
use App\Events\Supabase\ProjectUpdated;
use App\Models\User;
use App\Services\Supabase\SupabaseRealtimeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class RealtimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SupabaseRealtimeService::class, function ($app) {
            return new SupabaseRealtimeService(
                $app->make(\App\Services\Supabase\SupabaseService::class),
            );
        });
    }

    public function boot(): void
    {
        $this->registerEventChannelBindings();

        $this->provideRealtimeConfigToViews();
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
        if (Auth::check()) {
            $user = Auth::user();

            $realtime->channel("notifications:{$user->id}");
            $realtime->channel("user:{$user->id}");

            $teams = $user->teams ?? [];

            foreach ($teams as $team) {
                $realtime->channel("team:{$team->id}");
            }
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
