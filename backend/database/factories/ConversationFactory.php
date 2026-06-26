<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        $models = ['gpt-4o', 'gpt-4o-mini', 'claude-3-opus', 'claude-3-sonnet', 'claude-3-haiku'];

        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'title' => fake()->sentence(4),
            'model_used' => fake()->randomElement($models),
            'messages' => [
                ['role' => 'user', 'content' => fake()->paragraph()],
                ['role' => 'assistant', 'content' => fake()->paragraphs(2, true)],
            ],
            'tokens_used' => fake()->numberBetween(100, 4000),
            'total_cost' => fake()->randomFloat(6, 0.001, 0.05),
        ];
    }
}
