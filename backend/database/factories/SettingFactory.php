<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SettingFactory extends Factory
{
    protected $model = Setting::class;

    public function definition(): array
    {
        $settings = [
            'theme' => ['value' => 'dark', 'type' => 'string', 'category' => 'appearance'],
            'language' => ['value' => 'en', 'type' => 'string', 'category' => 'locale'],
            'notifications_enabled' => ['value' => 'true', 'type' => 'boolean', 'category' => 'notifications'],
            'auto_sync' => ['value' => 'true', 'type' => 'boolean', 'category' => 'sync'],
            'sync_interval' => ['value' => '30', 'type' => 'integer', 'category' => 'sync'],
            'editor_font_size' => ['value' => '14', 'type' => 'integer', 'category' => 'editor'],
            'editor_tab_size' => ['value' => '4', 'type' => 'integer', 'category' => 'editor'],
            'privacy_share_analytics' => ['value' => 'true', 'type' => 'boolean', 'category' => 'privacy'],
        ];

        $setting = $this->faker->randomElement($settings);

        return [
            'user_id' => User::factory(),
            'team_id' => null,
            'key' => $this->faker->unique()->randomElement(array_keys($settings)),
            'value' => $setting['value'],
            'type' => $setting['type'],
            'category' => $setting['category'],
            'is_encrypted' => false,
            'metadata' => ['source' => 'default'],
        ];
    }

    public function forUser(string $userId): static
    {
        return $this->state(['user_id' => $userId, 'team_id' => null]);
    }

    public function forTeam(string $teamId): static
    {
        return $this->state(['team_id' => $teamId, 'user_id' => null]);
    }

    public function global(): static
    {
        return $this->state(['user_id' => null, 'team_id' => null]);
    }

    public function encrypted(): static
    {
        return $this->state(['is_encrypted' => true, 'type' => 'encrypted']);
    }

    public function ofCategory(string $category): static
    {
        return $this->state(['category' => $category]);
    }
}
