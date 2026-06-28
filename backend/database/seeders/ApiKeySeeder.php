<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Database\Seeder;

class ApiKeySeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(function (User $user) {
            ApiKey::factory(fake()->numberBetween(0, 3))->create([
                'user_id' => $user->id,
            ]);
        });
    }
}
