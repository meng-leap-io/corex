<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeatureUsage extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'feature_usage';

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'feature',
        'action',
        'context',
        'success',
        'duration_ms',
    ];

    protected $casts = [
        'context' => 'array',
        'success' => 'boolean',
        'duration_ms' => 'float',
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

    public function scopeForFeature($query, string $feature)
    {
        return $query->where('feature', $feature);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    public function scopeSince($query, $date)
    {
        return $query->where('created_at', '>=', $date);
    }
}
