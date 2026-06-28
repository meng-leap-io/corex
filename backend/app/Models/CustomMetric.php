<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomMetric extends Model
{
    use HasFactory, HasUuids;

    const UPDATED_AT = null;

    const TYPE_GAUGE = 'gauge';

    const TYPE_COUNTER = 'counter';

    const TYPE_HISTOGRAM = 'histogram';

    protected $fillable = [
        'metric_key',
        'metric_type',
        'value',
        'tags',
        'metadata',
        'source',
    ];

    protected $casts = [
        'value' => 'float',
        'tags' => 'array',
        'metadata' => 'array',
        'recorded_at' => 'datetime',
    ];

    public function scopeForMetric($query, string $key)
    {
        return $query->where('metric_key', $key);
    }

    public function scopeWithTag($query, string $key, string $value)
    {
        return $query->where('tags->' . $key, $value);
    }

    public function scopeFromSource($query, string $source)
    {
        return $query->where('source', $source);
    }

    public function scopeSince($query, $date)
    {
        return $query->where('recorded_at', '>=', $date);
    }
}
