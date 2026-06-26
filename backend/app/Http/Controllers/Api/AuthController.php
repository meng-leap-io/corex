<?php

namespace App\Http\Controllers\Api;

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
}
