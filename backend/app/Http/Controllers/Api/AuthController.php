<?php

namespace App\Http\Controllers\Api;

use App\Contracts\SupabaseAuthContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ?SupabaseAuthContract $supabase = null,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            $user = $this->authService->register($request->validated());
            $token = $this->authService->createToken($user);

            return $this->success(
                data: [
                    'user' => new UserResource($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration') * 60,
                ],
                message: 'Registration successful.',
                code: 201,
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'registration_failed',
                'Registration failed. Please try again.',
                $e,
                500,
                ['email' => $request->email],
            );
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return $this->error(
                "Too many login attempts. Try again in {$seconds} seconds.",
                429,
            );
        }

        try {
            $user = $this->authService->login(
                $request->email,
                $request->password,
            );

            if (!$user) {
                RateLimiter::hit($key, 60);
                return $this->error('Invalid email or password.', 401);
            }

            RateLimiter::clear($key);

            $token = $this->authService->createToken($user);

            return $this->success([
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration') * 60,
            ], 'Login successful.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'login_failed',
                'Login failed. Please try again.',
                $e,
                500,
            );
        }
    }

    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->unauthenticated();
            }

            $token = $this->authService->createToken($user);

            return $this->success([
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration') * 60,
            ], 'Token refreshed successfully.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'token_refresh_failed',
                'Failed to refresh token.',
                $e,
                500,
            );
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->unauthenticated();
            }

            $this->authService->revokeCurrentToken($user);

            return $this->success(message: 'Logged out successfully.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'logout_failed',
                'Logout failed.',
                $e,
                500,
            );
        }
    }

    public function verify(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->unauthenticated();
            }

            if ($user->isVerified()) {
                return $this->success(message: 'Email is already verified.');
            }

            $this->authService->verifyEmail($user);

            return $this->success(message: 'Email verified successfully.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'email_verification_failed',
                'Email verification failed.',
                $e,
                500,
            );
        }
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $key = 'forgot_password:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            return $this->error(
                "Too many requests. Try again in {$seconds} seconds.",
                429,
            );
        }

        try {
            $this->authService->sendPasswordResetLink($request->email);
            RateLimiter::hit($key, 3600);

            return $this->success(
                message: 'Password reset link sent to your email.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'forgot_password_failed',
                'Failed to send reset link.',
                $e,
                500,
                ['email' => $request->email],
            );
        }
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $this->authService->resetPassword(
                $request->email,
                $request->token,
                $request->password,
            );

            return $this->success(
                message: 'Password reset successful. Please log in with your new password.',
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'reset_password_failed',
                'Failed to reset password.',
                $e,
                500,
            );
        }
    }

    // ── Supabase Auth Methods ─────────────────────────────────────────────────

    public function supabaseRegister(RegisterRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->registerWithSupabase($request->validated());
            $token = $this->authService->createToken($result['user']);

            return $this->success(
                data: [
                    'user' => new UserResource($result['user']),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'access_token' => $result['session']['access_token'],
                    'refresh_token' => $result['session']['refresh_token'],
                    'expires_in' => $result['session']['expires_in'] ?? config('sanctum.expiration') * 60,
                ],
                message: 'Supabase registration successful.',
                code: 201,
            );
        } catch (\Throwable $e) {
            return $this->logAndError(
                'supabase_registration_failed',
                'Supabase registration failed.',
                $e,
                500,
                ['email' => $request->email],
            );
        }
    }

    public function supabaseLogin(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->loginWithSupabase(
                $request->email,
                $request->password,
            );

            $token = $this->authService->createToken($result['user']);

            return $this->success([
                'user' => new UserResource($result['user']),
                'token' => $token,
                'token_type' => 'Bearer',
                'access_token' => $result['session']['access_token'],
                'refresh_token' => $result['session']['refresh_token'],
                'expires_in' => $result['session']['expires_in'] ?? config('sanctum.expiration') * 60,
            ], 'Supabase login successful.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'supabase_login_failed',
                'Supabase login failed.',
                $e,
                500,
            );
        }
    }

    public function supabaseOAuth(Request $request, string $provider): JsonResponse
    {
        if (!$this->supabase) {
            return $this->error('Supabase auth not configured.', 500);
        }

        $redirectUrl = $request->input('redirect_url', config('supabase.auth.redirect_url'));

        try {
            $url = $this->supabase->signInWithProvider($provider, $redirectUrl);

            return $this->success([
                'url' => $url,
                'provider' => $provider,
                'redirect_url' => $redirectUrl,
            ], "Redirecting to {$provider} for authentication.");
        } catch (\Throwable $e) {
            return $this->logAndError(
                'supabase_oauth_failed',
                "Failed to initiate {$provider} OAuth.",
                $e,
                500,
                ['provider' => $provider],
            );
        }
    }

    public function supabaseCallback(Request $request): JsonResponse
    {
        if (!$this->supabase) {
            return $this->error('Supabase auth not configured.', 500);
        }

        $code = $request->input('code');
        $redirectUrl = $request->input('redirect_url', config('supabase.auth.redirect_url'));

        if (!$code) {
            return $this->error('Authorization code is required.', 400);
        }

        try {
            $session = $this->supabase->exchangeCode($code, $redirectUrl);

            $user = $this->supabase->verifySupabaseToken($session['access_token']);

            if (!$user) {
                return $this->error('Failed to resolve user from session.', 500);
            }

            $token = $this->authService->createToken($user);

            return $this->success([
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
                'access_token' => $session['access_token'],
                'refresh_token' => $session['refresh_token'],
                'provider' => $session['user']['app_metadata']['provider'] ?? 'unknown',
            ], 'OAuth authentication successful.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'supabase_callback_failed',
                'OAuth callback processing failed.',
                $e,
                500,
            );
        }
    }

    public function supabaseRefresh(Request $request): JsonResponse
    {
        if (!$this->supabase) {
            return $this->error('Supabase auth not configured.', 500);
        }

        $refreshToken = $request->input('refresh_token');

        if (!$refreshToken) {
            return $this->error('Refresh token is required.', 400);
        }

        try {
            $session = $this->supabase->refreshSession($refreshToken);

            return $this->success([
                'access_token' => $session['access_token'],
                'refresh_token' => $session['refresh_token'],
                'expires_in' => $session['expires_in'],
            ], 'Session refreshed successfully.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'supabase_refresh_failed',
                'Failed to refresh Supabase session.',
                $e,
                500,
            );
        }
    }

    public function supabaseLogout(Request $request): JsonResponse
    {
        if (!$this->supabase) {
            return $this->error('Supabase auth not configured.', 500);
        }

        $supabaseToken = $request->input('supabase_token') ?? $request->bearerToken();

        try {
            if ($supabaseToken) {
                $this->supabase->signOut($supabaseToken);
            }

            $this->authService->revokeCurrentToken($request->user());

            return $this->success(message: 'Logged out from all sessions.');
        } catch (\Throwable $e) {
            return $this->logAndError(
                'supabase_logout_failed',
                'Failed to sign out.',
                $e,
                500,
            );
        }
    }
}
