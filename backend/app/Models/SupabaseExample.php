<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupabaseExample extends Model
{
    use HasFactory, HasUuids, SoftDeletes, Syncable;

    protected $connection = 'sqlite';

    protected $fillable = [
        'name',
        'data',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }
}
