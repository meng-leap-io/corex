<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@corex.dev')->first();
        $pro = User::where('email', 'jane@example.com')->first();
        $others = User::whereNotIn('email', ['admin@corex.dev', 'jane@example.com'])->take(4);

        if ($admin) {
            $coreTeam = Team::factory()->create([
                'owner_id' => $admin->id,
                'name' => 'CoreX Team',
                'slug' => 'corex-team',
                'plan' => 'team',
                'max_members' => 20,
            ]);
            $coreTeam->members()->attach($admin->id, ['role' => 'owner']);
            if ($pro) {
                $coreTeam->members()->attach($pro->id, ['role' => 'admin']);
            }
            $others->each(fn (User $u) => $coreTeam->members()->attach($u->id, ['role' => 'member']));
        }

        if ($pro) {
            $proTeam = Team::factory()->create([
                'owner_id' => $pro->id,
                'name' => 'Acme Projects',
                'slug' => 'acme-projects',
                'plan' => 'pro',
                'max_members' => 10,
            ]);
            $proTeam->members()->attach($pro->id, ['role' => 'owner']);
            User::whereNotIn('email', ['admin@corex.dev', 'jane@example.com'])->take(2)
                ->each(fn (User $u) => $proTeam->members()->attach($u->id, ['role' => 'member']));
        }

        Team::factory(2)->create()->each(function (Team $team) {
            $owner = User::inRandomOrder()->first();
            $team->update(['owner_id' => $owner->id]);
            $team->members()->attach($owner->id, ['role' => 'owner']);
            User::inRandomOrder()->take(fake()->numberBetween(1, 3))
                ->each(fn (User $u) => $team->members()->attach($u->id, ['role' => 'member']));
        });
    }
}
