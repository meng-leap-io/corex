<?php

namespace Tests\Unit\Models;

use App\Models\Conversation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_conversation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
            'title' => 'Debugging Session',
        ]);

        $this->assertNotNull($conversation->id);
        $this->assertEquals('Debugging Session', $conversation->title);
    }

    public function test_conversation_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($conversation->user->is($user));
    }

    public function test_conversation_belongs_to_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $conversation = Conversation::factory()->create([
            'user_id' => $user->id,
            'project_id' => $project->id,
        ]);

        $this->assertTrue($conversation->project->is($project));
    }

    public function test_conversation_stores_messages_as_json(): void
    {
        $conversation = Conversation::factory()->create([
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'content' => 'Hi there'],
            ],
        ]);

        $this->assertIsArray($conversation->messages);
        $this->assertCount(2, $conversation->messages);
        $this->assertEquals('Hello', $conversation->messages[0]['content']);
    }

    public function test_conversation_can_be_archived(): void
    {
        $conversation = Conversation::factory()->create();
        $conversation->archive();

        $this->assertNotNull($conversation->archived_at);
    }

    public function test_scope_excludes_archived(): void
    {
        Conversation::factory()->count(3)->create();
        Conversation::first()->archive();

        $this->assertCount(2, Conversation::active()->get());
    }

    public function test_has_uuid(): void
    {
        $conversation = Conversation::factory()->create();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $conversation->id);
    }
}
