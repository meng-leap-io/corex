<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UpdateController extends Controller
{
    private const UPDATE_CACHE_KEY = '_native_update_info';
    private const UPDATE_CACHE_TTL = 3600;

    public function check(): JsonResponse
    {
        $cached = cache()->get(self::UPDATE_CACHE_KEY);
        if ($cached) {
            return response()->json($cached);
        }

        $currentVersion = config('nativephp.version', '1.0.0');
        $repo = config('nativephp.auto_update.github_repo');

        $updateInfo = [
            'current_version' => $currentVersion,
            'latest_version' => $currentVersion,
            'update_available' => false,
            'download_url' => null,
            'release_notes' => null,
            'published_at' => null,
        ];

        if ($repo) {
            try {
                $response = \Http::timeout(5)
                    ->withHeaders(['Accept' => 'application/vnd.github.v3+json'])
                    ->get("https://api.github.com/repos/{$repo}/releases/latest");

                if ($response->successful()) {
                    $release = $response->json();
                    $latestVersion = ltrim($release['tag_name'] ?? '', 'v');

                    $updateInfo = [
                        'current_version' => $currentVersion,
                        'latest_version' => $latestVersion,
                        'update_available' => version_compare($latestVersion, $currentVersion, '>'),
                        'download_url' => $release['assets'][0]['browser_download_url'] ?? null,
                        'release_notes' => $release['body'] ?? null,
                        'published_at' => $release['published_at'] ?? null,
                    ];
                }
            } catch (\Throwable) {
                // GitHub API unavailable — return cached or current
            }
        }

        cache()->put(self::UPDATE_CACHE_KEY, $updateInfo, self::UPDATE_CACHE_TTL);

        return response()->json($updateInfo);
    }

    public function download(Request $request): JsonResponse
    {
        $url = $request->input('url');
        if (! $url) {
            return response()->json(['error' => 'Download URL required'], 422);
        }

        try {
            $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'corex-update';
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $filename = basename(parse_url($url, PHP_URL_PATH));
            $destPath = $tempDir . DIRECTORY_SEPARATOR . $filename;

            $response = \Http::timeout(300)->sink($destPath)->get($url);

            if ($response->successful()) {
                return response()->json([
                    'downloaded' => true,
                    'path' => $destPath,
                    'size' => filesize($destPath),
                ]);
            }

            return response()->json(['error' => 'Download failed'], 500);
        } catch (\Throwable $e) {
            return response()->json(['error' => "Download failed: {$e->getMessage()}"], 500);
        }
    }

    public function install(Request $request): JsonResponse
    {
        $path = $request->input('path');
        if (! $path || ! file_exists($path)) {
            return response()->json(['error' => 'Update package not found'], 404);
        }

        try {
            if (PHP_OS_FAMILY === 'Windows') {
                exec("start \"\" \"{$path}\" /SILENT /NORESTART");
            } elseif (PHP_OS_FAMILY === 'Darwin') {
                exec("open \"{$path}\"");
            } else {
                exec("xdg-open \"{$path}\"");
            }

            return response()->json(['installing' => true]);
        } catch (\Throwable $e) {
            return response()->json(['error' => "Failed to start installer: {$e->getMessage()}"], 500);
        }
    }
}
