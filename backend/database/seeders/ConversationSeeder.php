<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ConversationSeeder extends Seeder
{
    public function run(): void
    {
        User::all()->each(function (User $user) {
            $projects = $user->projects;

            if ($projects->isEmpty()) {
                Conversation::factory(fake()->numberBetween(1, 3))->create([
                    'user_id' => $user->id,
                    'project_id' => null,
                ]);
            } else {
                $projects->each(function (Project $project) use ($user) {
                    Conversation::factory(fake()->numberBetween(1, 6))->create([
                        'user_id' => $user->id,
                        'project_id' => $project->id,
                    ]);
                });
            }
        });
    }
}
