<?php

declare(strict_types=1);

namespace App\Services\Supabase\Storage;

use App\Contracts\SupabaseStorageContract;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileManagementService
{
    public const BUCKET_PROJECTS = 'projects';

    public const BUCKET_AVATARS = 'avatars';

    public const BUCKET_DOCUMENTS = 'documents';

    public const BUCKET_EXPORTS = 'exports';

    private array $buckets;

    public function __construct(
        private readonly SupabaseStorageContract $storage,
        private readonly FileValidationService $validator,
        private readonly ImageOptimizerService $optimizer,
        private readonly FileCacheService $cache,
    ) {
        $this->buckets = config('supabase.storage.buckets', []);
    }

    public function upload(
        User $user,
        UploadedFile $file,
        string $bucket,
        ?string $directory = null,
        array $options = [],
    ): File {
        $errors = $this->validator->validateUpload($file, $bucket);

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        $directory = $directory ?? Str::random(2) . '/' . Str::random(2);
        $sanitizedName = $this->validator->sanitizeFilename($file->getClientOriginalName());
        $storedName = Str::random(20) . '_' . $sanitizedName;
        $path = "{$directory}/{$storedName}";

        $uploadPath = $path;
        $optimizedPath = null;

        if ($this->validator->isImage($file) && $options['optimize'] ?? true) {
            $optimizedPath = $this->optimizer->optimize($file, $options);

            if ($optimizedPath !== null) {
                $storedName = pathinfo($storedName, PATHINFO_FILENAME) . '.webp';
                $path = "{$directory}/{$storedName}";
                $url = $this->storage->uploadFromPath($bucket, $path, $optimizedPath, $options);

                @unlink($optimizedPath);
            } else {
                $url = $this->storage->upload($bucket, $path, $file, $options);
            }
        } else {
            $url = $this->storage->upload($bucket, $path, $file, $options);
        }

        $fileModel = File::create([
            'user_id' => $user->id,
            'bucket' => $bucket,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'extension' => $file->getClientOriginalExtension(),
            'url' => $url,
            'metadata' => array_merge([
                'upload_source' => $options['source'] ?? 'web',
                'optimized' => $optimizedPath !== null,
            ], $options['metadata'] ?? []),
            'disk' => 'supabase',
        ]);

        Log::info('supabase.file.uploaded', [
            'file_id' => $fileModel->id,
            'bucket' => $bucket,
            'path' => $path,
        ]);

        return $fileModel;
    }

    public function uploadAvatar(User $user, UploadedFile $file): File
    {
        $errors = $this->validator->validateUpload($file, self::BUCKET_AVATARS);

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        $this->deleteUserAvatars($user);

        $ext = 'webp';
        $path = "users/{$user->id}/avatar.{$ext}";

        $optimizedPath = $this->optimizer->createAvatar($file->getPathname(), 256);

        if ($optimizedPath !== null) {
            $url = $this->storage->uploadFromPath(self::BUCKET_AVATARS, $path, $optimizedPath);
            @unlink($optimizedPath);
        } else {
            $url = $this->storage->upload(self::BUCKET_AVATARS, $path, $file);
        }

        $fileModel = File::create([
            'user_id' => $user->id,
            'bucket' => self::BUCKET_AVATARS,
            'path' => $path,
            'original_name' => 'avatar.' . $ext,
            'mime_type' => 'image/webp',
            'size' => $file->getSize(),
            'extension' => $ext,
            'url' => $url,
            'metadata' => [
                'type' => 'avatar',
                'optimized' => true,
            ],
            'disk' => 'supabase',
        ]);

        $user->update(['avatar' => $url]);

        return $fileModel;
    }

    public function download(File $file): ?string
    {
        return $this->cache->get($file->bucket, $file->path);
    }

    public function downloadAsStream(File $file)
    {
        $contents = $this->storage->download($file->bucket, $file->path);

        if ($contents === null) {
            return null;
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    public function delete(File $file): bool
    {
        DB::beginTransaction();

        try {
            $deleted = $this->storage->delete($file->bucket, $file->path);

            if (!$deleted) {
                Log::warning('supabase.file.remote_delete_failed', [
                    'file_id' => $file->id,
                    'bucket' => $file->bucket,
                    'path' => $file->path,
                ]);
            }

            $this->cache->forget($file->bucket, $file->path);

            $file->delete();

            DB::commit();

            Log::info('supabase.file.deleted', ['file_id' => $file->id]);

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('supabase.file.delete_failed', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function deleteMultiple(array $files): int
    {
        $count = 0;

        foreach ($files as $file) {
            if ($file instanceof File) {
                try {
                    $this->delete($file);
                    $count++;
                } catch (\Throwable $e) {
                    Log::error('supabase.file.bulk_delete_failed', [
                        'file_id' => $file->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $count;
    }

    public function listFiles(string $bucket, ?User $user = null, string $directory = ''): array
    {
        $query = File::where('bucket', $bucket);

        if ($user !== null) {
            $query->where('user_id', $user->id);
        }

        if ($directory !== '') {
            $query->where('path', 'like', "{$directory}%");
        }

        return $query->orderBy('created_at', 'desc')->get()->toArray();
    }

    public function listRemoteFiles(string $bucket, string $directory = ''): array
    {
        return $this->storage->listFiles($bucket, $directory);
    }

    public function getSignedDownloadUrl(File $file, int $expiresIn = 3600): string
    {
        return $this->storage->getSignedUrl($file->bucket, $file->path, $expiresIn);
    }

    public function createShareLink(File $file, int $expiresIn = 86400): array
    {
        $token = Str::random(64);
        $url = $this->getSignedDownloadUrl($file, $expiresIn);

        $file->update([
            'metadata' => array_merge($file->metadata ?? [], [
                'share_token' => $token,
                'share_expires_at' => now()->addSeconds($expiresIn)->toIso8601String(),
                'share_url' => $url,
            ]),
        ]);

        return [
            'token' => $token,
            'url' => $url,
            'expires_at' => now()->addSeconds($expiresIn),
        ];
    }

    public function getSharedFile(string $token): ?File
    {
        $files = File::all()->filter(function (File $file) use ($token) {
            $metadata = $file->metadata ?? [];

            return ($metadata['share_token'] ?? null) === $token
                && ($metadata['share_expires_at'] ?? null) > now()->toIso8601String();
        });

        return $files->first();
    }

    public function duplicate(File $file, ?User $user = null): File
    {
        $contents = $this->storage->download($file->bucket, $file->path);

        if ($contents === null) {
            throw new \RuntimeException("Cannot duplicate file {$file->id}: source not found");
        }

        $newPath = 'duplicates/' . Str::random(40) . '_' . $file->original_name;

        $url = $this->storage->upload($file->bucket, $newPath, $contents);

        return File::create([
            'user_id' => $user?->id ?? $file->user_id,
            'bucket' => $file->bucket,
            'path' => $newPath,
            'original_name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size' => $file->size,
            'extension' => $file->extension,
            'url' => $url,
            'metadata' => array_merge($file->metadata ?? [], ['duplicated_from' => $file->id]),
            'disk' => 'supabase',
        ]);
    }

    public function ensureBucketsExist(): void
    {
        foreach ($this->buckets as $name => $config) {
            if (!$this->storage->bucketExists($name)) {
                $this->storage->createBucket($name, $config['public'] ?? false);

                Log::info('supabase.bucket.created', ['name' => $name, 'public' => $config['public'] ?? false]);
            }
        }
    }

    public function exportProject(array $files, string $projectName): array
    {
        $exportPath = "exports/" . Str::slug($projectName) . '_' . now()->format('Ymd_His');

        $manifest = [
            'project' => $projectName,
            'exported_at' => now()->toIso8601String(),
            'files' => [],
        ];

        foreach ($files as $file) {
            $relativePath = $exportPath . '/' . $file->original_name;

            $contents = $this->storage->download($file->bucket, $file->path);

            if ($contents !== null) {
                $this->storage->upload(self::BUCKET_EXPORTS, $relativePath, $contents);

                $manifest['files'][] = [
                    'original_id' => $file->id,
                    'name' => $file->original_name,
                    'path' => $relativePath,
                    'mime' => $file->mime_type,
                ];
            }
        }

        $manifestJson = json_encode($manifest, JSON_PRETTY_PRINT);
        $manifestPath = "{$exportPath}/manifest.json";

        $this->storage->upload(self::BUCKET_EXPORTS, $manifestPath, $manifestJson);

        return [
            'path' => $exportPath,
            'manifest_url' => $this->storage->getPublicUrl(self::BUCKET_EXPORTS, $manifestPath),
            'file_count' => count($manifest['files']),
        ];
    }

    public function importProject(string $exportPath, User $user, ?string $projectId = null): array
    {
        $manifestContent = $this->storage->download(self::BUCKET_EXPORTS, "{$exportPath}/manifest.json");

        if ($manifestContent === null) {
            throw new \RuntimeException("Export manifest not found at {$exportPath}/manifest.json");
        }

        $manifest = json_decode($manifestContent, true);

        if ($manifest === null) {
            throw new \RuntimeException("Invalid manifest file");
        }

        $imported = [];

        foreach ($manifest['files'] as $fileInfo) {
            $contents = $this->storage->download(self::BUCKET_EXPORTS, $fileInfo['path']);

            if ($contents === null) {
                Log::warning('supabase.import.file_missing', ['path' => $fileInfo['path']]);
                continue;
            }

            $importPath = "imported/{$user->id}/{$fileInfo['name']}";

            $url = $this->storage->upload(self::BUCKET_PROJECTS, $importPath, $contents);

            $fileModel = File::create([
                'user_id' => $user->id,
                'bucket' => self::BUCKET_PROJECTS,
                'path' => $importPath,
                'original_name' => $fileInfo['name'],
                'mime_type' => $fileInfo['mime'],
                'size' => strlen($contents),
                'extension' => pathinfo($fileInfo['name'], PATHINFO_EXTENSION),
                'url' => $url,
                'metadata' => [
                    'imported_from' => $exportPath,
                    'original_file_id' => $fileInfo['original_id'],
                ],
                'disk' => 'supabase',
            ]);

            if ($projectId !== null) {
                $fileModel->update(['project_id' => $projectId]);
            }

            $imported[] = $fileModel;
        }

        return $imported;
    }

    private function deleteUserAvatars(User $user): void
    {
        $existingAvatars = File::where('user_id', $user->id)
            ->where('bucket', self::BUCKET_AVATARS)
            ->where('metadata->type', 'avatar')
            ->get();

        foreach ($existingAvatars as $avatar) {
            try {
                $this->storage->delete($avatar->bucket, $avatar->path);
                $this->cache->forget($avatar->bucket, $avatar->path);
                $avatar->delete();
            } catch (\Throwable $e) {
                Log::warning('supabase.avatar.delete_old_failed', [
                    'file_id' => $avatar->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
