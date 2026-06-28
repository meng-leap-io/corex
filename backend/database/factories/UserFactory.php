<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'avatar' => fake()->imageUrl(200, 200, 'people'),
            'github_id' => null,
            'google_id' => null,
            'provider' => null,
            'provider_id' => null,
            'plan' => 'free',
            'plan_expires_at' => null,
            'api_usage_limit' => 1000,
            'api_usage_current' => 0,
            'settings' => ['theme' => 'light', 'language' => 'en'],
            'preferences' => ['email_notifications' => true],
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function pro(): static
    {
        return $this->state(fn () => [
            'plan' => 'pro',
            'plan_expires_at' => now()->addYear(),
            'api_usage_limit' => 10000,
        ]);
    }

    public function team(): static
    {
        return $this->state(fn () => [
            'plan' => 'team',
            'plan_expires_at' => now()->addYear(),
            'api_usage_limit' => 50000,
        ]);
    }

    public function oauth(string $provider): static
    {
        return $this->state(fn () => [
            'password' => null,
            'provider' => $provider,
            'provider_id' => fake()->uuid(),
            'email_verified_at' => now(),
        ]);
    }
}
