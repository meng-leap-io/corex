<?php

declare(strict_types=1);

namespace App\Services\Supabase;

use App\Contracts\SupabaseAuthContract;
use App\Models\User;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseAuthService implements SupabaseAuthContract
{
    private string $url;

    private string $key;

    private string $jwtSecret;

    public function __construct(
        private readonly SupabaseService $supabase,
    ) {
        $this->url = rtrim(config('supabase.url', ''), '/');
        $this->key = config('supabase.key', '');
        $this->jwtSecret = config('supabase.jwt_secret', '');
    }

    public function signUp(string $email, string $password, array $options = []): array
    {
        $data = array_merge([
            'email' => $email,
            'password' => $password,
        ], $options);

        if (config('supabase.auth.auto_confirm')) {
            $data['data'] = array_merge($options['data'] ?? [], ['email_confirmed' => true]);
        }

        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$this->key}",
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/signup", $data);

        if ($response->failed()) {
            Log::error('supabase.auth.signup_failed', [
                'email' => $email,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        $result = $response->json();

        Log::info('supabase.auth.signup_success', [
            'email' => $email,
            'id' => $result['user']['id'] ?? null,
        ]);

        return $result;
    }

    public function signIn(string $email, string $password): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$this->key}",
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/token?grant_type=password", [
            'email' => $email,
            'password' => $password,
        ]);

        if ($response->failed()) {
            Log::warning('supabase.auth.login_failed', ['email' => $email]);
            $response->throw();
        }

        return $response->json();
    }

    public function signInWithProvider(string $provider, string $redirectUrl): string
    {
        $params = http_build_query([
            'provider' => $provider,
            'redirect_to' => $redirectUrl,
        ]);

        $url = "{$this->url}/auth/v1/authorize?{$params}";

        Log::info('supabase.auth.oauth_redirect', [
            'provider' => $provider,
            'redirect_url' => $redirectUrl,
        ]);

        return $url;
    }

    public function signOut(string $accessToken): void
    {
        Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$accessToken}",
        ])->post("{$this->url}/auth/v1/logout");

        Log::info('supabase.auth.logout', ['token' => substr($accessToken, 0, 10).'...']);
    }

    public function getUser(string $accessToken): ?array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$accessToken}",
        ])->get("{$this->url}/auth/v1/user");

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    public function exchangeCode(string $code, string $redirectUrl): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$this->key}",
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/token?grant_type=authorization_code", [
            'auth_code' => $code,
            'redirect_uri' => $redirectUrl,
        ]);

        if ($response->failed()) {
            Log::error('supabase.auth.code_exchange_failed', ['status' => $response->status()]);
            $response->throw();
        }

        return $response->json();
    }

    public function refreshSession(string $refreshToken): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$this->key}",
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/token?grant_type=refresh_token", [
            'refresh_token' => $refreshToken,
        ]);

        if ($response->failed()) {
            Log::error('supabase.auth.refresh_failed', ['status' => $response->status()]);
            $response->throw();
        }

        return $response->json();
    }

    public function sendPasswordReset(string $email): void
    {
        Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$this->key}",
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/auth/v1/recover", [
            'email' => $email,
        ]);
    }

    public function updatePassword(string $accessToken, string $newPassword): void
    {
        Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
        ])->put("{$this->url}/auth/v1/user", [
            'password' => $newPassword,
        ]);
    }

    public function verifySupabaseToken(string $jwt): ?User
    {
        try {
            $payload = $this->decodeToken($jwt);

            if (! $payload || ! isset($payload['sub'])) {
                return null;
            }

            $supabaseUserId = $payload['sub'];
            $email = $payload['email'] ?? null;

            $user = User::where('supabase_id', $supabaseUserId)->first();

            if (! $user && $email) {
                $user = User::where('email', $email)->first();

                if ($user) {
                    $user->update(['supabase_id' => $supabaseUserId]);
                }
            }

            if (! $user && $email) {
                $user = User::create([
                    'supabase_id' => $supabaseUserId,
                    'email' => $email,
                    'name' => $payload['user_metadata']['name'] ?? $email,
                    'avatar' => $payload['user_metadata']['avatar_url'] ?? null,
                    'email_verified_at' => $payload['email_verified'] ?? false ? now() : null,
                ]);
            }

            return $user;

        } catch (\Throwable $e) {
            Log::error('supabase.auth.token_verification_failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function decodeToken(string $jwt): ?array
    {
        if ($this->jwtSecret) {
            try {
                $decoded = JWT::decode($jwt, new Key($this->jwtSecret, 'HS256'));

                return (array) $decoded;
            } catch (\Throwable $e) {
                Log::warning('supabase.auth.jwt_decode_failed', ['error' => $e->getMessage()]);

                return null;
            }
        }

        return $this->verifyWithJWKS($jwt);
    }

    private function verifyWithJWKS(string $jwt): ?array
    {
        try {
            $jwks = Cache::remember('supabase_jwks', 3600, function () {
                $projectRef = parse_url($this->url, PHP_URL_HOST);
                $response = Http::get("https://{$projectRef}/.well-known/jwks.json");

                if ($response->failed()) {
                    throw new \RuntimeException('Failed to fetch Supabase JWKS');
                }

                return $response->json();
            });

            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($jwt, $keys);

            return (array) $decoded;
        } catch (\Throwable $e) {
            Log::error('supabase.auth.jwks_verification_failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
