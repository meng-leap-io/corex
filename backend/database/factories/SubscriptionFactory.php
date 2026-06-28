<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan' => 'free',
            'status' => 'active',
            'stripe_id' => 'sub_'.fake()->uuid(),
            'stripe_status' => 'active',
            'stripe_price' => null,
            'quantity' => 1,
            'trial_ends_at' => null,
            'ends_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function pro(): static
    {
        return $this->state(fn () => [
            'plan' => 'pro',
            'stripe_price' => 'price_pro_monthly',
        ]);
    }

    public function team(): static
    {
        return $this->state(fn () => [
            'plan' => 'team',
            'stripe_price' => 'price_team_monthly',
        ]);
    }

    public function trialing(): static
    {
        return $this->state(fn () => [
            'plan' => 'pro',
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => 'expired',
            'ends_at' => now()->subDay(),
        ]);
    }
}
