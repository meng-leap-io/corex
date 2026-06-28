<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use App\Services\Windows\ComService;
use App\Services\Windows\EventLogService;
use App\Services\Windows\PerformanceMonitorService;
use App\Services\Windows\ProxySettingsService;
use App\Services\Windows\ThemeService;
use App\Services\Windows\ToastNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function info(): JsonResponse
    {
        return response()->json([
            'os' => PHP_OS_FAMILY,
            'php_version' => PHP_VERSION,
            'hostname' => gethostname(),
            'is_windows' => ComService::isWindows(),
            'app_version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'debug' => config('app.debug'),
        ]);
    }

    public function theme(): JsonResponse
    {
        return response()->json(ThemeService::detect());
    }

    public function themeCss(): JsonResponse
    {
        return response()->json([
            'variables' => ThemeService::cssVariables(),
            'tailwind' => ThemeService::tailwindClasses(),
            'is_dark' => ThemeService::isDarkMode(),
            'dpi_scale' => ThemeService::dpiScale(),
            'font_scale' => ThemeService::fontScale(),
        ]);
    }

    public function performance(): JsonResponse
    {
        return response()->json(PerformanceMonitorService::takeSnapshot());
    }

    public function systemInfo(): JsonResponse
    {
        return response()->json([
            'os' => PerformanceMonitorService::getOsInfo(),
            'hardware' => PerformanceMonitorService::getSystemInfo(),
            'services' => PerformanceMonitorService::getCorexServices(),
            'processes' => PerformanceMonitorService::getProcessList(10),
        ]);
    }

    public function services(): JsonResponse
    {
        return response()->json([
            'services' => PerformanceMonitorService::getCorexServices(),
        ]);
    }

    public function proxy(): JsonResponse
    {
        return response()->json(ProxySettingsService::get());
    }

    public function proxyConfigure(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'server' => 'required|string|max:500',
            'bypass' => 'nullable|string|max:2000',
            'enabled' => 'boolean',
        ]);

        $ok = ProxySettingsService::set(
            $validated['server'],
            $validated['bypass'] ?? '',
            $validated['enabled'] ?? true
        );

        return response()->json(['configured' => $ok]);
    }

    public function proxyDisable(): JsonResponse
    {
        return response()->json(['disabled' => ProxySettingsService::disable()]);
    }

    public function notify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'silent' => 'nullable|boolean',
            'actions' => 'nullable|array',
            'actions.*.label' => 'required|string|max:100',
            'actions.*.arguments' => 'required|string|max:500',
        ]);

        $sent = ! empty($validated['actions'])
            ? ToastNotificationService::sendWithActions(
                $validated['title'],
                $validated['message'],
                $validated['actions']
            )
            : ToastNotificationService::send(
                $validated['title'],
                $validated['message'],
                ['silent' => $validated['silent'] ?? false]
            );

        return response()->json(['sent' => $sent]);
    }

    public function notifyClear(): JsonResponse
    {
        ToastNotificationService::clear();

        return response()->json(['cleared' => true]);
    }

    public function eventLog(Request $request): JsonResponse
    {
        $count = min((int) $request->input('count', 20), 100);

        return response()->json([
            'events' => EventLogService::getRecent($count),
        ]);
    }

    public function eventLogClear(): JsonResponse
    {
        EventLogService::clear();

        return response()->json(['cleared' => true]);
    }

    public function shortcuts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:1000',
            'target' => 'required|string|max:1000',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:1000',
            'working_dir' => 'nullable|string|max:1000',
            'arguments' => 'nullable|string|max:1000',
        ]);

        $created = ComService::createShortcut(
            $validated['path'],
            $validated['target'],
            $validated['description'] ?? '',
            $validated['icon'] ?? '',
            $validated['working_dir'] ?? '',
            $validated['arguments'] ?? ''
        );

        return response()->json(['created' => $created]);
    }

    public function powershell(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'required|string|max:5000',
        ]);
        $output = ComService::powershell($validated['command']);

        return response()->json(['output' => $output]);
    }

    public function shell(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'command' => 'required|string|max:5000',
        ]);
        $output = ComService::shell($validated['command']);

        return response()->json(['output' => $output]);
    }

    public function desktop(): JsonResponse
    {
        return response()->json([
            'theme' => ThemeService::detect(),
            'info' => [
                'os' => PHP_OS_FAMILY,
                'hostname' => gethostname(),
                'php_version' => PHP_VERSION,
            ],
            'performance' => [
                'cpu' => PerformanceMonitorService::getCpuUsage(),
                'memory' => PerformanceMonitorService::getMemoryUsage(),
            ],
            'proxy' => ProxySettingsService::get(),
        ]);
    }
}
