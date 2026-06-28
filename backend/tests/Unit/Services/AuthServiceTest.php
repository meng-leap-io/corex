<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authService = new AuthService;
    }

    public function test_can_register_user(): void
    {
        $user = $this->authService->register([
            'name' => 'New User',
            'email' => 'newuser@corex.dev',
            'password' => 'SecurePass123!',
        ]);

        $this->assertNotNull($user->id);
        $this->assertEquals('newuser@corex.dev', $user->email);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));
        $this->assertEquals(User::PLAN_FREE, $user->plan);
        $this->assertEquals(1000, $user->api_usage_limit);
    }

    public function test_can_login_with_valid_credentials(): void
    {
        $password = 'CorrectPassword123!';
        User::factory()->create([
            'email' => 'login@corex.dev',
            'password' => Hash::make($password),
        ]);

        $result = $this->authService->login('login@corex.dev', $password);

        $this->assertNotNull($result);
        $this->assertEquals('login@corex.dev', $result->email);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'secure@corex.dev',
            'password' => Hash::make('real_password'),
        ]);

        $result = $this->authService->login('secure@corex.dev', 'wrong_password');

        $this->assertNull($result);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $result = $this->authService->login('ghost@corex.dev', 'any_password');
        $this->assertNull($result);
    }

    public function test_can_create_sanctum_token(): void
    {
        $user = User::factory()->create();
        $token = $this->authService->createToken($user, 'test-device');

        $this->assertIsString($token);
        $this->assertStringContainsString('|', $token);
    }

    public function test_create_token_deletes_previous_device_tokens(): void
    {
        $user = User::factory()->create();
        $this->authService->createToken($user, 'mobile');
        $this->authService->createToken($user, 'mobile');

        $this->assertCount(1, $user->tokens);
    }

    public function test_can_revoke_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api');
        $user->withAccessToken($token->accessToken);

        $this->authService->revokeCurrentToken($user);

        $this->assertCount(0, $user->tokens()->get());
    }

    public function test_can_refresh_token(): void
    {
        $user = User::factory()->create();
        $originalToken = $this->authService->createToken($user, 'api');
        $newToken = $this->authService->refreshToken($user, 'api');

        $this->assertIsString($newToken);
        $this->assertNotEquals($originalToken, $newToken);
    }

    public function test_initiate_password_reset_creates_token(): void
    {
        $user = User::factory()->create(['email' => 'reset@corex.dev']);
        $token = $this->authService->initiatePasswordReset('reset@corex.dev');

        $this->assertNotNull($token);
        $this->assertIsString($token);
    }

    public function test_password_reset_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'reset@corex.dev']);
        $token = $this->authService->initiatePasswordReset('reset@corex.dev');

        $result = $this->authService->resetPassword(
            'reset@corex.dev',
            $token,
            'NewSecurePass456!',
        );

        $this->assertTrue($result['success']);
        $this->assertTrue(Hash::check('NewSecurePass456!', $user->fresh()->password));
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        User::factory()->create(['email' => 'reset2@corex.dev']);

        $result = $this->authService->resetPassword(
            'reset2@corex.dev',
            'invalid-token',
            'NewPassword789!',
        );

        $this->assertFalse($result['success']);
    }
}
