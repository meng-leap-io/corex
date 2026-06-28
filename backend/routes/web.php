<?php

use Illuminate\Support\Facades\Route;

// Load desktop authentication routes
require __DIR__ . '/auth.php';

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
    Route::get('/files', fn () => view('console.files'))->name('console.files');
    Route::get('/analytics', fn () => view('console.analytics'))->name('console.analytics');
});

// Desktop-specific console entry point (frameless, offline-aware)
Route::middleware('nativephp')->group(function () {
    Route::get('/desktop', fn () => view('desktop.console'))->name('desktop.console');

    // Desktop file operations
    Route::prefix('_native/files')->group(function () {
        Route::post('/open', [\App\Http\Controllers\Desktop\FileController::class, 'openLocalFile']);
        Route::post('/save', [\App\Http\Controllers\Desktop\FileController::class, 'saveLocalFile']);
        Route::post('/upload', [\App\Http\Controllers\Desktop\FileController::class, 'uploadToSupabase']);
        Route::get('/download/{file}', [\App\Http\Controllers\Desktop\FileController::class, 'downloadFromSupabase']);
        Route::post('/sync', [\App\Http\Controllers\Desktop\FileController::class, 'syncLocalToRemote']);
        Route::get('/tree', [\App\Http\Controllers\Desktop\FileController::class, 'listLocalDirectory']);
    });
});
