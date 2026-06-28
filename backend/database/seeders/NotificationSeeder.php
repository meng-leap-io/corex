<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(function (User $user) {
            Notification::factory(fake()->numberBetween(2, 8))->create([
                'user_id' => $user->id,
            ]);
        });
    }
}
