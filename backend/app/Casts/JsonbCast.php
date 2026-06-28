<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class JsonbCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        return json_decode($value, true);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }
}
