<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CodeGeneration;
use App\Models\User;
use Illuminate\Database\Seeder;

class CodeGenerationSeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(function (User $user) {
            $projects = $user->projects;

            if ($projects->isNotEmpty()) {
                $projects->each(function ($project) use ($user) {
                    CodeGeneration::factory(fake()->numberBetween(0, 4))->create([
                        'user_id' => $user->id,
                        'project_id' => $project->id,
                    ]);
                });
            } else {
                CodeGeneration::factory(fake()->numberBetween(0, 2))->create([
                    'user_id' => $user->id,
                    'project_id' => null,
                ]);
            }
        });
    }
}
