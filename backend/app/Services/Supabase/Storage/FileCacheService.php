<?php

declare(strict_types=1);

namespace App\Services\Supabase\Storage;

use App\Contracts\SupabaseStorageContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileCacheService
{
    private string $localDisk;

    private int $defaultTtl;

    private int $maxCacheSize;

    public function __construct(
        private readonly SupabaseStorageContract $storage,
    ) {
        $this->localDisk = config('supabase.storage.cache.disk', 'local');
        $this->defaultTtl = config('supabase.storage.cache.ttl', 3600);
        $this->maxCacheSize = config('supabase.storage.cache.max_size', 500 * 1024 * 1024);
    }

    public function get(string $bucket, string $path, ?int $ttl = null): ?string
    {
        $cacheKey = $this->cacheKey($bucket, $path);
        $localPath = $this->localPath($bucket, $path);

        if ($this->isCached($localPath, $ttl)) {
            return Storage::disk($this->localDisk)->path($localPath);
        }

        $contents = $this->storage->download($bucket, $path);

        if ($contents === null) {
            return null;
        }

        $this->store($localPath, $contents);

        Cache::put($cacheKey, now()->timestamp, $ttl ?? $this->defaultTtl);

        $this->evictIfNeeded();

        return Storage::disk($this->localDisk)->path($localPath);
    }

    public function put(string $bucket, string $path, $contents): string
    {
        $url = $this->storage->upload($bucket, $path, $contents);

        $localPath = $this->localPath($bucket, $path);

        if (is_string($contents)) {
            $this->store($localPath, $contents);
        }

        return $url;
    }

    public function forget(string $bucket, string $path): void
    {
        $cacheKey = $this->cacheKey($bucket, $path);
        $localPath = $this->localPath($bucket, $path);

        Cache::forget($cacheKey);

        if (Storage::disk($this->localDisk)->exists($localPath)) {
            Storage::disk($this->localDisk)->delete($localPath);
        }
    }

    public function flushBucket(string $bucket): void
    {
        $cacheDir = "supabase-cache/{$bucket}";

        if (Storage::disk($this->localDisk)->exists($cacheDir)) {
            Storage::disk($this->localDisk)->deleteDirectory($cacheDir);
        }

        $pattern = "supabase:cache:{$bucket}:*";

        foreach (Cache::get($pattern, []) as $key) {
            Cache::forget($key);
        }
    }

    public function isCached(string $localPath, ?int $ttl = null): bool
    {
        if (! Storage::disk($this->localDisk)->exists($localPath)) {
            return false;
        }

        if ($ttl === null) {
            return true;
        }

        $lastModified = Storage::disk($this->localDisk)->lastModified($localPath);

        return ($lastModified + $ttl) > now()->timestamp;
    }

    public function getCacheSize(): int
    {
        $cacheDir = 'supabase-cache';

        if (! Storage::disk($this->localDisk)->exists($cacheDir)) {
            return 0;
        }

        $size = 0;

        foreach (Storage::disk($this->localDisk)->allFiles($cacheDir) as $file) {
            $size += Storage::disk($this->localDisk)->size($file);
        }

        return $size;
    }

    private function store(string $localPath, string $contents): void
    {
        $dir = dirname($localPath);

        if (! Storage::disk($this->localDisk)->exists($dir)) {
            Storage::disk($this->localDisk)->makeDirectory($dir);
        }

        Storage::disk($this->localDisk)->put($localPath, $contents);
    }

    private function localPath(string $bucket, string $path): string
    {
        return "supabase-cache/{$bucket}/{$path}";
    }

    private function cacheKey(string $bucket, string $path): string
    {
        return "supabase:cache:{$bucket}:{$path}";
    }

    private function evictIfNeeded(): void
    {
        if ($this->getCacheSize() <= $this->maxCacheSize) {
            return;
        }

        $cacheDir = 'supabase-cache';

        if (! Storage::disk($this->localDisk)->exists($cacheDir)) {
            return;
        }

        $files = Storage::disk($this->localDisk)->allFiles($cacheDir);

        usort($files, function (string $a, string $b) {
            return Storage::disk($this->localDisk)->lastModified($a)
                <=> Storage::disk($this->localDisk)->lastModified($b);
        });

        $evictTarget = $this->getCacheSize() - (int) ($this->maxCacheSize * 0.8);
        $evicted = 0;

        foreach ($files as $file) {
            if ($evicted >= $evictTarget) {
                break;
            }

            $evicted += Storage::disk($this->localDisk)->size($file);
            Storage::disk($this->localDisk)->delete($file);
        }

        Log::info('supabase.cache.evicted', [
            'files' => count($files),
            'bytes_evicted' => $evicted,
            'remaining' => $this->getCacheSize(),
        ]);
    }
}
