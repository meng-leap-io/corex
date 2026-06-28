<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        $role = $this->faker->randomElement(['user', 'assistant', 'system']);
        $promptTokens = $role === 'user' ? $this->faker->numberBetween(10, 500) : 0;
        $completionTokens = $role === 'assistant' ? $this->faker->numberBetween(50, 2000) : 0;

        return [
            'conversation_id' => Conversation::factory(),
            'user_id' => User::factory(),
            'role' => $role,
            'content' => $this->faker->paragraphs($this->faker->numberBetween(1, 5), true),
            'model_used' => $role === 'assistant' ? $this->faker->randomElement(['gpt-4o', 'gpt-4o-mini', 'claude-3-opus', 'claude-3-sonnet']) : null,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
            'cost' => $this->faker->randomFloat(6, 0.0001, 0.05),
            'metadata' => [],
        ];
    }

    public function user(): static
    {
        return $this->state(['role' => 'user']);
    }

    public function assistant(): static
    {
        return $this->state(fn () => [
            'role' => 'assistant',
            'prompt_tokens' => 0,
            'completion_tokens' => $this->faker->numberBetween(50, 2000),
            'total_tokens' => function (array $attrs) {
                return $attrs['completion_tokens'];
            },
        ]);
    }

    public function system(): static
    {
        return $this->state(['role' => 'system', 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0]);
    }
}
