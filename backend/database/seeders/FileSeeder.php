<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Seeder;

class FileSeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(function (User $user) {
            $projects = $user->projects;
            $count = fake()->numberBetween(0, 5);

            if ($projects->isNotEmpty()) {
                File::factory($count)->create([
                    'user_id' => $user->id,
                    'project_id' => $projects->random()->id,
                ]);
            } else {
                File::factory(min($count, 2))->create([
                    'user_id' => $user->id,
                    'project_id' => null,
                ]);
            }
        });
    }
}
