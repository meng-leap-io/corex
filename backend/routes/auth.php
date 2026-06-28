<?php

declare(strict_types=1);

use App\Http\Controllers\Desktop\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Desktop App Authentication Routes
|--------------------------------------------------------------------------
|
| These routes handle Supabase-powered authentication for the Windows
| desktop application. Supports email/password, Google OAuth, password
| reset, email verification, and offline mode.
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    // Login
    Route::get('/login', [AuthController::class, 'showLogin'])->name('auth.desktop.login');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.desktop.login.submit');

    // Offline login
    Route::post('/login/offline', [AuthController::class, 'offlineLogin'])->name('auth.desktop.login.offline');

    // Register
    Route::get('/register', [AuthController::class, 'showRegister'])->name('auth.desktop.register');
    Route::post('/register', [AuthController::class, 'register'])->name('auth.desktop.register.submit');

    // Password reset
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('auth.desktop.forgot-password');
    Route::post('/forgot-password', [AuthController::class, 'sendResetLink'])->name('auth.desktop.password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPassword'])->name('auth.desktop.password.reset');
    Route::post('/reset-password', [AuthController::class, 'updatePassword'])->name('auth.desktop.password.update');

    // Google OAuth
    Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('auth.desktop.oauth.google');
    Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.desktop.oauth.callback');
    Route::post('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('auth.desktop.oauth.callback.submit');

    // Email verification (from email link)
    Route::get('/verify-email', [AuthController::class, 'showVerifyEmail'])->name('auth.desktop.verify-email');
    Route::get('/email/verify/{token}', [AuthController::class, 'verifyEmail'])->name('auth.desktop.verification.verify');
});

Route::middleware('auth')->group(function () {

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.desktop.logout');

    // Email verification (authenticated)
    Route::post('/email/verification-notification', [AuthController::class, 'sendVerificationEmail'])->name('auth.desktop.verification.send');

    // Session management
    Route::get('/auth/check', [AuthController::class, 'checkSession'])->name('auth.desktop.session.check');

    // Account switching
    Route::post('/auth/switch', [AuthController::class, 'switchAccount'])->name('auth.desktop.switch');
});

// Public status endpoint (no auth)
Route::get('/auth/status', [AuthController::class, 'status'])->name('auth.desktop.status');
