<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_get_profile(): void
    {
        $user = User::factory()->create();
        $user->profile()->create(['bio' => 'Hello world']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/user/profile');

        $response->assertStatus(200)
            ->assertJsonPath('bio', 'Hello world');
    }

    public function test_profile_requires_auth(): void
    {
        $response = $this->getJson('/api/user/profile');
        $response->assertStatus(401);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::factory()->create();
        $user->profile()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->putJson('/api/user/profile', [
                'bio' => 'Updated bio',
                'company' => 'Corex Inc.',
                'twitter' => '@corex',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('profiles', [
            'user_id' => $user->id,
            'bio' => 'Updated bio',
            'company' => 'Corex Inc.',
        ]);
    }

    public function test_profile_update_validates_data(): void
    {
        $user = User::factory()->create();
        $user->profile()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->putJson('/api/user/profile', [
                'website' => 'not-a-url',
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_list_users(): void
    {
        User::factory()->count(3)->create();

        $admin = User::factory()->create(['plan' => 'team']);
        $token = $admin->createToken('api', ['admin'])->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/users/'.User::skip(1)->first()->id);

        $response->assertStatus(200);
    }

    public function test_admin_can_update_other_user(): void
    {
        $target = User::factory()->create(['name' => 'Original']);
        $admin = User::factory()->create(['plan' => 'team']);
        $token = $admin->createToken('api', ['admin'])->plainTextToken;

        $response = $this->withToken($token)
            ->putJson('/api/users/'.$target->id, [
                'name' => 'Updated Name',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Updated Name']);
    }

    public function test_admin_can_delete_user(): void
    {
        $target = User::factory()->create();
        $admin = User::factory()->create(['plan' => 'team']);
        $token = $admin->createToken('api', ['admin'])->plainTextToken;

        $response = $this->withToken($token)
            ->deleteJson('/api/users/'.$target->id);

        $response->assertStatus(200);
        $this->assertSoftDeleted($target);
    }

    public function test_non_admin_cannot_delete_users(): void
    {
        $target = User::factory()->create();
        $user = User::factory()->create(['plan' => 'free']);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->deleteJson('/api/users/'.$target->id);

        $response->assertStatus(403);
    }
}
