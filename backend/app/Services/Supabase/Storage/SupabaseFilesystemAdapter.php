<?php

declare(strict_types=1);

namespace App\Services\Supabase\Storage;

use App\Contracts\SupabaseStorageContract;
use Illuminate\Filesystem\FilesystemAdapter as BaseAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter as FlysystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;

class SupabaseFilesystemAdapter implements FlysystemAdapter
{
    private string $bucket;

    public function __construct(
        private readonly SupabaseStorageContract $storage,
        ?string $bucket = null,
    ) {
        $this->bucket = $bucket ?? config('supabase.storage.bucket', 'app-files');
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            $mimeType = $config->get('mimetype', 'application/octet-stream');
            $this->storage->upload($this->bucket, $path, $contents, [
                'content-type' => $mimeType,
                'upsert' => true,
            ]);
        } catch (\Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $streamContents = stream_get_contents($contents);

        if ($streamContents === false) {
            throw UnableToWriteFile::atLocation($path, 'Failed to read stream');
        }

        $this->write($path, $streamContents, $config);
    }

    public function read(string $path): string
    {
        $contents = $this->storage->download($this->bucket, $path);

        if ($contents === null) {
            throw UnableToReadFile::fromLocation($path);
        }

        return $contents;
    }

    public function readStream(string $path)
    {
        $contents = $this->read($path);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): bool
    {
        return $this->storage->delete($this->bucket, $path);
    }

    public function fileExists(string $path): bool
    {
        return $this->storage->getFileInfo($this->bucket, $path) !== null;
    }

    public function directoryExists(string $path): bool
    {
        $files = $this->storage->listFiles($this->bucket, $path);

        return !empty($files);
    }

    public function deleteDirectory(string $path): bool
    {
        $files = $this->storage->listFiles($this->bucket, $path);

        if (empty($files)) {
            return true;
        }

        $paths = array_map(fn ($file) => $file['name'], $files);

        return $this->storage->deleteMultiple($this->bucket, $paths);
    }

    public function createDirectory(string $path, Config $config): void
    {
    }

    public function setVisibility(string $path, string $visibility): void
    {
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, null, null, 'public');
    }

    public function mimeType(string $path): FileAttributes
    {
        $info = $this->storage->getFileInfo($this->bucket, $path);

        return new FileAttributes(
            $path,
            null,
            null,
            null,
            $info['content_type'] ?? 'application/octet-stream',
        );
    }

    public function lastModified(string $path): FileAttributes
    {
        $info = $this->storage->getFileInfo($this->bucket, $path);

        $timestamp = isset($info['last_modified'])
            ? strtotime($info['last_modified'])
            : now()->timestamp;

        return new FileAttributes($path, null, null, $timestamp);
    }

    public function fileSize(string $path): FileAttributes
    {
        $info = $this->storage->getFileInfo($this->bucket, $path);

        return new FileAttributes(
            $path,
            $info['content_length'] ?? 0,
        );
    }

    public function listContents(string $path, bool $deep = false): iterable
    {
        $files = $this->storage->listFiles($this->bucket, $path);

        foreach ($files as $file) {
            yield new FileAttributes(
                $file['name'] ?? $path,
                $file['metadata'][0]['contentLength'] ?? null,
                null,
                isset($file['created_at']) ? strtotime($file['created_at']) : null,
                $file['metadata'][0]['mimetype'] ?? 'application/octet-stream',
            );
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->storage->move($this->bucket, $source, $destination);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->storage->copy($this->bucket, $source, $destination);
    }

    public function publicUrl(string $path): string
    {
        return $this->storage->getPublicUrl($this->bucket, $path);
    }

    public function temporaryUrl(string $path, int $expiresIn = 3600): string
    {
        return $this->storage->getSignedUrl($this->bucket, $path, $expiresIn);
    }
}
