<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Corex Admin',
            'email' => 'admin@corex.dev',
            'password' => Hash::make('admin123'),
            'plan' => 'team',
            'role' => 'admin',
            'api_usage_limit' => 100000,
        ]);

        $pro = User::factory()->pro()->create([
            'name' => 'Jane Developer',
            'email' => 'jane@example.com',
            'password' => Hash::make('password'),
        ]);

        User::factory()->create([
            'name' => 'John Starter',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
        ]);

        User::factory(8)->create();
        User::factory(3)->unverified()->create();
    }
}
