<?php

declare(strict_types=1);

namespace App\Facades;

use App\Services\Supabase\SupabaseService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Services\Supabase\SupabaseService api()
 * @method static \App\Contracts\SupabaseAuthContract auth()
 * @method static \App\Contracts\SupabaseStorageContract storage()
 * @method static \App\Contracts\SyncContract sync()
 * @method static \Illuminate\Http\Client\PendingRequest client(bool $useServiceKey = false)
 * @method static \Illuminate\Http\Client\PendingRequest restClient()
 * @method static \Illuminate\Http\Client\PendingRequest authClient()
 * @method static \Illuminate\Http\Client\PendingRequest storageClient()
 * @method static array get(string $endpoint, array $params = [], bool $useServiceKey = false)
 * @method static array post(string $endpoint, array $data = [], bool $useServiceKey = false)
 * @method static array put(string $endpoint, array $data = [], bool $useServiceKey = false)
 * @method static array patch(string $endpoint, array $data = [], bool $useServiceKey = false)
 * @method static array delete(string $endpoint, array $params = [], bool $useServiceKey = false)
 * @method static array rpc(string $function, array $params = [])
 * @method static bool isConnected()
 *
 * @see SupabaseService
 */
class Supabase extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SupabaseService::class;
    }
}
