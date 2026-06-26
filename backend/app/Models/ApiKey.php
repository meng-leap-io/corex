<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'key',
        'permissions',
        'last_used_at',
        'expires_at',
    ];

    protected $hidden = [
        'key',
    ];

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ApiKey $apiKey) {
            if (empty($apiKey->key)) {
                $apiKey->key = 'corex_' . Str::random(64);
            }
            if (empty($apiKey->permissions)) {
                $apiKey->permissions = ['read'];
            }
        });

        static::created(function (ApiKey $apiKey) {
            // api_key_created hook
        });

        static::updated(function (ApiKey $apiKey) {
            if ($apiKey->wasChanged('last_used_at')) {
                // Key was used — hook for analytics
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []) || in_array('admin', $this->permissions ?? []);
    }

    public function markAsUsed(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    public function getKeyPrefixAttribute(): string
    {
        return substr($this->key, 0, 8) . '...';
    }

    public function getIsActiveAttribute(): bool
    {
        return !$this->isExpired();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeRecentlyUsed(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_used_at', '>=', now()->subDays($days));
    }

    public function scopeWithPermission(Builder $query, string $permission): Builder
    {
        return $query->whereJsonContains('permissions', $permission);
    }
}
