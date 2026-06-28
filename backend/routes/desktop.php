<?php

use App\Http\Controllers\Desktop\AiModelController;
use App\Http\Controllers\Desktop\DeepLinkController;
use App\Http\Controllers\Desktop\DialogController;
use App\Http\Controllers\Desktop\FileController;
use App\Http\Controllers\Desktop\NotificationController;
use App\Http\Controllers\Desktop\ProjectController;
use App\Http\Controllers\Desktop\ShortcutController;
use App\Http\Controllers\Desktop\SystemController;
use App\Http\Controllers\Desktop\TaskSchedulerController;
use App\Http\Controllers\Desktop\UpdateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['nativephp', 'auth:sanctum'])->prefix('_native')->group(function () {

    // ── File System ───────────────────────────────────────────────────
    Route::prefix('files')->group(function () {
        Route::get('/list', [FileController::class, 'listDirectory']);
        Route::get('/read', [FileController::class, 'read']);
        Route::post('/write', [FileController::class, 'write']);
        Route::post('/rename', [FileController::class, 'rename']);
        Route::post('/delete', [FileController::class, 'delete']);
        Route::post('/create', [FileController::class, 'create']);
        Route::post('/duplicate', [FileController::class, 'duplicate']);
        Route::get('/search', [FileController::class, 'search']);
        Route::get('/info', [FileController::class, 'info']);
        Route::get('/tree', [FileController::class, 'tree']);
        Route::post('/move', [FileController::class, 'move']);
        Route::post('/upload', [FileController::class, 'upload']);
    });

    // ── Projects ──────────────────────────────────────────────────────
    Route::prefix('projects')->group(function () {
        Route::get('/recent', [ProjectController::class, 'recent']);
        Route::post('/open', [ProjectController::class, 'open']);
        Route::post('/create', [ProjectController::class, 'create']);
        Route::post('/close', [ProjectController::class, 'close']);
        Route::get('/current', [ProjectController::class, 'current']);
        Route::get('/settings', [ProjectController::class, 'settings']);
        Route::post('/settings', [ProjectController::class, 'updateSettings']);
    });

    // ── Native Dialogs ────────────────────────────────────────────────
    Route::post('/dialog/open-file', [DialogController::class, 'openFile']);
    Route::post('/dialog/save-file', [DialogController::class, 'saveFile']);
    Route::post('/dialog/open-folder', [DialogController::class, 'openFolder']);
    Route::post('/dialog/message', [DialogController::class, 'message']);

    // ── Notifications ─────────────────────────────────────────────────
    Route::post('/notifications/send', [NotificationController::class, 'send']);
    Route::post('/notifications/clear', [NotificationController::class, 'clear']);

    // ── AI Model Management ───────────────────────────────────────────
    Route::prefix('ai-models')->group(function () {
        Route::get('/', [AiModelController::class, 'index']);
        Route::post('/pull', [AiModelController::class, 'pull']);
        Route::post('/remove', [AiModelController::class, 'remove']);
        Route::get('/status', [AiModelController::class, 'status']);
    });

    // ── Updates ───────────────────────────────────────────────────────
    Route::get('/updates/check', [UpdateController::class, 'check']);
    Route::post('/updates/download', [UpdateController::class, 'download']);
    Route::post('/updates/install', [UpdateController::class, 'install']);

    // ── App State ─────────────────────────────────────────────────────
    Route::get('/state', function () {
        return response()->json([
            'version' => config('nativephp.version', '1.0.0'),
            'platform' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'uptime' => sys_getloadavg()[0],
            'memory' => [
                'usage' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit'),
            ],
            'offline' => ! cache()->get('_native_online', true),
        ]);
    });

    // ── Window Management ─────────────────────────────────────────────
    Route::post('/window/minimize', fn () => native()->window()->minimize());
    Route::post('/window/maximize', fn () => native()->window()->maximize());
    Route::post('/window/restore', fn () => native()->window()->restore());
    Route::post('/window/close', fn () => native()->window()->close());
    Route::post('/window/set-size', fn () => native()->window()->setSize(request('width'), request('height')));
    Route::get('/window/size', fn () => response()->json(native()->window()->getSize()));
    Route::post('/window/set-position', fn () => native()->window()->setPosition(request('x'), request('y')));
    Route::post('/window/set-title', fn () => native()->window()->setTitle(request('title')));
    Route::post('/window/set-fullscreen', fn () => native()->window()->setFullScreen(request('fullscreen')));
    Route::post('/window/set-always-on-top', fn () => native()->window()->setAlwaysOnTop(request('alwaysOnTop')));
    Route::get('/window/is-maximized', fn () => response()->json(['maximized' => native()->window()->isMaximized()]));
    Route::get('/window/is-fullscreen', fn () => response()->json(['fullscreen' => native()->window()->isFullScreen()]));

    // ── System Tray ───────────────────────────────────────────────────
    Route::post('/tray/set-tooltip', fn () => native()->tray()->setTooltip(request('tooltip')));
    Route::post('/tray/update-menu', fn () => native()->tray()->updateMenu(request('menu')));
    Route::post('/tray/remove', fn () => native()->tray()->remove());
    Route::post('/tray/set-icon', fn () => native()->tray()->setIcon(request('icon')));

    // ── Menu Bar ──────────────────────────────────────────────────────
    Route::post('/menu/set-application-menu', fn () => native()->menu()->setApplicationMenu(request('menu')));
    Route::post('/menu/popup', fn () => native()->menu()->popup(request('template', [])));

    // ── System ────────────────────────────────────────────────────────
    Route::prefix('system')->group(function () {
        Route::get('/info', [SystemController::class, 'info']);
        Route::get('/theme', [SystemController::class, 'theme']);
        Route::get('/theme-css', [SystemController::class, 'themeCss']);
        Route::get('/performance', [SystemController::class, 'performance']);
        Route::get('/system-info', [SystemController::class, 'systemInfo']);
        Route::get('/services', [SystemController::class, 'services']);
        Route::get('/proxy', [SystemController::class, 'proxy']);
        Route::post('/proxy/configure', [SystemController::class, 'proxyConfigure']);
        Route::post('/proxy/disable', [SystemController::class, 'proxyDisable']);
        Route::post('/notify', [SystemController::class, 'notify']);
        Route::post('/notify/clear', [SystemController::class, 'notifyClear']);
        Route::get('/event-log', [SystemController::class, 'eventLog']);
        Route::post('/event-log/clear', [SystemController::class, 'eventLogClear']);
        Route::post('/shortcuts/create', [SystemController::class, 'shortcuts']);
        Route::post('/powershell', [SystemController::class, 'powershell']);
        Route::post('/shell', [SystemController::class, 'shell']);
        Route::get('/desktop', [SystemController::class, 'desktop']);
    });

    // ── Scheduled Tasks ───────────────────────────────────────────────
    Route::prefix('tasks')->group(function () {
        Route::get('/', [TaskSchedulerController::class, 'list']);
        Route::post('/create', [TaskSchedulerController::class, 'create']);
        Route::get('/{taskName}', [TaskSchedulerController::class, 'show']);
        Route::get('/{taskName}/status', [TaskSchedulerController::class, 'status']);
        Route::post('/{taskName}/start', [TaskSchedulerController::class, 'start']);
        Route::post('/{taskName}/stop', [TaskSchedulerController::class, 'stop']);
        Route::post('/{taskName}/enable', [TaskSchedulerController::class, 'enable']);
        Route::post('/{taskName}/disable', [TaskSchedulerController::class, 'disable']);
        Route::delete('/{taskName}', [TaskSchedulerController::class, 'delete']);
        Route::post('/schedule/backup', [TaskSchedulerController::class, 'scheduleBackup']);
        Route::post('/schedule/health-check', [TaskSchedulerController::class, 'scheduleHealthCheck']);
        Route::post('/clear-all', [TaskSchedulerController::class, 'clearAll']);
    });

    // ── Shortcuts / File Associations / Jump List ─────────────────────
    Route::prefix('shortcuts')->group(function () {
        Route::post('/create', [ShortcutController::class, 'create']);
        Route::post('/register-protocol', [ShortcutController::class, 'registerProtocol']);
        Route::post('/unregister-protocol', [ShortcutController::class, 'unregisterProtocol']);
        Route::post('/register-association', [ShortcutController::class, 'registerAssociation']);
        Route::post('/unregister-association', [ShortcutController::class, 'unregisterAssociation']);
        Route::post('/register-context-menu', [ShortcutController::class, 'registerContextMenu']);
        Route::post('/register-explorer-menu', [ShortcutController::class, 'registerExplorerMenu']);
        Route::post('/unregister-explorer-menu', [ShortcutController::class, 'unregisterExplorerMenu']);
        Route::post('/send-to', [ShortcutController::class, 'sendTo']);
        Route::get('/associations', [ShortcutController::class, 'associations']);
        Route::post('/refresh-explorer', [ShortcutController::class, 'refreshExplorer']);
    });

    // ── Jump List ─────────────────────────────────────────────────────
    Route::prefix('jump-list')->group(function () {
        Route::post('/add-recent', [ShortcutController::class, 'jumpListAddRecent']);
        Route::post('/add-task', [ShortcutController::class, 'jumpListAddTask']);
        Route::post('/clear', [ShortcutController::class, 'jumpListClear']);
        Route::get('/recent', [ShortcutController::class, 'jumpListRecent']);
    });
});

// ── WebSocket / IPC bridge for real-time events ──────────────────────────
Route::middleware('nativephp')->post('/_native/ipc/send', function () {
    $channel = request('channel');
    $event = request('event');
    $data = request('data');
    native()->ipc()->send($channel, $event, $data);

    return response()->json(['sent' => true]);
});

// ── Deep links ───────────────────────────────────────────────────────────
Route::middleware('nativephp')->get('/_native/deeplink/{action}', [DeepLinkController::class, 'handle']);
