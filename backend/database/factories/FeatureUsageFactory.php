<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeatureUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

class FeatureUsageFactory extends Factory
{
    protected $model = FeatureUsage::class;

    public function definition(): array
    {
        return [
            'feature' => $this->faker->randomElement(['chat', 'code_generation', 'editor', 'files', 'settings', 'terminal']),
            'action' => $this->faker->randomElement(['create', 'read', 'update', 'delete', 'search', 'export']),
            'context' => ['trigger' => $this->faker->word()],
            'success' => $this->faker->boolean(90),
            'duration_ms' => $this->faker->randomFloat(2, 10, 10000),
        ];
    }
}
