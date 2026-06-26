<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthService
{
    public function __construct(
        private readonly int $tokenExpirationDays = 7,
        private readonly int $resetTokenExpirationMinutes = 60,
    ) {}

    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        Log::info('auth.user_registered', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $user;
    }

    public function login(string $email, string $password): ?User
    {
        $user = User::findByEmail($email);

        if (!$user || !Hash::check($password, $user->password)) {
            Log::warning('auth.login_failed', ['email' => $email]);
            return null;
        }

        if (!$user->isVerified()) {
            Log::info('auth.login_unverified', ['user_id' => $user->id]);
        }

        Log::info('auth.user_logged_in', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $user;
    }

    public function createToken(User $user, string $device = 'api'): string
    {
        $user->tokens()->where('name', $device)->delete();

        return $user->createToken(
            $device,
            ['*'],
            now()->addDays($this->tokenExpirationDays),
        )->plainTextToken;
    }

    public function refreshToken(User $user, string $device = 'api'): string
    {
        return $this->createToken($user, $device);
    }

    public function revokeCurrentToken(User $user): void
    {
        if ($user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
            Log::info('auth.user_logged_out', ['user_id' => $user->id]);
        }
    }

    public function revokeAllTokens(User $user): void
    {
        $count = $user->tokens()->count();
        $user->tokens()->delete();

        Log::info('auth.tokens_revoked', [
            'user_id' => $user->id,
            'count' => $count,
        ]);
    }

    public function revokeTokenById(User $user, string $tokenId): bool
    {
        $token = $user->tokens()->find($tokenId);

        if (!$token) {
            return false;
        }

        $token->delete();
        return true;
    }

    public function verifyEmail(User $user): void
    {
        if ($user->isVerified()) {
            return;
        }

        $user->markAsVerified();

        Log::info('auth.email_verified', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function sendEmailVerification(User $user): void
    {
        $user->notify(new VerifyEmailNotification());
    }

    public function sendPasswordResetLink(string $email): void
    {
        $user = User::findByEmail($email);

        if (!$user) {
            return;
        }

        $token = Str::random(64);

        Cache::put(
            "password_reset_{$email}",
            ['token' => $token, 'email' => $email],
            now()->addMinutes($this->resetTokenExpirationMinutes),
        );

        $user->notify(new PasswordResetNotification($token));

        Log::info('auth.password_reset_requested', ['email' => $email]);
    }

    public function resetPassword(string $email, string $token, string $password): bool
    {
        $cacheKey = "password_reset_{$email}";
        $cached = Cache::get($cacheKey);

        if (!$cached || !hash_equals($cached['token'], $token)) {
            Log::warning('auth.password_reset_invalid_token', ['email' => $email]);
            return false;
        }

        $user = User::findByEmail($email);

        if (!$user) {
            return false;
        }

        $user->update(['password' => $password]);
        $this->revokeAllTokens($user);
        Cache::forget($cacheKey);

        Log::info('auth.password_reset_completed', ['user_id' => $user->id]);

        return true;
    }

    public function validateResetToken(string $email, string $token): bool
    {
        $cached = Cache::get("password_reset_{$email}");

        return $cached && hash_equals($cached['token'], $token);
    }

    public function loginAsUser(User $user, string $device = 'impersonate'): string
    {
        Log::warning('auth.impersonation', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $this->createToken($user, $device);
    }
}
