<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private array $registerData = [
        'name' => 'Test User',
        'email' => 'test@corex.dev',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ];

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', $this->registerData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@corex.dev',
            'name' => 'Test User',
        ]);
    }

    public function test_registration_requires_name(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'test@corex.dev',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_registration_requires_valid_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test',
            'email' => 'not-an-email',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_registration_requires_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test',
            'email' => 'test@corex.dev',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'login@corex.dev',
            'password' => Hash::make('CorrectPass123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@corex.dev',
            'password' => 'CorrectPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'secure@corex.dev',
            'password' => Hash::make('real_password'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'secure@corex.dev',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'ghost@corex.dev',
            'password' => 'any_password',
        ]);

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/auth/logout');

        $response->assertStatus(200);
        $this->assertCount(0, $user->fresh()->tokens);
    }

    public function test_logout_fails_without_token(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    public function test_user_can_get_current_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'email' => $user->email,
            ]);
    }

    public function test_user_can_verify_email(): void
    {
        $user = User::factory()->unverified()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withToken($token)
            ->postJson('/api/auth/verify');

        $response->assertStatus(200);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_can_request_password_reset(): void
    {
        User::factory()->create(['email' => 'reset@corex.dev']);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'reset@corex.dev',
        ]);

        $response->assertStatus(200);
    }

    public function test_forgot_password_accepts_missing_email(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'ghost@corex.dev',
        ]);

        $response->assertStatus(200);
    }

    public function test_register_sets_default_plan(): void
    {
        $response = $this->postJson('/api/auth/register', $this->registerData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'test@corex.dev',
            'plan' => 'free',
        ]);
    }
}
