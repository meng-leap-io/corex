<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'corex.dev,console.corex.dev,localhost,localhost:3000,localhost:8000')),
    'guard' => ['web'],
    'expiration' => 60 * 24 * 7,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'corex_'),
    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],
];
