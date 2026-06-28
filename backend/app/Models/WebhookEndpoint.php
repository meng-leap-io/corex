<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebhookEndpoint extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'url',
        'secret',
        'events',
        'status',
        'retry_count',
        'timeout_seconds',
        'headers',
        'metadata',
        'last_success_at',
        'last_failure_at',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'metadata' => 'array',
        'retry_count' => 'integer',
        'timeout_seconds' => 'integer',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class, 'endpoint_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForEvent($query, string $eventType)
    {
        return $query->whereJsonContains('events', $eventType);
    }

    public function markSuccess(): void
    {
        $this->update(['last_success_at' => now()]);
    }

    public function markFailure(): void
    {
        $this->update(['last_failure_at' => now()]);
    }
}
