<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageView extends Model
{
    use HasFactory, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'path',
        'route',
        'method',
        'status_code',
        'duration_ms',
        'query_time_ms',
        'memory_bytes',
        'query_log',
        'session_id',
        'ip_address',
        'user_agent',
        'referer',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'duration_ms' => 'float',
        'query_time_ms' => 'float',
        'memory_bytes' => 'integer',
        'query_log' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForPath($query, string $path)
    {
        return $query->where('path', $path);
    }

    public function scopeErrors($query)
    {
        return $query->where('status_code', '>=', 400);
    }

    public function scopeSlow($query, float $thresholdMs = 1000)
    {
        return $query->where('duration_ms', '>=', $thresholdMs);
    }

    public function scopeSince($query, $date)
    {
        return $query->where('created_at', '>=', $date);
    }
}
