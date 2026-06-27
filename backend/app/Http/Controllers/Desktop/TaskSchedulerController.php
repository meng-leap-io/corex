<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use App\Services\Windows\TaskSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskSchedulerController extends Controller
{
    public function list(): JsonResponse
    {
        return response()->json([
            'tasks' => TaskSchedulerService::listTasks('Corex'),
        ]);
    }

    public function show(string $taskName): JsonResponse
    {
        $details = TaskSchedulerService::getTaskDetails($taskName);
        if (!$details) {
            return response()->json(['error' => 'Task not found'], 404);
        }
        return response()->json(['task' => $details]);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'script' => 'required|string|max:2000',
            'trigger' => 'required|in:daily,hourly,onstart,onlogon,minute',
            'time' => 'nullable|string|max:10',
            'interval_minutes' => 'nullable|integer|min:1|max:1440',
            'description' => 'nullable|string|max:1000',
            'working_dir' => 'nullable|string|max:1000',
            'user' => 'nullable|string|max:255',
        ]);

        $created = TaskSchedulerService::createTask(
            $validated['name'],
            $validated['script'],
            $validated['trigger'],
            $validated['time'] ?? '09:00',
            [
                'description' => $validated['description'] ?? "Corex: {$validated['name']}",
                'working_dir' => $validated['working_dir'] ?? base_path(),
                'user' => $validated['user'] ?? 'SYSTEM',
                'interval_minutes' => $validated['interval_minutes'] ?? null,
            ]
        );

        return response()->json(['created' => $created]);
    }

    public function status(string $taskName): JsonResponse
    {
        return response()->json([
            'status' => TaskSchedulerService::getTaskStatus($taskName),
        ]);
    }

    public function start(string $taskName): JsonResponse
    {
        return response()->json(['started' => TaskSchedulerService::startTask($taskName)]);
    }

    public function stop(string $taskName): JsonResponse
    {
        return response()->json(['stopped' => TaskSchedulerService::stopTask($taskName)]);
    }

    public function enable(string $taskName): JsonResponse
    {
        return response()->json(['enabled' => TaskSchedulerService::enableTask($taskName)]);
    }

    public function disable(string $taskName): JsonResponse
    {
        return response()->json(['disabled' => TaskSchedulerService::disableTask($taskName)]);
    }

    public function delete(string $taskName): JsonResponse
    {
        return response()->json(['deleted' => TaskSchedulerService::deleteTask($taskName)]);
    }

    public function scheduleBackup(Request $request): JsonResponse
    {
        $hour = (int) $request->input('hour', 2);
        $minute = (int) $request->input('minute', 0);
        return response()->json(['scheduled' => TaskSchedulerService::scheduleBackup($hour, $minute)]);
    }

    public function scheduleHealthCheck(Request $request): JsonResponse
    {
        $interval = (int) $request->input('interval_minutes', 5);
        return response()->json(['scheduled' => TaskSchedulerService::scheduleHealthCheck($interval)]);
    }

    public function clearAll(): JsonResponse
    {
        TaskSchedulerService::clearAllCorexTasks();
        return response()->json(['cleared' => true]);
    }
}
