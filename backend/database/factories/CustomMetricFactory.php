<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CustomMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomMetricFactory extends Factory
{
    protected $model = CustomMetric::class;

    public function definition(): array
    {
        return [
            'metric_key' => $this->faker->randomElement([
                'app.users.active',
                'app.projects.total',
                'app.ai.tokens_per_second',
                'app.cache.hit_rate',
                'app.queue.processing_time',
            ]),
            'metric_type' => $this->faker->randomElement(['gauge', 'counter', 'histogram']),
            'value' => $this->faker->randomFloat(4, 0, 10000),
            'tags' => ['env' => 'test', 'host' => gethostname()],
            'source' => 'phpunit',
        ];
    }
}
