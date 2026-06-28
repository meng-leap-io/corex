<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'name' => 'My API Project',
        ]);

        $this->assertNotNull($project->id);
        $this->assertEquals('My API Project', $project->name);
        $this->assertEquals(Project::STATUS_DRAFT, $project->status);
        $this->assertEquals($user->id, $project->user_id);
    }

    public function test_project_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($project->user->is($user));
    }

    public function test_project_has_uuid(): void
    {
        $project = Project::factory()->create();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $project->id);
    }

    public function test_project_can_be_archived(): void
    {
        $project = Project::factory()->create();
        $project->markAsArchived();

        $this->assertEquals(Project::STATUS_ARCHIVED, $project->fresh()->status);
    }

    public function test_project_can_have_conversations(): void
    {
        $project = Project::factory()->create();
        $project->conversations()->createMany([
            ['user_id' => $project->user_id, 'title' => 'Chat 1', 'messages' => []],
            ['user_id' => $project->user_id, 'title' => 'Chat 2', 'messages' => []],
        ]);

        $this->assertCount(2, $project->conversations);
    }

    public function test_project_can_have_code_generations(): void
    {
        $project = Project::factory()->create();
        $project->codeGenerations()->createMany([
            ['user_id' => $project->user_id, 'prompt' => 'Generate API', 'code_generated' => '<?php', 'language' => 'php', 'status' => 'completed'],
            ['user_id' => $project->user_id, 'prompt' => 'Generate test', 'code_generated' => 'test()', 'language' => 'php', 'status' => 'completed'],
        ]);

        $this->assertCount(2, $project->codeGenerations);
    }

    public function test_scope_filters_by_status(): void
    {
        Project::factory()->count(2)->create(['status' => Project::STATUS_DRAFT]);
        Project::factory()->count(3)->create(['status' => Project::STATUS_ACTIVE]);

        $this->assertCount(2, Project::byStatus(Project::STATUS_DRAFT)->get());
        $this->assertCount(3, Project::byStatus(Project::STATUS_ACTIVE)->get());
    }

    public function test_scope_excludes_archived(): void
    {
        Project::factory()->count(4)->create();
        Project::first()->markAsArchived();

        $this->assertCount(3, Project::withoutArchived()->get());
    }

    public function test_soft_delete(): void
    {
        $project = Project::factory()->create();
        $id = $project->id;
        $project->delete();

        $this->assertNull(Project::find($id));
        $this->assertNotNull(Project::withTrashed()->find($id));
    }

    public function test_slug_is_generated_on_create(): void
    {
        $project = Project::factory()->create(['name' => 'My Awesome Project']);
        $this->assertNotNull($project->slug);
        $this->assertStringContainsString('my-awesome-project', $project->slug);
    }
}
