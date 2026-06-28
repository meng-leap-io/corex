<?php

declare(strict_types=1);

namespace App\Services\Supabase;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseService
{
    private string $url;

    private string $key;

    private string $serviceKey;

    public function __construct()
    {
        $this->url = rtrim(config('supabase.url', ''), '/');
        $this->key = config('supabase.key', '');
        $this->serviceKey = config('supabase.service_key', '');
    }

    public function client(bool $useServiceKey = false): PendingRequest
    {
        $key = $useServiceKey && $this->serviceKey ? $this->serviceKey : $this->key;

        return Http::withHeaders([
            'apikey' => $key,
            'Authorization' => "Bearer {$key}",
            'Content-Type' => 'application/json',
            'Prefer' => 'return=representation',
        ])->baseUrl($this->url)->timeout(30)->retry(2, 1000);
    }

    public function restClient(): PendingRequest
    {
        return $this->client()->baseUrl("{$this->url}/rest/v1");
    }

    public function authClient(): PendingRequest
    {
        return $this->client()->baseUrl("{$this->url}/auth/v1");
    }

    public function storageClient(): PendingRequest
    {
        return $this->client()->baseUrl("{$this->url}/storage/v1");
    }

    public function realtimeClient(): PendingRequest
    {
        return $this->client()->baseUrl("{$this->url}/realtime/v1");
    }

    public function get(string $endpoint, array $params = [], bool $useServiceKey = false): array
    {
        $response = $this->client($useServiceKey)->get($endpoint, $params);

        if ($response->failed()) {
            Log::error('supabase.get_failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    public function post(string $endpoint, array $data = [], bool $useServiceKey = false): array
    {
        $response = $this->client($useServiceKey)->post($endpoint, $data);

        if ($response->failed()) {
            Log::error('supabase.post_failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    public function put(string $endpoint, array $data = [], bool $useServiceKey = false): array
    {
        $response = $this->client($useServiceKey)->put($endpoint, $data);

        if ($response->failed()) {
            Log::error('supabase.put_failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    public function patch(string $endpoint, array $data = [], bool $useServiceKey = false): array
    {
        $response = $this->client($useServiceKey)->patch($endpoint, $data);

        if ($response->failed()) {
            Log::error('supabase.patch_failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    public function delete(string $endpoint, array $params = [], bool $useServiceKey = false): array
    {
        $response = $this->client($useServiceKey)->delete($endpoint, $params);

        if ($response->failed()) {
            Log::error('supabase.delete_failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    public function rpc(string $function, array $params = []): array
    {
        return $this->post("/rest/v1/rpc/{$function}", $params);
    }

    public function isConnected(): bool
    {
        try {
            $this->get('/rest/v1/', []);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
