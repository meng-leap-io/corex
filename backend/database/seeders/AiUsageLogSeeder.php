<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class AiUsageLogSeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(function (User $user) {
            AiUsageLog::factory(fake()->numberBetween(10, 40))->create([
                'user_id' => $user->id,
            ]);
        });
    }
}
