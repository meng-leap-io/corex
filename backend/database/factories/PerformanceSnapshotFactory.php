<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PerformanceSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

class PerformanceSnapshotFactory extends Factory
{
    protected $model = PerformanceSnapshot::class;

    public function definition(): array
    {
        return [
            'cpu_load' => $this->faker->randomFloat(2, 0, 100),
            'memory_used_mb' => $this->faker->numberBetween(256, 8192),
            'memory_total_mb' => 16384,
            'disk_used_mb' => $this->faker->numberBetween(10000, 50000),
            'disk_total_mb' => 100000,
            'active_connections' => $this->faker->numberBetween(1, 50),
            'queue_size' => $this->faker->numberBetween(0, 100),
            'request_rate_per_min' => $this->faker->randomFloat(2, 1, 500),
            'avg_response_time_ms' => $this->faker->randomFloat(2, 50, 2000),
            'p95_response_time_ms' => $this->faker->randomFloat(2, 100, 5000),
            'p99_response_time_ms' => $this->faker->randomFloat(2, 200, 10000),
            'error_count_5m' => $this->faker->numberBetween(0, 20),
            'services' => [
                'database' => ['status' => 'healthy'],
                'redis' => ['status' => 'healthy'],
            ],
        ];
    }
}
