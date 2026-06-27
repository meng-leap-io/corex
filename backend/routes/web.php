<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing.index');
})->name('home');

Route::get('/features', fn () => view('landing.features'))->name('features');
Route::get('/pricing', fn () => view('landing.pricing'))->name('pricing');
Route::get('/about', fn () => view('landing.about'))->name('about');
Route::get('/contact', fn () => view('landing.contact'))->name('contact');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'laravel-web',
        'desktop' => config('nativephp.license') ? true : false,
    ]);
});

Route::prefix('console')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/', fn () => view('console.index'))->name('console');
    Route::get('/chat', fn () => view('console.chat'))->name('console.chat');
    Route::get('/editor', fn () => view('console.editor'))->name('console.editor');
    Route::get('/terminal', fn () => view('console.terminal'))->name('console.terminal');
    Route::get('/settings', fn () => view('console.settings'))->name('console.settings');
});

// Desktop-specific console entry point (frameless, offline-aware)
Route::middleware('nativephp')->group(function () {
    Route::get('/desktop', fn () => view('desktop.console'))->name('desktop.console');
});
