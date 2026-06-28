<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EncryptedJsonCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        $decrypted = Crypt::decryptString($value);

        return json_decode($decrypted, true);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        $encoded = json_encode($value, JSON_THROW_ON_ERROR);

        return Crypt::encryptString($encoded);
    }
}
