<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function recent(): JsonResponse
    {
        $projects = cache()->get('_native_recent_projects', []);

        return response()->json([
            'projects' => array_map(function ($project) {
                $project['exists'] = is_dir($project['path']);

                return $project;
            }, $projects),
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $path = $request->input('path');

        if (! $path || ! is_dir($path)) {
            return response()->json(['error' => 'Invalid project path'], 400);
        }

        $project = [
            'path' => $path,
            'name' => basename($path),
            'opened_at' => now()->toIso8601String(),
        ];

        $this->addToRecent($project);

        return response()->json($project);
    }

    public function create(Request $request): JsonResponse
    {
        $parentPath = $request->input('parent', $this->defaultPath());
        $name = $request->input('name');

        if (! $name) {
            return response()->json(['error' => 'Project name is required'], 422);
        }

        $projectPath = $parentPath.DIRECTORY_SEPARATOR.$name;

        if (is_dir($projectPath)) {
            return response()->json(['error' => 'Directory already exists'], 409);
        }

        mkdir($projectPath, 0755, true);

        $this->scaffoldProject($projectPath);

        $project = [
            'path' => $projectPath,
            'name' => $name,
            'opened_at' => now()->toIso8601String(),
        ];

        $this->addToRecent($project);

        return response()->json($project, 201);
    }

    public function close(): JsonResponse
    {
        return response()->json(['closed' => true]);
    }

    public function current(): JsonResponse
    {
        $current = cache()->get('_native_current_project');
        if (! $current || ! is_dir($current['path'])) {
            return response()->json(['project' => null]);
        }

        return response()->json(['project' => $current]);
    }

    public function settings(): JsonResponse
    {
        $path = request('path', cache()->get('_native_current_project.path'));
        $settingsPath = $path.DIRECTORY_SEPARATOR.'.corex'.DIRECTORY_SEPARATOR.'settings.json';

        if (! file_exists($settingsPath)) {
            return response()->json(['settings' => $this->defaultSettings()]);
        }

        return response()->json([
            'settings' => array_merge(
                $this->defaultSettings(),
                json_decode(file_get_contents($settingsPath), true) ?? []
            ),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $path = $request->input('path', cache()->get('_native_current_project.path'));
        $settingsDir = $path.DIRECTORY_SEPARATOR.'.corex';
        $settingsPath = $settingsDir.DIRECTORY_SEPARATOR.'settings.json';

        if (! is_dir($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }

        $existing = [];
        if (file_exists($settingsPath)) {
            $existing = json_decode(file_get_contents($settingsPath), true) ?? [];
        }

        $merged = array_merge($existing, $request->input('settings', []));
        file_put_contents($settingsPath, json_encode($merged, JSON_PRETTY_PRINT));

        return response()->json(['settings' => $merged]);
    }

    private function addToRecent(array $project): void
    {
        $recent = cache()->get('_native_recent_projects', []);
        $recent = array_filter($recent, fn ($p) => $p['path'] !== $project['path']);
        array_unshift($recent, $project);
        cache()->forever('_native_recent_projects', array_slice($recent, 0, 20));
        cache()->forever('_native_current_project', $project);
    }

    private function scaffoldProject(string $path): void
    {
        $dirs = ['.corex', 'src', 'public', 'tests'];
        foreach ($dirs as $dir) {
            mkdir($path.DIRECTORY_SEPARATOR.$dir, 0755, true);
        }

        file_put_contents(
            $path.DIRECTORY_SEPARATOR.'README.md',
            '# '.basename($path)."\n\nProject created with Corex.\n"
        );

        file_put_contents(
            $path.DIRECTORY_SEPARATOR.'.corex'.DIRECTORY_SEPARATOR.'settings.json',
            json_encode($this->defaultSettings(), JSON_PRETTY_PRINT)
        );
    }

    private function defaultSettings(): array
    {
        return [
            'tabSize' => 4,
            'fontSize' => 14,
            'theme' => 'dark',
            'wordWrap' => false,
            'lineNumbers' => 'on',
            'minimap' => true,
            'autoSave' => true,
            'autoSaveInterval' => 2000,
        ];
    }

    private function defaultPath(): string
    {
        return env('HOME') ?: env('USERPROFILE') ?: 'C:\\';
    }
}
