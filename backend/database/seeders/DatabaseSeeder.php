<?php

namespace Database\Seeders;

use App\Models\AiUsageLog;
use App\Models\ApiKey;
use App\Models\CodeGeneration;
use App\Models\Conversation;
use App\Models\Project;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Corex Admin',
            'email' => 'admin@corex.dev',
            'password' => Hash::make('admin123'),
            'plan' => 'team',
            'api_usage_limit' => 100000,
        ]);

        $admin->profile()->update([
            'company' => 'Corex.dev',
            'bio' => 'Platform administrator and lead developer.',
            'public_email' => 'admin@corex.dev',
        ]);

        Subscription::factory()->team()->create([
            'user_id' => $admin->id,
        ]);

        $proUser = User::factory()->pro()->create([
            'name' => 'Jane Developer',
            'email' => 'jane@example.com',
            'password' => Hash::make('password'),
        ]);

        $proUser->profile()->update([
            'company' => 'Acme Inc.',
            'twitter' => '@jane_dev',
            'github' => 'jane-dev',
        ]);

        Subscription::factory()->pro()->create([
            'user_id' => $proUser->id,
        ]);

        User::factory()->create([
            'name' => 'John Starter',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
        ]);

        User::factory(5)->create();

        User::factory(3)->unverified()->create();

        $allUsers = User::all();

        $allUsers->each(function (User $user) {
            $projectCount = fake()->numberBetween(1, 4);

            Project::factory($projectCount)->create([
                'user_id' => $user->id,
            ])->each(function (Project $project) use ($user) {
                Conversation::factory(fake()->numberBetween(1, 6))->create([
                    'user_id' => $user->id,
                    'project_id' => $project->id,
                ]);

                CodeGeneration::factory(fake()->numberBetween(0, 4))->create([
                    'user_id' => $user->id,
                    'project_id' => $project->id,
                ]);
            });

            AiUsageLog::factory(fake()->numberBetween(5, 20))->create([
                'user_id' => $user->id,
            ]);

            ApiKey::factory(fake()->numberBetween(0, 3))->create([
                'user_id' => $user->id,
            ]);
        });
    }
}
