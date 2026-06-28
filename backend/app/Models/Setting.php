<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const string TYPE_STRING = 'string';

    public const string TYPE_BOOLEAN = 'boolean';

    public const string TYPE_INTEGER = 'integer';

    public const string TYPE_FLOAT = 'float';

    public const string TYPE_JSON = 'json';

    public const string TYPE_ENCRYPTED = 'encrypted';

    public const array TYPES = [
        self::TYPE_STRING,
        self::TYPE_BOOLEAN,
        self::TYPE_INTEGER,
        self::TYPE_FLOAT,
        self::TYPE_JSON,
        self::TYPE_ENCRYPTED,
    ];

    protected $fillable = [
        'user_id',
        'team_id',
        'key',
        'value',
        'type',
        'category',
        'is_encrypted',
        'metadata',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            self::TYPE_BOOLEAN => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_INTEGER => (int) $this->value,
            self::TYPE_FLOAT => (float) $this->value,
            self::TYPE_JSON => json_decode($this->value, true),
            default => $this->value,
        };
    }

    public function scopeByKey($query, string $key)
    {
        return $query->where('key', $key);
    }

    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTeam($query, string $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('user_id')->whereNull('team_id');
    }
}
