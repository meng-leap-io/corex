<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bio' => fake()->paragraph(2),
            'company' => fake()->company(),
            'website' => fake()->url(),
            'location' => fake()->city() . ', ' . fake()->country(),
            'twitter' => '@' . fake()->userName(),
            'github' => fake()->userName(),
            'expertise' => fake()->randomElements(['PHP', 'Laravel', 'Vue.js', 'React', 'Python', 'Go', 'DevOps'], 3),
            'skills' => fake()->randomElements(['REST APIs', 'CI/CD', 'Docker', 'Kubernetes', 'PostgreSQL', 'Redis'], 4),
            'public_email' => fake()->boolean() ? fake()->email() : null,
            'notification_settings' => [
                'email' => true,
                'push' => false,
                'digest' => 'weekly',
            ],
        ];
    }
}
