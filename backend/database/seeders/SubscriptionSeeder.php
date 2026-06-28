<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@corex.dev')->first();
        if ($admin) {
            Subscription::factory()->team()->create(['user_id' => $admin->id]);
        }

        $pro = User::where('email', 'jane@example.com')->first();
        if ($pro) {
            Subscription::factory()->pro()->create(['user_id' => $pro->id]);
        }

        User::whereNotIn('email', ['admin@corex.dev', 'jane@example.com'])->each(function (User $user) {
            if (fake()->boolean(40)) {
                Subscription::factory()->create(['user_id' => $user->id]);
            }
        });
    }
}
