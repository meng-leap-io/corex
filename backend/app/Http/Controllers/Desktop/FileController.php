<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use App\Models\File;
use App\Services\Supabase\Storage\FileManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function __construct(
        private readonly FileManagementService $fileManagement,
    ) {}

    public function openLocalFile(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->input('path');

        if (!file_exists($path)) {
            return new JsonResponse(['error' => 'File not found.'], 404);
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return new JsonResponse(['error' => 'Could not read file.'], 500);
        }

        return new JsonResponse([
            'name' => basename($path),
            'path' => $path,
            'content' => $content,
            'language' => $this->detectLanguage($path),
            'size' => filesize($path),
            'modified_at' => date('c', filemtime($path)),
        ]);
    }

    public function saveLocalFile(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string',
            'content' => 'required|string',
        ]);

        $path = $request->input('path');
        $content = $request->input('content');

        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $written = file_put_contents($path, $content);

        if ($written === false) {
            return new JsonResponse(['error' => 'Could not write file.'], 500);
        }

        return new JsonResponse([
            'message' => 'File saved.',
            'path' => $path,
            'size' => $written,
        ]);
    }

    public function uploadToSupabase(Request $request): JsonResponse
    {
        $request->validate([
            'local_path' => 'required|string',
            'bucket' => 'nullable|string|in:projects,avatars,documents,exports',
            'directory' => 'nullable|string',
        ]);

        $localPath = $request->input('local_path');
        $bucket = $request->input('bucket', 'projects');
        $directory = $request->input('directory');

        if (!file_exists($localPath)) {
            return new JsonResponse(['error' => 'Local file not found.'], 404);
        }

        try {
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $localPath,
                basename($localPath),
                mime_content_type($localPath),
                null,
                true,
            );

            $fileModel = $this->fileManagement->upload(
                $request->user(),
                $uploadedFile,
                $bucket,
                $directory,
                ['source' => 'desktop', 'optimize' => false],
            );

            return new JsonResponse([
                'data' => $fileModel,
                'message' => 'File uploaded to Supabase.',
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function downloadFromSupabase(File $file): JsonResponse
    {
        if ($file->user_id !== auth()->id()) {
            return new JsonResponse(['error' => 'Forbidden.'], 403);
        }

        $localPath = $this->fileManagement->download($file);

        if ($localPath === null) {
            return new JsonResponse(['error' => 'File not found in storage.'], 404);
        }

        return new JsonResponse([
            'local_path' => $localPath,
            'name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size' => filesize($localPath),
        ]);
    }

    public function syncLocalToRemote(Request $request): JsonResponse
    {
        $request->validate([
            'local_path' => 'required|string',
            'file_id' => 'nullable|string|exists:files,id',
        ]);

        $localPath = $request->input('local_path');
        $fileId = $request->input('file_id');

        if (!file_exists($localPath)) {
            return new JsonResponse(['error' => 'Local file not found.'], 404);
        }

        try {
            $content = file_get_contents($localPath);

            if ($fileId) {
                $file = File::findOrFail($fileId);

                if ($file->user_id !== auth()->id()) {
                    return new JsonResponse(['error' => 'Forbidden.'], 403);
                }

                $url = app(\App\Contracts\SupabaseStorageContract::class)
                    ->upload($file->bucket, $file->path, $content, ['upsert' => true]);

                $file->update(['url' => $url, 'size' => strlen($content)]);

                return new JsonResponse(['data' => $file, 'message' => 'File synced.']);
            }

            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $localPath,
                basename($localPath),
                mime_content_type($localPath),
                null,
                true,
            );

            $fileModel = $this->fileManagement->upload(
                $request->user(),
                $uploadedFile,
                'projects',
                'desktop-sync',
                ['source' => 'desktop-sync', 'optimize' => false],
            );

            return new JsonResponse(['data' => $fileModel, 'message' => 'File synced.']);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    public function listLocalDirectory(Request $request): JsonResponse
    {
        $request->validate([
            'path' => 'required|string',
            'depth' => 'nullable|integer|min:0|max:10',
        ]);

        $path = $request->input('path');
        $depth = $request->input('depth', 2);

        if (!is_dir($path)) {
            return new JsonResponse(['error' => 'Directory not found.'], 404);
        }

        return new JsonResponse([
            'data' => $this->scanDirectory($path, 0, $depth),
        ]);
    }

    private function scanDirectory(string $path, int $currentDepth, int $maxDepth): array
    {
        $items = [];

        $entries = scandir($path);

        if ($entries === false) {
            return $items;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($fullPath)) {
                $item = [
                    'name' => $entry,
                    'path' => $fullPath,
                    'type' => 'folder',
                ];

                if ($currentDepth < $maxDepth) {
                    $item['children'] = $this->scanDirectory($fullPath, $currentDepth + 1, $maxDepth);
                }

                $items[] = $item;
            } elseif (is_file($fullPath)) {
                $items[] = [
                    'name' => $entry,
                    'path' => $fullPath,
                    'type' => 'file',
                    'size' => filesize($fullPath),
                    'extension' => pathinfo($entry, PATHINFO_EXTENSION),
                    'modified_at' => date('c', filemtime($fullPath)),
                ];
            }
        }

        usort($items, fn ($a, $b) => $a['name'] <=> $b['name']);

        return $items;
    }

    private function detectLanguage(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $map = [
            'php' => 'php', 'js' => 'javascript', 'ts' => 'typescript',
            'vue' => 'html', 'json' => 'json', 'md' => 'markdown',
            'css' => 'css', 'py' => 'python', 'rs' => 'rust',
            'go' => 'go', 'yaml' => 'yaml', 'yml' => 'yaml',
            'xml' => 'xml', 'sql' => 'sql', 'sh' => 'shell',
            'bat' => 'bat', 'blade.php' => 'php',
        ];

        return $map[$ext] ?? 'plaintext';
    }
}
