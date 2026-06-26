<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use HasFactory, HasUuids;

    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_ANTHROPIC = 'anthropic';
    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_AZURE = 'azure';
    public const PROVIDER_AWS = 'aws';

    public const PROVIDERS = [
        self::PROVIDER_OPENAI,
        self::PROVIDER_ANTHROPIC,
        self::PROVIDER_GOOGLE,
        self::PROVIDER_AZURE,
        self::PROVIDER_AWS,
    ];

    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'prompt_tokens',
        'completion_tokens',
        'cost',
        'duration',
        'endpoint',
        'success',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'prompt_tokens' => 'integer',
            'completion_tokens' => 'integer',
            'cost' => 'decimal:6',
            'duration' => 'integer',
            'success' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiUsageLog $log) {
            if (!isset($log->prompt_tokens)) {
                $log->prompt_tokens = 0;
            }
            if (!isset($log->completion_tokens)) {
                $log->completion_tokens = 0;
            }
            if (!isset($log->cost)) {
                $log->cost = 0;
            }
            if (!isset($log->success)) {
                $log->success = true;
            }
        });

        static::created(function (AiUsageLog $log) {
            if ($log->user && $log->success) {
                $totalTokens = $log->prompt_tokens + $log->completion_tokens;
                $log->user->incrementApiUsage($totalTokens);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTotalTokensAttribute(): int
    {
        return $this->prompt_tokens + $this->completion_tokens;
    }

    public function getDurationSecondsAttribute(): ?float
    {
        return $this->duration ? round($this->duration / 1000, 2) : null;
    }

    public function getCostPerTokenAttribute(): ?float
    {
        $totalTokens = $this->getTotalTokensAttribute();
        return $totalTokens > 0 ? round($this->cost / $totalTokens, 8) : null;
    }

    public function scopeByProvider(Builder $query, string $provider): Builder
    {
        return $query->where('provider', $provider);
    }

    public function scopeByModel(Builder $query, string $model): Builder
    {
        return $query->where('model', $model);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('success', true);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('success', false);
    }

    public function scopeByDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function scopeByEndpoint(Builder $query, string $endpoint): Builder
    {
        return $query->where('endpoint', $endpoint);
    }

    public function scopeOrderedByDuration(Builder $query, string $direction = 'desc'): Builder
    {
        return $query->orderBy('duration', $direction);
    }
}
