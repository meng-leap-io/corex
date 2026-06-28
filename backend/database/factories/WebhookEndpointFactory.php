<?php

namespace Database\Factories;

use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookEndpointFactory extends Factory
{
    protected $model = WebhookEndpoint::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' Webhook',
            'url' => fake()->url(),
            'secret' => fake()->sha256(),
            'events' => ['*'],
            'status' => 'active',
            'retry_count' => 3,
            'timeout_seconds' => 10,
            'headers' => [],
            'metadata' => [],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function forEvent(string $eventType): static
    {
        return $this->state(fn (array $attributes) => [
            'events' => [$eventType],
        ]);
    }
}
