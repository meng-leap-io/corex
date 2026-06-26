<?php

namespace Tests\Unit\Models;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@corex.dev',
        ]);

        $this->assertNotNull($user->id);
        $this->assertEquals('test@corex.dev', $user->email);
        $this->assertEquals(User::PLAN_FREE, $user->plan);
        $this->assertTrue($user->isVerified());
    }

    public function test_can_create_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertFalse($user->isVerified());
        $this->assertNull($user->email_verified_at);
    }

    public function test_plan_limits_are_correct(): void
    {
        $freeUser = User::factory()->create(['plan' => 'free']);
        $proUser = User::factory()->pro()->create();
        $teamUser = User::factory()->team()->create();

        $this->assertEquals(1000, $freeUser->getApiLimit());
        $this->assertEquals(10000, $proUser->getApiLimit());
        $this->assertEquals(50000, $teamUser->getApiLimit());
    }

    public function test_user_can_check_plan_feature_access(): void
    {
        $freeUser = User::factory()->create(['plan' => 'free']);
        $proUser = User::factory()->pro()->create();

        $this->assertFalse($freeUser->canAccessFeature('advanced_analytics'));
        $this->assertTrue($proUser->canAccessFeature('advanced_analytics'));
    }

    public function test_user_has_default_settings(): void
    {
        $user = User::factory()->create();

        $this->assertIsArray($user->settings);
        $this->assertEquals('light', $user->settings['theme']);
        $this->assertEquals('en', $user->settings['language']);
    }

    public function test_user_can_have_multiple_api_keys(): void
    {
        $user = User::factory()->create();
        $user->apiKeys()->createMany([
            ['name' => 'Key 1', 'key' => 'key1', 'last_used_at' => null],
            ['name' => 'Key 2', 'key' => 'key2', 'last_used_at' => null],
        ]);

        $this->assertCount(2, $user->apiKeys);
    }

    public function test_user_can_have_multiple_projects(): void
    {
        $user = User::factory()->create();
        Project::factory(3)->create(['user_id' => $user->id]);

        $this->assertCount(3, $user->projects);
    }

    public function test_has_uuid_primary_key(): void
    {
        $user = User::factory()->create();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $user->id);
    }

    public function test_uses_soft_deletes(): void
    {
        $user = User::factory()->create();
        $userId = $user->id;
        $user->delete();

        $this->assertNull(User::find($userId));
        $this->assertNotNull(User::withTrashed()->find($userId));
    }

    public function test_scope_filters_by_plan(): void
    {
        User::factory()->count(3)->create(['plan' => 'free']);
        User::factory()->count(2)->pro()->create();

        $this->assertCount(2, User::byPlan('pro')->get());
        $this->assertCount(3, User::byPlan('free')->get());
    }

    public function test_scope_filters_active_users(): void
    {
        User::factory()->count(5)->create();
        $user = User::first();
        $user->delete();

        $this->assertCount(4, User::active()->get());
    }

    public function test_find_by_email(): void
    {
        User::factory()->create(['email' => 'unique@corex.dev']);
        $this->assertNotNull(User::findByEmail('unique@corex.dev'));
        $this->assertNull(User::findByEmail('nonexistent@corex.dev'));
    }
}
