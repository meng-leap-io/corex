<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->sentence(3);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'description' => fake()->paragraph(),
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'language' => fake()->randomElement(['PHP', 'JavaScript', 'TypeScript', 'Python', 'Go', 'Rust']),
            'framework' => fake()->randomElement(['Laravel', 'React', 'Vue.js', 'Django', 'FastAPI']),
            'files' => [
                ['path' => 'src/app.js', 'size' => 2048],
                ['path' => 'src/components/Header.jsx', 'size' => 1024],
                ['path' => 'package.json', 'size' => 512],
            ],
            'structure' => [
                'src' => ['app.js', 'components/Header.jsx'],
                'public' => ['index.html'],
            ],
            'status' => fake()->randomElement(['draft', 'active']),
            'last_accessed_at' => fake()->dateTimeThisMonth(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn() => ['status' => 'active']);
    }

    public function archived(): static
    {
        return $this->state(fn() => ['status' => 'archived']);
    }
}
