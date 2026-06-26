<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

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
