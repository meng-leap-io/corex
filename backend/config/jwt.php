<?php

return [
    'secret' => env('JWT_SECRET'),
    'algorithm' => env('JWT_ALGORITHM', 'HS256'),
    'public_key' => env('JWT_PUBLIC_KEY'),
    'private_key' => env('JWT_PRIVATE_KEY'),
    'ttl' => env('JWT_TTL', 60 * 24 * 7),
    'refresh_ttl' => env('JWT_REFRESH_TTL', 60 * 24 * 14),
];
