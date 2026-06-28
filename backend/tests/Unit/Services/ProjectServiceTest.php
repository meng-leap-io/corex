<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProjectService $projectService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectService = $this->app->make(ProjectService::class);
    }

    public function test_can_create_project(): void
    {
        $user = User::factory()->create();

        $project = $this->projectService->create($user, [
            'name' => 'Test Project',
            'description' => 'A test project description',
            'language' => 'PHP',
        ]);

        $this->assertNotNull($project->id);
        $this->assertEquals('Test Project', $project->name);
        $this->assertEquals('PHP', $project->language);
        $this->assertEquals(Project::STATUS_DRAFT, $project->status);
        $this->assertEquals($user->id, $project->user_id);
    }

    public function test_create_generates_unique_slug(): void
    {
        $user = User::factory()->create();

        $project = $this->projectService->create($user, ['name' => 'My API Project']);

        $this->assertNotNull($project->slug);
        $this->assertStringContainsString('my-api-project', $project->slug);
    }

    public function test_can_update_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'name' => 'Original Name',
        ]);

        $updated = $this->projectService->update($project, [
            'name' => 'Updated Name',
            'description' => 'Updated description',
        ]);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('Updated description', $updated->description);
    }

    public function test_update_generates_new_slug_on_name_change(): void
    {
        $project = Project::factory()->create(['name' => 'Original']);

        $updated = $this->projectService->update($project, ['name' => 'New Name']);

        $this->assertStringContainsString('new-name', $updated->slug);
    }

    public function test_can_archive_project(): void
    {
        $project = Project::factory()->create();

        $this->projectService->archive($project);

        $this->assertEquals(Project::STATUS_ARCHIVED, $project->fresh()->status);
    }

    public function test_can_duplicate_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $user->id,
            'name' => 'Original Project',
        ]);

        $duplicate = $this->projectService->duplicate($project, $user);

        $this->assertNotEquals($project->id, $duplicate->id);
        $this->assertStringContainsString('Original Project', $duplicate->name);
        $this->assertEquals(Project::STATUS_DRAFT, $duplicate->status);
    }

    public function test_can_list_user_projects(): void
    {
        $user = User::factory()->create();
        Project::factory(5)->create(['user_id' => $user->id]);

        $projects = $this->projectService->listForUser($user);

        $this->assertCount(5, $projects);
    }

    public function test_list_excludes_archived_by_default(): void
    {
        $user = User::factory()->create();
        Project::factory(3)->create(['user_id' => $user->id]);
        Project::first()->markAsArchived();

        $projects = $this->projectService->listForUser($user);

        $this->assertCount(2, $projects);
    }
}
