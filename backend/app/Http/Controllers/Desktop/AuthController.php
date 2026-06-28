<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Contracts\SupabaseAuthContract;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\OfflineAuthCache;
use App\Services\Auth\SupabaseSessionService;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly SupabaseAuthContract $supabase,
        private readonly SupabaseSessionService $sessionService,
        private readonly OfflineAuthCache $offlineCache,
    ) {}

    // ── View Renderers ─────────────────────────────────────────────────────

    public function showLogin(): View
    {
        return view('auth.desktop.login');
    }

    public function showRegister(): View
    {
        return view('auth.desktop.register');
    }

    public function showForgotPassword(): View
    {
        return view('auth.desktop.forgot-password');
    }

    public function showResetPassword(string $token): View
    {
        return view('auth.desktop.reset-password', ['token' => $token]);
    }

    public function showVerifyEmail(): View
    {
        return view('auth.desktop.verify-email');
    }

    public function showOAuthCallback(): View
    {
        return view('auth.desktop.oauth-callback');
    }

    // ── Registration ───────────────────────────────────────────────────────

    public function register(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $supabaseResult = $this->supabase->signUp(
                $validated['email'],
                $validated['password'],
                ['data' => ['name' => $validated['name']]],
            );

            $user = User::create([
                'supabase_id' => $supabaseResult['user']['id'],
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'email_verified_at' => config('supabase.auth.auto_confirm') ? now() : null,
            ]);

            $session = $this->sessionService->createSession(
                $user,
                $supabaseResult['access_token'] ?? '',
                $supabaseResult['refresh_token'] ?? '',
                $request->boolean('remember'),
            );

            $this->offlineCache->cacheCredentials(
                $user,
                $supabaseResult['access_token'] ?? '',
                $supabaseResult['refresh_token'] ?? '',
            );

            Log::info('desktop.auth.registered', ['user_id' => $user->id, 'email' => $user->email]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Registration successful.',
                    'user' => $user->toArray(),
                    'session' => $session,
                ], 201);
            }

            return redirect()->intended('/console')->with('status', 'Registration successful.');

        } catch (\Throwable $e) {
            Log::error('desktop.auth.registration_failed', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Registration failed: '.$e->getMessage(),
                ], 500);
            }

            return back()->withErrors(['email' => 'Registration failed: '.$e->getMessage()]);
        }
    }

    // ── Login ──────────────────────────────────────────────────────────────

    public function login(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $remember = $request->boolean('remember');

        try {
            $supabaseResult = $this->supabase->signIn(
                $validated['email'],
                $validated['password'],
            );

            $supabaseUser = $supabaseResult['user'];
            $user = User::where('supabase_id', $supabaseUser['id'])->first();

            if (! $user) {
                $user = User::where('email', $validated['email'])->first();

                if ($user) {
                    $user->update(['supabase_id' => $supabaseUser['id']]);
                } else {
                    $user = User::create([
                        'supabase_id' => $supabaseUser['id'],
                        'name' => $supabaseUser['user_metadata']['name'] ?? explode('@', $validated['email'])[0],
                        'email' => $validated['email'],
                        'password' => bcrypt(Str::random(32)),
                        'email_verified_at' => $supabaseUser['email_confirmed_at'] ? now() : null,
                    ]);
                }
            }

            $session = $this->sessionService->createSession(
                $user,
                $supabaseResult['access_token'],
                $supabaseResult['refresh_token'],
                $remember,
            );

            $this->offlineCache->cacheCredentials(
                $user,
                $supabaseResult['access_token'],
                $supabaseResult['refresh_token'],
                ['plan' => $user->plan],
            );

            Log::info('desktop.auth.logged_in', ['user_id' => $user->id, 'remember' => $remember]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Login successful.',
                    'user' => $user->toArray(),
                    'session' => $session,
                ]);
            }

            return redirect()->intended('/console')->with('status', 'Login successful.');

        } catch (\Throwable $e) {
            Log::warning('desktop.auth.login_failed', ['email' => $validated['email']]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid email or password.'], 401);
            }

            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput($request->only('email', 'remember'));
        }
    }

    // ── Offline Login ──────────────────────────────────────────────────────

    public function offlineLogin(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $this->offlineCache->authenticateOffline(
            $validated['email'],
            $validated['password'],
        );

        if (! $user) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Invalid credentials or no offline session.'], 401);
            }

            return back()->withErrors(['email' => 'Invalid credentials or no offline session.']);
        }

        if (! $this->offlineCache->hasCachedSession($user->id)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'No cached session found. Connect to internet first.'], 401);
            }

            return back()->withErrors(['email' => 'No cached session. Connect to the internet first.']);
        }

        $token = $this->authService->createToken($user, 'offline');

        Log::info('desktop.auth.offline_login', ['user_id' => $user->id]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Offline login successful.',
                'user' => $user->toArray(),
                'token' => $token,
                'offline' => true,
            ]);
        }

        return redirect('/console')->with('status', 'Offline mode. Some features may be limited.');
    }

    // ── Logout ──────────────────────────────────────────────────────────────

    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            try {
                $session = $this->sessionService->getCurrentSession($user);

                if ($session && ! empty($session['access_token'])) {
                    $this->supabase->signOut($session['access_token']);
                }
            } catch (\Throwable $e) {
                Log::warning('desktop.auth.supabase_logout_failed', ['error' => $e->getMessage()]);
            }

            $this->sessionService->destroySession($user);
            $this->offlineCache->removeCachedSession($user);
            $this->authService->revokeAllTokens($user);
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Logged out successfully.']);
        }

        return redirect('/login')->with('status', 'Logged out successfully.');
    }

    // ── Google OAuth ────────────────────────────────────────────────────────

    public function redirectToGoogle(): RedirectResponse
    {
        $redirectUrl = route('auth.desktop.oauth.callback');
        $url = $this->supabase->signInWithProvider('google', $redirectUrl);

        return redirect($url);
    }

    public function handleGoogleCallback(Request $request): JsonResponse|RedirectResponse
    {
        $code = $request->input('code');

        if (! $code) {
            $error = $request->input('error_description', 'OAuth authentication failed.');

            return redirect('/login')->withErrors(['oauth' => $error]);
        }

        try {
            $redirectUrl = route('auth.desktop.oauth.callback');
            $session = $this->supabase->exchangeCode($code, $redirectUrl);

            $user = $this->supabase->verifySupabaseToken($session['access_token']);

            if (! $user) {
                return redirect('/login')->withErrors(['oauth' => 'Failed to resolve user.']);
            }

            $this->sessionService->createSession(
                $user,
                $session['access_token'],
                $session['refresh_token'],
                true,
            );

            $this->offlineCache->cacheCredentials(
                $user,
                $session['access_token'],
                $session['refresh_token'],
            );

            Log::info('desktop.auth.oauth_completed', [
                'user_id' => $user->id,
                'provider' => 'google',
            ]);

            return redirect()->intended('/console');

        } catch (\Throwable $e) {
            Log::error('desktop.auth.oauth_callback_failed', ['error' => $e->getMessage()]);

            return redirect('/login')->withErrors(['oauth' => 'OAuth login failed.']);
        }
    }

    // ── Password Reset ─────────────────────────────────────────────────────

    public function sendResetLink(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        try {
            $this->supabase->sendPasswordReset($validated['email']);

            Log::info('desktop.auth.password_reset_requested', ['email' => $validated['email']]);

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Password reset link sent to your email.',
                ]);
            }

            return back()->with('status', 'Password reset link sent to your email.');

        } catch (\Throwable $e) {
            Log::error('desktop.auth.password_reset_failed', [
                'email' => $validated['email'],
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Failed to send reset link.'], 500);
            }

            return back()->withErrors(['email' => 'Failed to send reset link.']);
        }
    }

    public function updatePassword(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $response = Http::withHeaders([
                'apikey' => config('supabase.key'),
                'Authorization' => 'Bearer '.config('supabase.key'),
                'Content-Type' => 'application/json',
            ])->post(rtrim(config('supabase.url'), '/').'/auth/v1/verify', [
                'type' => 'recovery',
                'token' => $validated['token'],
                'password' => $validated['password'],
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('Password update failed: '.$response->body());
            }

            Log::info('desktop.auth.password_updated', ['token' => substr($validated['token'], 0, 8).'...']);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Password updated successfully.']);
            }

            return redirect('/login')->with('status', 'Password updated. Please log in.');

        } catch (\Throwable $e) {
            Log::error('desktop.auth.password_update_failed', ['error' => $e->getMessage()]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Failed to update password.'], 500);
            }

            return back()->withErrors(['password' => 'Failed to update password.']);
        }
    }

    // ── Email Verification ─────────────────────────────────────────────────

    public function sendVerificationEmail(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return $this->unauthenticated();
        }

        if ($user->isVerified()) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Email already verified.']);
            }

            return back()->with('status', 'Email already verified.');
        }

        try {
            Http::withHeaders([
                'apikey' => config('supabase.key'),
                'Authorization' => 'Bearer '.config('supabase.key'),
                'Content-Type' => 'application/json',
            ])->post(rtrim(config('supabase.url'), '/').'/auth/v1/verify', [
                'type' => 'signup',
                'email' => $user->email,
            ]);

            Log::info('desktop.auth.verification_sent', ['user_id' => $user->id]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Verification email sent.']);
            }

            return back()->with('status', 'Verification email sent.');

        } catch (\Throwable $e) {
            Log::error('desktop.auth.verification_failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Failed to send verification email.'], 500);
            }

            return back()->withErrors(['email' => 'Failed to send verification email.']);
        }
    }

    public function verifyEmail(Request $request): JsonResponse|RedirectResponse
    {
        $token = $request->input('token');

        if (! $token) {
            return redirect('/login')->withErrors(['verification' => 'Invalid verification token.']);
        }

        try {
            $response = Http::withHeaders([
                'apikey' => config('supabase.key'),
                'Authorization' => 'Bearer '.config('supabase.key'),
                'Content-Type' => 'application/json',
            ])->post(rtrim(config('supabase.url'), '/').'/auth/v1/verify', [
                'type' => 'signup',
                'token' => $token,
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('Verification failed: '.$response->body());
            }

            $email = $response->json()['email'] ?? null;

            if ($email) {
                User::where('email', $email)->update(['email_verified_at' => now()]);
            }

            Log::info('desktop.auth.email_verified', ['email' => $email]);

            return redirect('/login')->with('status', 'Email verified successfully. You can now log in.');

        } catch (\Throwable $e) {
            Log::error('desktop.auth.verification_failed', ['error' => $e->getMessage()]);

            return redirect('/login')->withErrors(['verification' => 'Email verification failed.']);
        }
    }

    // ── Session Management ─────────────────────────────────────────────────

    public function checkSession(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'authenticated' => false,
                'offline' => $this->offlineCache->isOfflineMode(),
            ]);
        }

        $activeSession = $this->sessionService->getCurrentSession($user);
        $offlineStatus = $this->offlineCache->getOfflineStatus($user);

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url,
                'plan' => $user->plan,
                'verified' => $user->isVerified(),
            ],
            'session' => $activeSession ? [
                'created_at' => $activeSession['created_at'],
                'last_activity' => $activeSession['last_activity'],
                'remember' => $activeSession['remember'],
            ] : null,
            'offline' => $offlineStatus,
        ]);
    }

    // ── Switch Account / User Info ─────────────────────────────────────────

    public function switchAccount(Request $request): JsonResponse|RedirectResponse
    {
        $userId = $request->input('user_id');
        $password = $request->input('password');

        if (! $userId || ! $password) {
            return response()->json(['message' => 'User ID and password required.'], 400);
        }

        $targetUser = User::find($userId);

        if (! $targetUser || ! Hash::check($password, $targetUser->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $this->authService->revokeAllTokens($request->user());
        $this->authService->createToken($targetUser);

        Log::info('desktop.auth.account_switched', [
            'from' => $request->user()?->id,
            'to' => $targetUser->id,
        ]);

        return response()->json(['message' => 'Account switched.', 'user' => $targetUser->toArray()]);
    }

    // ── Auth Status ────────────────────────────────────────────────────────

    public function status(): JsonResponse
    {
        return response()->json([
            'supabase_configured' => config('supabase.url') && config('supabase.key'),
            'auth_providers' => [
                'email' => true,
                'google' => config('supabase.auth.providers.google.enabled', false),
            ],
            'offline_enabled' => true,
            'session_driver' => config('session.driver', 'file'),
        ]);
    }
}
