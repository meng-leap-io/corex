<?php

declare(strict_types=1);

namespace App\Services\Supabase;

use App\Contracts\SupabaseStorageContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SupabaseStorageService implements SupabaseStorageContract
{
    private string $url;

    private string $key;

    public function __construct(
        private readonly SupabaseService $supabase,
    ) {
        $this->url = rtrim(config('supabase.url', ''), '/');
        $this->key = config('supabase.key', '');
    }

    private function headers(): array
    {
        return [
            'apikey' => $this->key,
            'Authorization' => "Bearer {$this->key}",
            'Content-Type' => 'application/json',
        ];
    }

    public function upload(string $bucket, string $path, $contents, array $options = []): string
    {
        $headers = [
            'apikey' => $this->key,
            'Authorization' => "Bearer {$this->key}",
        ];

        if ($contents instanceof UploadedFile) {
            $headers['Content-Type'] = $contents->getMimeType() ?? 'application/octet-stream';
            $contents = $contents->get();
        } else {
            $headers['Content-Type'] = $options['content-type'] ?? 'application/octet-stream';
        }

        $upsert = $options['upsert'] ?? true;
        $endpoint = "{$this->url}/storage/v1/object/{$bucket}/{$path}?upsert=".($upsert ? 'true' : 'false');

        $response = Http::withHeaders($headers)
            ->withBody($contents, $headers['Content-Type'])
            ->post($endpoint);

        if ($response->failed()) {
            Log::error('supabase.storage.upload_failed', [
                'bucket' => $bucket,
                'path' => $path,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        Log::info('supabase.storage.upload_success', [
            'bucket' => $bucket,
            'path' => $path,
        ]);

        return $this->getPublicUrl($bucket, $path);
    }

    public function download(string $bucket, string $path): ?string
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$this->key}",
        ])->get("{$this->url}/storage/v1/object/{$bucket}/{$path}");

        if ($response->failed()) {
            Log::warning('supabase.storage.download_failed', [
                'bucket' => $bucket,
                'path' => $path,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->body();
    }

    public function delete(string $bucket, string $path): bool
    {
        $response = Http::withHeaders($this->headers())
            ->send('DELETE', "{$this->url}/storage/v1/object/{$bucket}/{$path}");

        if ($response->failed()) {
            Log::error('supabase.storage.delete_failed', [
                'bucket' => $bucket,
                'path' => $path,
                'status' => $response->status(),
            ]);

            return false;
        }

        return true;
    }

    public function deleteMultiple(string $bucket, array $paths): bool
    {
        $response = Http::withHeaders($this->headers())
            ->send('DELETE', "{$this->url}/storage/v1/object/{$bucket}", [
                'prefixes' => $paths,
            ]);

        return ! $response->failed();
    }

    public function listFiles(string $bucket, string $directory = ''): array
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->url}/storage/v1/object/list/{$bucket}", [
                'prefix' => $directory,
                'limit' => 100,
                'offset' => 0,
                'sortBy' => ['column' => 'name', 'order' => 'asc'],
            ]);

        if ($response->failed()) {
            Log::error('supabase.storage.list_failed', [
                'bucket' => $bucket,
                'directory' => $directory,
                'status' => $response->status(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    public function getFileInfo(string $bucket, string $path): ?array
    {
        $files = $this->listFiles($bucket, dirname($path));

        $targetName = basename($path);

        foreach ($files as $file) {
            if (($file['name'] ?? '') === $targetName) {
                return $file;
            }
        }

        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$this->key}",
        ])->head("{$this->url}/storage/v1/object/{$bucket}/{$path}");

        if ($response->successful()) {
            return [
                'name' => basename($path),
                'bucket_id' => $bucket,
                'content_type' => $response->header('Content-Type'),
                'content_length' => (int) $response->header('Content-Length'),
                'last_modified' => $response->header('Last-Modified'),
            ];
        }

        return null;
    }

    public function move(string $bucket, string $from, string $to): bool
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->url}/storage/v1/object/move", [
                'bucket' => $bucket,
                'sourceKey' => $from,
                'destinationKey' => $to,
            ]);

        if ($response->failed()) {
            Log::error('supabase.storage.move_failed', [
                'bucket' => $bucket,
                'from' => $from,
                'to' => $to,
                'status' => $response->status(),
            ]);

            return false;
        }

        return true;
    }

    public function copy(string $bucket, string $from, string $to): bool
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->url}/storage/v1/object/copy", [
                'bucket' => $bucket,
                'sourceKey' => $from,
                'destinationKey' => $to,
            ]);

        if ($response->failed()) {
            Log::error('supabase.storage.copy_failed', [
                'bucket' => $bucket,
                'from' => $from,
                'to' => $to,
                'status' => $response->status(),
            ]);

            return false;
        }

        return true;
    }

    public function getPublicUrl(string $bucket, string $path): string
    {
        return "{$this->url}/storage/v1/object/public/{$bucket}/{$path}";
    }

    public function getSignedUrl(string $bucket, string $path, int $expiresIn = 3600): string
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->url}/storage/v1/object/sign/{$bucket}/{$path}", [
                'expiresIn' => $expiresIn,
            ]);

        if ($response->failed()) {
            Log::error('supabase.storage.signed_url_failed', [
                'bucket' => $bucket,
                'path' => $path,
            ]);
            $response->throw();
        }

        $result = $response->json();

        return "{$this->url}{$result['signedURL']}";
    }

    public function createBucket(string $name, bool $public = false): array
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->url}/storage/v1/bucket", [
                'name' => $name,
                'public' => $public,
            ]);

        if ($response->failed()) {
            Log::error('supabase.storage.create_bucket_failed', [
                'name' => $name,
                'status' => $response->status(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    public function bucketExists(string $name): bool
    {
        return $this->getBucket($name) !== null;
    }

    public function getBucket(string $name): ?array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$this->key}",
        ])->get("{$this->url}/storage/v1/bucket/{$name}");

        if ($response->failed()) {
            return null;
        }

        return $response->json();
    }

    public function listBuckets(): array
    {
        $response = Http::withHeaders([
            'apikey' => $this->key,
            'Authorization' => "Bearer {$this->key}",
        ])->get("{$this->url}/storage/v1/bucket");

        if ($response->failed()) {
            Log::error('supabase.storage.list_buckets_failed', [
                'status' => $response->status(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    public function updateBucket(string $name, bool $public): array
    {
        $response = Http::withHeaders($this->headers())
            ->put("{$this->url}/storage/v1/bucket/{$name}", [
                'public' => $public,
            ]);

        if ($response->failed()) {
            Log::error('supabase.storage.update_bucket_failed', [
                'name' => $name,
                'status' => $response->status(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    public function deleteBucket(string $name): bool
    {
        $response = Http::withHeaders($this->headers())
            ->send('DELETE', "{$this->url}/storage/v1/bucket/{$name}");

        if ($response->failed()) {
            Log::error('supabase.storage.delete_bucket_failed', [
                'name' => $name,
                'status' => $response->status(),
            ]);

            return false;
        }

        return true;
    }

    public function uploadFromFile(string $bucket, string $path, UploadedFile $file, array $options = []): string
    {
        $uniquePath = $options['unique'] ?? true
            ? $path.'/'.Str::random(40).'.'.$file->getClientOriginalExtension()
            : $path.'/'.$file->getClientOriginalName();

        return $this->upload($bucket, $uniquePath, $file, $options);
    }

    public function uploadFromPath(string $bucket, string $path, string $localPath, array $options = []): string
    {
        $contents = file_get_contents($localPath);

        if ($contents === false) {
            throw new \RuntimeException("Cannot read file from path: {$localPath}");
        }

        return $this->upload($bucket, $path, $contents, $options);
    }
}
