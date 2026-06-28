<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;

interface SupabaseAuthContract
{
    public function signUp(string $email, string $password, array $options = []): array;

    public function signIn(string $email, string $password): array;

    public function signInWithProvider(string $provider, string $redirectUrl): string;

    public function signOut(string $accessToken): void;

    public function getUser(string $accessToken): ?array;

    public function exchangeCode(string $code, string $redirectUrl): array;

    public function refreshSession(string $refreshToken): array;

    public function sendPasswordReset(string $email): void;

    public function updatePassword(string $accessToken, string $newPassword): void;

    public function verifySupabaseToken(string $jwt): ?User;
}
