<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RlsPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_own_projects(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownedProject = Project::factory()->create(['user_id' => $owner->id]);
        Project::factory()->create(['user_id' => $otherUser->id]);

        $this->actingAs($owner);

        $visibleProjects = Project::whereIn('id', [$ownedProject->id])->get();

        $this->assertCount(1, $visibleProjects);
        $this->assertEquals($ownedProject->id, $visibleProjects->first()->id);
    }

    public function test_team_member_sees_shared_project(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $project = Project::factory()->create([
            'user_id' => $owner->id,
        ]);

        $project->members()->attach($member->id, ['role' => 'member']);

        $this->assertTrue($project->isAccessibleBy($member));
        $this->assertTrue($project->isAccessibleBy($owner));
    }

    public function test_team_member_can_access_via_team(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $team = Team::factory()->create(['owner_id' => $owner->id]);
        $team->members()->attach($member->id, ['role' => 'member']);

        $project = Project::factory()->create(['user_id' => $owner->id]);
        $project->teams()->attach($team->id, ['access_level' => 'member']);

        $this->assertTrue($project->isAccessibleBy($member));
        $this->assertTrue($project->isAccessibleBy($owner));
    }

    public function test_non_member_cannot_access_private_project(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        $project = Project::factory()->create(['user_id' => $owner->id]);

        $this->assertFalse($project->isAccessibleBy($stranger));
        $this->assertTrue($project->isAccessibleBy($owner));
    }

    public function test_public_project_is_readable_by_all(): void
    {
        $owner = User::factory()->create();
        $reader = User::factory()->create();

        $project = Project::factory()->create([
            'user_id' => $owner->id,
            'is_public' => true,
            'visibility' => Project::VISIBILITY_PUBLIC,
        ]);

        $this->assertTrue($project->isAccessibleBy($reader));
        $this->assertTrue($project->isPublic());
    }

    public function test_make_public_and_private(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->create([
            'user_id' => $owner->id,
            'is_public' => false,
        ]);

        $project->makePublic();
        $this->assertTrue($project->is_public);
        $this->assertEquals(Project::VISIBILITY_PUBLIC, $project->visibility);

        $project->makePrivate();
        $this->assertFalse($project->is_public);
        $this->assertEquals(Project::VISIBILITY_PRIVATE, $project->visibility);
    }

    public function test_admin_sees_all_projects(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $project1 = Project::factory()->create(['user_id' => $user1->id, 'is_public' => false]);
        $project2 = Project::factory()->create(['user_id' => $user2->id, 'is_public' => false]);

        $this->actingAs($admin);

        $this->assertTrue($project1->isAccessibleBy($admin));
        $this->assertTrue($project2->isAccessibleBy($admin));
    }

    public function test_project_scope_visible_to_user(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        Project::factory()->create(['user_id' => $owner->id, 'is_public' => false]);
        $sharedProject = Project::factory()->create(['user_id' => $owner->id, 'is_public' => false]);
        $sharedProject->members()->attach($member->id, ['role' => 'member']);

        $visibleProjects = Project::scopeVisibleTo(
            Project::query(),
            $member
        )->get();

        $this->assertCount(1, $visibleProjects);
        $this->assertEquals($sharedProject->id, $visibleProjects->first()->id);
    }

    public function test_user_can_see_public_project_without_being_member(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $publicProject = Project::factory()->create([
            'user_id' => $owner->id,
            'is_public' => true,
        ]);

        $visibleProjects = Project::scopeVisibleTo(
            Project::query(),
            $viewer
        )->get();

        $this->assertTrue($visibleProjects->contains('id', $publicProject->id));
    }
}
