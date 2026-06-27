<?php

declare(strict_types=1);

namespace App\Http\Controllers\Desktop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Mime\MimeTypes;

class FileController extends Controller
{
    private const DISALLOWED_PATTERNS = [
        'node_modules', 'vendor', '.git', '.svn', '.hg',
        '__pycache__', '.cache', 'dist', 'build',
    ];

    public function listDirectory(Request $request): JsonResponse
    {
        $path = $this->resolvePath($request->input('path'));
        $showHidden = $request->boolean('showHidden', false);

        if (! is_dir($path)) {
            return response()->json(['error' => 'Directory not found'], 404);
        }

        $items = [];
        $handle = opendir($path);
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') continue;
            if (! $showHidden && $entry[0] === '.') continue;
            if (in_array($entry, self::DISALLOWED_PATTERNS)) continue;

            $fullPath = $path . DIRECTORY_SEPARATOR . $entry;
            $items[] = $this->fileInfo($fullPath, $entry);
        }
        closedir($handle);

        $dirs = array_values(array_filter($items, fn($i) => $i['type'] === 'folder'));
        $files = array_values(array_filter($items, fn($i) => $i['type'] === 'file'));

        usort($dirs, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return response()->json([
            'path' => $path,
            'parent' => dirname($path),
            'directories' => $dirs,
            'files' => $files,
        ]);
    }

    public function tree(Request $request): JsonResponse
    {
        $root = $this->resolvePath($request->input('path'));
        $depth = min((int) $request->input('depth', 3), 5);
        $showHidden = $request->boolean('showHidden', false);

        return response()->json($this->buildTree($root, 0, $depth, $showHidden));
    }

    public function read(Request $request): JsonResponse
    {
        $path = $this->resolvePath($request->input('path'));

        if (! file_exists($path) || is_dir($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        if (! is_readable($path)) {
            return response()->json(['error' => 'File is not readable'], 403);
        }

        $size = filesize($path);
        $maxSize = 10 * 1024 * 1024; // 10 MB
        if ($size > $maxSize) {
            return response()->json(['error' => 'File too large to open in editor'], 413);
        }

        $content = file_get_contents($path);
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);

        if ($encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding ?: 'UTF-8');
        }

        return response()->json([
            'path' => $path,
            'name' => basename($path),
            'content' => $content,
            'language' => $this->detectLanguage($path),
            'size' => $size,
            'modified' => filemtime($path),
            'encoding' => $encoding,
        ]);
    }

    public function write(Request $request): JsonResponse
    {
        $path = $this->resolvePath($request->input('path'));
        $content = $request->input('content');

        $validator = Validator::make($request->all(), [
            'path' => 'required|string',
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->ensureDirectoryExists(dirname($path));
        file_put_contents($path, $content);

        return response()->json([
            'path' => $path,
            'size' => filesize($path),
            'modified' => filemtime($path),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $parentPath = $this->resolvePath($request->input('parent'));
        $name = $request->input('name');
        $type = $request->input('type', 'file');

        $validator = Validator::make($request->all(), [
            'parent' => 'required|string',
            'name' => 'required|string|regex:/^[\w\-. ]+$/',
            'type' => 'in:file,folder',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (! is_dir($parentPath)) {
            return response()->json(['error' => 'Parent directory not found'], 404);
        }

        $fullPath = $parentPath . DIRECTORY_SEPARATOR . $name;

        if (file_exists($fullPath)) {
            return response()->json(['error' => 'File or folder already exists'], 409);
        }

        if ($type === 'folder') {
            mkdir($fullPath, 0755, true);
        } else {
            file_put_contents($fullPath, '');
        }

        return response()->json($this->fileInfo($fullPath, $name), 201);
    }

    public function rename(Request $request): JsonResponse
    {
        $oldPath = $this->resolvePath($request->input('path'));
        $newName = $request->input('name');

        if (! file_exists($oldPath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $newPath = dirname($oldPath) . DIRECTORY_SEPARATOR . $newName;
        if (file_exists($newPath)) {
            return response()->json(['error' => 'Target already exists'], 409);
        }

        rename($oldPath, $newPath);

        return response()->json($this->fileInfo($newPath, $newName));
    }

    public function delete(Request $request): JsonResponse
    {
        $path = $this->resolvePath($request->input('path'));

        if (! file_exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        if (is_dir($path)) {
            $this->rmdirRecursive($path);
        } else {
            unlink($path);
        }

        return response()->json(['deleted' => true]);
    }

    public function duplicate(Request $request): JsonResponse
    {
        $path = $this->resolvePath($request->input('path'));

        if (! file_exists($path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        $info = pathinfo($path);
        $copyName = $info['filename'] . ' (copy)';
        if (isset($info['extension'])) {
            $copyName .= '.' . $info['extension'];
        }
        $copyPath = $info['dirname'] . DIRECTORY_SEPARATOR . $copyName;

        if (is_dir($path)) {
            $this->copyRecursive($path, $copyPath);
        } else {
            copy($path, $copyPath);
        }

        return response()->json($this->fileInfo($copyPath, $copyName), 201);
    }

    public function move(Request $request): JsonResponse
    {
        $from = $this->resolvePath($request->input('from'));
        $to = $this->resolvePath($request->input('to'));

        if (! file_exists($from)) {
            return response()->json(['error' => 'Source not found'], 404);
        }

        $destDir = $to;
        if (! is_dir($to)) {
            $destDir = dirname($to);
        }

        $this->ensureDirectoryExists($destDir);
        rename($from, $to);

        return response()->json($this->fileInfo($to, basename($to)));
    }

    public function search(Request $request): JsonResponse
    {
        $root = $this->resolvePath($request->input('root'));
        $query = $request->input('query');
        $maxResults = min((int) $request->input('max', 100), 500);

        $results = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (count($results) >= $maxResults) break;

            $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $skip = false;
            foreach (self::DISALLOWED_PATTERNS as $pattern) {
                if (str_contains($relative, $pattern)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            if (stripos($file->getFilename(), $query) !== false) {
                $results[] = [
                    'path' => $file->getPathname(),
                    'name' => $file->getFilename(),
                    'type' => $file->isDir() ? 'folder' : 'file',
                    'size' => $file->getSize(),
                ];
            }
        }

        return response()->json(['results' => $results, 'total' => count($results)]);
    }

    public function info(Request $request): JsonResponse
    {
        $path = $this->resolvePath($request->input('path'));

        if (! file_exists($path)) {
            return response()->json(['error' => 'Not found'], 404);
        }

        return response()->json($this->fileInfo($path, basename($path)));
    }

    public function upload(Request $request): JsonResponse
    {
        $path = $this->resolvePath($request->input('path'));
        $overwrite = $request->boolean('overwrite', false);

        if (! $request->hasFile('file')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('file');
        $destPath = $path . DIRECTORY_SEPARATOR . $file->getClientOriginalName();

        if (file_exists($destPath) && ! $overwrite) {
            return response()->json(['error' => 'File already exists'], 409);
        }

        $file->move($path, $file->getClientOriginalName());

        return response()->json($this->fileInfo($destPath, $file->getClientOriginalName()));
    }

    private function resolvePath(?string $input): string
    {
        if (! $input) {
            return $this->defaultPath();
        }

        if (str_starts_with($input, '~')) {
            $input = (env('HOME') ?: env('USERPROFILE')) . substr($input, 1);
        }

        $real = realpath($input);
        return $real ?: $input;
    }

    private function defaultPath(): string
    {
        return env('HOME') ?: env('USERPROFILE') ?: 'C:\\';
    }

    private function fileInfo(string $fullPath, string $name): array
    {
        $isDir = is_dir($fullPath);
        return [
            'name' => $name,
            'path' => $fullPath,
            'type' => $isDir ? 'folder' : 'file',
            'size' => $isDir ? 0 : filesize($fullPath),
            'modified' => filemtime($fullPath),
            'language' => $isDir ? null : $this->detectLanguage($fullPath),
            'extension' => $isDir ? null : pathinfo($fullPath, PATHINFO_EXTENSION),
        ];
    }

    private function detectLanguage(string $path): string
    {
        $map = [
            'php' => 'php', 'blade.php' => 'php', 'js' => 'javascript',
            'ts' => 'typescript', 'jsx' => 'javascript', 'tsx' => 'typescript',
            'vue' => 'html', 'json' => 'json', 'md' => 'markdown',
            'css' => 'css', 'scss' => 'scss', 'less' => 'less',
            'html' => 'html', 'xml' => 'xml', 'yaml' => 'yaml', 'yml' => 'yaml',
            'py' => 'python', 'go' => 'go', 'rs' => 'rust', 'rb' => 'ruby',
            'toml' => 'toml', 'env' => 'dotenv', 'sql' => 'sql',
            'sh' => 'shell', 'bash' => 'shell', 'bat' => 'bat',
            'dockerfile' => 'dockerfile', 'gitignore' => 'plaintext',
        ];

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $filename = strtolower(basename($path));

        if (isset($map[$filename])) return $map[$filename];
        if (isset($map[$ext])) return $map[$ext];

        return 'plaintext';
    }

    private function buildTree(string $dir, int $currentDepth, int $maxDepth, bool $showHidden): ?array
    {
        if ($currentDepth >= $maxDepth) {
            return ['name' => basename($dir), 'path' => $dir, 'type' => 'folder', 'children' => []];
        }

        $children = [];
        $handle = opendir($dir);
        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..') continue;
            if (! $showHidden && $entry[0] === '.') continue;
            if (in_array($entry, self::DISALLOWED_PATTERNS)) continue;

            $fullPath = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($fullPath)) {
                $children[] = $this->buildTree($fullPath, $currentDepth + 1, $maxDepth, $showHidden);
            } else {
                $children[] = [
                    'name' => $entry,
                    'path' => $fullPath,
                    'type' => 'file',
                    'size' => filesize($fullPath),
                    'language' => $this->detectLanguage($fullPath),
                ];
            }
        }
        closedir($handle);

        usort($children, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return [
            'name' => basename($dir),
            'path' => $dir,
            'type' => 'folder',
            'children' => $children,
        ];
    }

    private function ensureDirectoryExists(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function rmdirRecursive(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    private function copyRecursive(string $src, string $dst): void
    {
        if (is_dir($src)) {
            mkdir($dst, 0755, true);
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                $target = $dst . DIRECTORY_SEPARATOR . $iterator->getSubPathname();
                $item->isDir() ? mkdir($target) : copy($item->getPathname(), $target);
            }
        } else {
            copy($src, $dst);
        }
    }
}
