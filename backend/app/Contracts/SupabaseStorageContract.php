<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface SupabaseStorageContract
{
    public function upload(string $bucket, string $path, $contents, array $options = []): string;

    public function download(string $bucket, string $path): ?string;

    public function delete(string $bucket, string $path): bool;

    public function listFiles(string $bucket, string $directory = ''): array;

    public function getPublicUrl(string $bucket, string $path): string;

    public function getSignedUrl(string $bucket, string $path, int $expiresIn = 3600): string;

    public function createBucket(string $name, bool $public = false): array;

    public function bucketExists(string $name): bool;

    public function getFileInfo(string $bucket, string $path): ?array;

    public function move(string $bucket, string $from, string $to): bool;

    public function copy(string $bucket, string $from, string $to): bool;

    public function deleteMultiple(string $bucket, array $paths): bool;

    public function uploadFromFile(string $bucket, string $path, UploadedFile $file, array $options = []): string;

    public function uploadFromPath(string $bucket, string $path, string $localPath, array $options = []): string;

    public function getBucket(string $name): ?array;

    public function listBuckets(): array;

    public function updateBucket(string $name, bool $public): array;

    public function deleteBucket(string $name): bool;
}
