<?php

namespace App\Providers;

use App\Models\AiUsageLog;
use App\Models\Conversation;
use App\Models\File;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Policies\AiUsageLogPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\FilePolicy;
use App\Policies\NotificationPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\SettingPolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\TeamPolicy;
use App\Policies\UserPolicy;
use App\Policies\WebhookEndpointPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        AiUsageLog::class => AiUsageLogPolicy::class,
        Conversation::class => ConversationPolicy::class,
        File::class => FilePolicy::class,
        Notification::class => NotificationPolicy::class,
        Project::class => ProjectPolicy::class,
        Setting::class => SettingPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
        Team::class => TeamPolicy::class,
        User::class => UserPolicy::class,
        WebhookEndpoint::class => WebhookEndpointPolicy::class,
    ];

    public function boot(): void
    {
        Gate::define('admin', function (User $user) {
            return $user->email === config('app.admin_email', 'admin@corex.dev');
        });

        Gate::define('update-user', function (User $user, User $target) {
            return $user->id === $target->id || $user->can('admin');
        });

        Gate::define('delete-user', function (User $user, User $target) {
            return $user->id === $target->id || $user->can('admin');
        });
    }
}
