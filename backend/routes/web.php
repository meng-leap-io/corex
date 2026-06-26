<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Corex.dev Web Routes
|--------------------------------------------------------------------------
| Routes for the landing page and SSR if applicable.
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return response()->json([
        'name' => 'Corex.dev',
        'description' => 'AI-powered development platform',
        'domains' => [
            'landing' => 'https://corex.dev',
            'console' => 'https://console.corex.dev',
        ]
    ]);
});

Route::get('/health', function () {
    return response()->json(['status' => 'ok', 'service' => 'laravel-web']);
});
