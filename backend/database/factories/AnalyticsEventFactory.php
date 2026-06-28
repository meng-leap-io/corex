<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AnalyticsEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalyticsEventFactory extends Factory
{
    protected $model = AnalyticsEvent::class;

    public function definition(): array
    {
        return [
            'event_type' => $this->faker->randomElement(['page_view', 'click', 'login', 'signup', 'api_call', 'export', 'import']),
            'category' => $this->faker->randomElement(['navigation', 'user', 'content', 'system']),
            'label' => $this->faker->word(),
            'value' => $this->faker->optional(0.3)->randomFloat(2, 0, 100),
            'metadata' => ['source' => $this->faker->word(), 'version' => '1.0'],
            'session_id' => $this->faker->uuid(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
        ];
    }

    public function ofType(string $type): static
    {
        return $this->state(['event_type' => $type]);
    }
}
