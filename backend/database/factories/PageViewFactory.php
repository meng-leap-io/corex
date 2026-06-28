<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PageView;
use Illuminate\Database\Eloquent\Factories\Factory;

class PageViewFactory extends Factory
{
    protected $model = PageView::class;

    public function definition(): array
    {
        return [
            'path' => '/' . $this->faker->randomElement(['console', 'chat', 'settings', 'files', 'editor', 'terminal', 'api']),
            'route' => 'console.' . $this->faker->word(),
            'method' => 'GET',
            'status_code' => $this->faker->randomElement([200, 200, 200, 200, 201, 301, 404, 500]),
            'duration_ms' => $this->faker->randomFloat(2, 5, 5000),
            'query_time_ms' => $this->faker->randomFloat(2, 1, 500),
            'memory_bytes' => $this->faker->numberBetween(1024 * 1024, 50 * 1024 * 1024),
            'session_id' => $this->faker->uuid(),
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'referer' => $this->faker->optional(0.5)->url(),
        ];
    }

    public function error(): static
    {
        return $this->state([
            'status_code' => $this->faker->randomElement([404, 500, 403, 422]),
        ]);
    }
}
