<?php

namespace Database\Factories;

use App\Models\WebhookLog;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookLogFactory extends Factory
{
    protected $model = WebhookLog::class;

    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(['stripe', 'resend', 'github', 'supabase']),
            'event_type' => fake()->randomElement([
                'customer.subscription.updated',
                'email.sent',
                'push',
                'pull_request',
                'analytics.event',
            ]),
            'event_id' => fake()->uuid(),
            'status' => 'pending',
            'payload' => ['type' => 'test', 'data' => ['key' => fake()->word()]],
            'headers' => ['content-type' => 'application/json'],
            'attempts' => 0,
            'max_attempts' => 3,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'response' => ['handled' => true],
            'response_status' => 200,
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => fake()->sentence(),
            'failed_at' => now(),
        ]);
    }

    public function stripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'stripe',
            'event_type' => 'customer.subscription.updated',
            'payload' => [
                'type' => 'customer.subscription.updated',
                'data' => [
                    'object' => [
                        'id' => 'sub_'.fake()->regexify('[a-z0-9]{14}'),
                        'customer' => 'cus_'.fake()->regexify('[a-z0-9]{14}'),
                        'status' => 'active',
                        'metadata' => ['user_id' => fake()->uuid()],
                        'items' => [
                            'data' => [
                                ['price' => ['nickname' => 'pro', 'id' => 'price_1']],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
