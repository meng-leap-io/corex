<?php

namespace Tests\Fixtures;

class TestUserData
{
    public static function validRegistration(): array
    {
        return [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];
    }

    public static function validLogin(): array
    {
        return [
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
        ];
    }

    public static function validProfile(): array
    {
        return [
            'bio' => 'Full-stack developer',
            'company' => 'Acme Corp',
            'website' => 'https://johndoe.dev',
            'twitter' => '@johndoe',
            'github' => 'johndoe',
        ];
    }

    public static function invalidEmails(): array
    {
        return [
            'not-an-email',
            '',
            'user@',
            '@domain.com',
            'user@.com',
            'user@domain.',
        ];
    }

    public static function weakPasswords(): array
    {
        return [
            'short',
            '12345678',
            'password',
            'aaaaaaaa',
            '',
        ];
    }

    public static function validProject(): array
    {
        return [
            'name' => 'My API Project',
            'description' => 'A REST API built with Laravel',
            'language' => 'PHP',
            'framework' => 'Laravel 11',
        ];
    }

    public static function plansWithLimits(): array
    {
        return [
            'free' => ['limit' => 1000, 'price' => 0],
            'pro' => ['limit' => 10000, 'price' => 29],
            'team' => ['limit' => 50000, 'price' => 99],
        ];
    }

    public static function modelPricing(): array
    {
        return [
            'gpt-4o' => ['input' => 0.00001, 'output' => 0.00003],
            'gpt-4o-mini' => ['input' => 0.000005, 'output' => 0.000015],
            'claude-3-opus' => ['input' => 0.000015, 'output' => 0.000075],
            'claude-3-haiku' => ['input' => 0.000005, 'output' => 0.00001],
            'gpt-3.5-turbo' => ['input' => 0.000002, 'output' => 0.000002],
        ];
    }
}
