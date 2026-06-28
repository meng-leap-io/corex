<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            ['key' => 'theme', 'value' => 'dark', 'type' => 'string', 'category' => 'appearance'],
            ['key' => 'language', 'value' => 'en', 'type' => 'string', 'category' => 'locale'],
            ['key' => 'notifications_enabled', 'value' => 'true', 'type' => 'boolean', 'category' => 'notifications'],
            ['key' => 'auto_sync', 'value' => 'true', 'type' => 'boolean', 'category' => 'sync'],
            ['key' => 'sync_interval', 'value' => '30', 'type' => 'integer', 'category' => 'sync'],
            ['key' => 'editor_font_size', 'value' => '14', 'type' => 'integer', 'category' => 'editor'],
            ['key' => 'editor_tab_size', 'value' => '4', 'type' => 'integer', 'category' => 'editor'],
            ['key' => 'editor_word_wrap', 'value' => 'true', 'type' => 'boolean', 'category' => 'editor'],
            ['key' => 'privacy_share_analytics', 'value' => 'true', 'type' => 'boolean', 'category' => 'privacy'],
            ['key' => 'privacy_online_status', 'value' => 'true', 'type' => 'boolean', 'category' => 'privacy'],
        ];

        User::all()->each(function (User $user) use ($defaults) {
            foreach ($defaults as $default) {
                Setting::factory()->forUser($user->id)->create($default);
            }
        });

        Setting::factory()->global()->create([
            'key' => 'app_name',
            'value' => 'Corex.dev',
            'type' => 'string',
            'category' => 'general',
        ]);

        Setting::factory()->global()->create([
            'key' => 'max_upload_size',
            'value' => '104857600',
            'type' => 'integer',
            'category' => 'storage',
        ]);
    }
}
