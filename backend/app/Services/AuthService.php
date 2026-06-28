<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SupabaseAuthContract;
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
        private readonly int $rememberTokenLength = 64,
        private readonly ?SupabaseAuthContract $supabase = null,
    ) {}

    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        if ($this->supabase && config('supabase.auth.auto_confirm')) {
            try {
                $this->supabase->signUp($data['email'], $data['password']);
            } catch (\Throwable $e) {
                Log::warning('auth.supabase_signup_failed', [
                    'email' => $data['email'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

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

    public function registerWithSupabase(array $data): array
    {
        if (!$this->supabase) {
            throw new \RuntimeException('Supabase auth not configured.');
        }

        $result = $this->supabase->signUp(
            $data['email'],
            $data['password'],
            ['data' => ['name' => $data['name'] ?? '']],
        );

        $user = User::create([
            'supabase_id' => $result['user']['id'],
            'name' => $data['name'] ?? explode('@', $data['email'])[0],
            'email' => $data['email'],
            'password' => $data['password'],
            'email_verified_at' => config('supabase.auth.auto_confirm') ? now() : null,
        ]);

        return [
            'user' => $user,
            'session' => $result,
        ];
    }

    public function loginWithSupabase(string $email, string $password): array
    {
        if (!$this->supabase) {
            throw new \RuntimeException('Supabase auth not configured.');
        }

        $session = $this->supabase->signIn($email, $password);

        $supabaseUser = $session['user'];
        $user = User::where('supabase_id', $supabaseUser['id'])->first();

        if (!$user) {
            $user = User::where('email', $email)->first();

            if ($user) {
                $user->update(['supabase_id' => $supabaseUser['id']]);
            } else {
                $user = User::create([
                    'supabase_id' => $supabaseUser['id'],
                    'name' => $supabaseUser['user_metadata']['name'] ?? explode('@', $email)[0],
                    'email' => $email,
                    'password' => bcrypt(Str::random(32)),
                    'email_verified_at' => $supabaseUser['email_confirmed_at'] ? now() : null,
                ]);
            }
        }

        return [
            'user' => $user,
            'session' => $session,
        ];
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

    public function createRememberToken(User $user): string
    {
        $token = Str::random($this->rememberTokenLength);

        $user->forceFill([
            'remember_token' => hash('sha256', $token),
        ])->save();

        return $token;
    }

    public function validateRememberToken(User $user, string $token): bool
    {
        return $user->remember_token && hash_equals($user->remember_token, hash('sha256', $token));
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

    public function revokeTokensExceptDevice(User $user, string $device): void
    {
        $user->tokens()->where('name', '!=', $device)->delete();
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

    public function findOrCreateUserFromSupabase(array $supabaseUser): User
    {
        $user = User::where('supabase_id', $supabaseUser['id'])->first();

        if ($user) {
            return $user;
        }

        $email = $supabaseUser['email'] ?? null;
        if ($email) {
            $user = User::where('email', $email)->first();

            if ($user) {
                $user->update(['supabase_id' => $supabaseUser['id']]);
                return $user;
            }
        }

        return User::create([
            'supabase_id' => $supabaseUser['id'],
            'name' => $supabaseUser['user_metadata']['name']
                ?? $supabaseUser['email']
                ?? 'User',
            'email' => $email ?? 'unknown-' . $supabaseUser['id'] . '@placeholder.local',
            'password' => bcrypt(Str::random(32)),
            'email_verified_at' => now(),
            'avatar' => $supabaseUser['user_metadata']['avatar_url'] ?? null,
        ]);
    }
}
