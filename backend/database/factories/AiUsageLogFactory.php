<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiUsageLogFactory extends Factory
{
    public function definition(): array
    {
        $promptTokens = fake()->numberBetween(50, 2000);
        $completionTokens = fake()->numberBetween(20, 1500);
        $providers = ['openai', 'anthropic', 'google'];

        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement($providers),
            'model' => fake()->randomElement(['gpt-4o', 'gpt-4o-mini', 'claude-3-opus', 'claude-3-sonnet', 'gemini-1.5-pro']),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cost' => fake()->randomFloat(6, 0.001, 0.10),
            'duration' => fake()->numberBetween(100, 5000),
            'endpoint' => fake()->randomElement(['/v1/chat/completions', '/v1/embeddings', '/v1/completions']),
            'success' => fake()->boolean(90),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => ['success' => false]);
    }
}
