<?php

return [
    'allowed_origins' => env('CORS_ALLOWED_ORIGINS', 'https://corex.dev,https://console.corex.dev'),
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Request-ID'],
    'supports_credentials' => true,
    'max_age' => 86400,
];
