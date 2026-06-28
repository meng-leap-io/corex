<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => Str::slug(fake()->unique()->company()),
            'description' => fake()->sentence(),
            'owner_id' => User::factory(),
            'plan' => 'free',
            'max_members' => 10,
            'settings' => [],
        ];
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => 'pro',
            'max_members' => 25,
        ]);
    }
}
