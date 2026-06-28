<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(function (User $user) {
            $count = fake()->numberBetween(1, 4);
            Project::factory($count)->create(['user_id' => $user->id]);
        });
    }
}
