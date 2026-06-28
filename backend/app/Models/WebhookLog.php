<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookLog extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'provider',
        'event_type',
        'event_id',
        'endpoint_id',
        'user_id',
        'status',
        'payload',
        'headers',
        'response',
        'response_status',
        'attempts',
        'max_attempts',
        'error_message',
        'processed_at',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'headers' => 'array',
        'response' => 'array',
        'response_status' => 'integer',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'processed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'endpoint_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(WebhookEvent::class, 'log_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeForEvent($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function markCompleted(?array $response = null, ?int $statusCode = null): void
    {
        $this->update([
            'status' => 'completed',
            'response' => $response,
            'response_status' => $statusCode,
            'processed_at' => now(),
        ]);
    }

    public function markFailed(string $error, ?array $response = null): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'response' => $response,
            'failed_at' => now(),
        ]);
    }

    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts && $this->status === 'failed';
    }
}
