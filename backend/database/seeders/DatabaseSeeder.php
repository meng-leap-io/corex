<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ProjectSeeder::class,
            ConversationSeeder::class,
            MessageSeeder::class,
            CodeGenerationSeeder::class,
            AiUsageLogSeeder::class,
            ApiKeySeeder::class,
            SubscriptionSeeder::class,
            NotificationSeeder::class,
            FileSeeder::class,
            SettingSeeder::class,
            TeamSeeder::class,
        ]);
    }
}
