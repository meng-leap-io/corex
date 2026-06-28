<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApiKeyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true).' API Key',
            'key' => 'corex_'.Str::random(64),
            'permissions' => fake()->randomElements(['read', 'write', 'delete', 'admin'], 2),
            'last_used_at' => fake()->boolean(70) ? fake()->dateTimeThisMonth() : null,
            'expires_at' => fake()->boolean(30) ? fake()->dateTimeBetween('+1 month', '+1 year') : null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'expires_at' => fake()->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    public function neverExpires(): static
    {
        return $this->state(fn () => ['expires_at' => null]);
    }
}
