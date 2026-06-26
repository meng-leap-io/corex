<?php

return [
    'ai_gateway' => [
        'url' => env('AI_GATEWAY_URL', 'http://ai-gateway:8000'),
        'api_key' => env('AI_GATEWAY_API_KEY'),
    ],

    'supabase' => [
        'url' => env('SUPABASE_URL'),
        'key' => env('SUPABASE_KEY'),
        'jwt_secret' => env('SUPABASE_JWT_SECRET'),
    ],
];
