<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CodeGenerationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'prompt' => fake()->sentence(10),
            'code_generated' => "<?php\n\nnamespace App\\Services;\n\nclass ".fake()->word()."\n{\n    public function handle(): void\n    {\n        // ".fake()->sentence()."\n    }\n}",
            'language' => fake()->randomElement(['PHP', 'JavaScript', 'TypeScript', 'Python', 'Go']),
            'model_used' => fake()->randomElement(['gpt-4o', 'claude-3-sonnet']),
            'tokens_used' => fake()->numberBetween(200, 6000),
            'cost' => fake()->randomFloat(6, 0.002, 0.08),
            'status' => 'completed',
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'code_generated' => '',
            'status' => 'failed',
        ]);
    }
}
