<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        Conversation::all()->each(function (Conversation $conversation) {
            $count = fake()->numberBetween(2, 15);
            $sequence = 0;

            // System message first
            Message::factory()->system()->create([
                'conversation_id' => $conversation->id,
                'user_id' => null,
                'sequence' => $sequence++,
                'content' => 'You are a helpful coding assistant. Respond concisely with working code.',
            ]);

            // User-assistant pairs
            for ($i = 0; $i < $count; $i++) {
                Message::factory()->user()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $conversation->user_id,
                    'sequence' => $sequence++,
                ]);
                Message::factory()->assistant()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => null,
                    'sequence' => $sequence++,
                ]);
            }
        });
    }
}
