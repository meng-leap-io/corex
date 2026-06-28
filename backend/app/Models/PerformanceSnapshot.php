<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceSnapshot extends Model
{
    use HasFactory, HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'cpu_load',
        'memory_used_mb',
        'memory_total_mb',
        'disk_used_mb',
        'disk_total_mb',
        'network_in_mb',
        'network_out_mb',
        'active_connections',
        'queue_size',
        'request_rate_per_min',
        'avg_response_time_ms',
        'p95_response_time_ms',
        'p99_response_time_ms',
        'error_count_5m',
        'services',
        'extra',
    ];

    protected $casts = [
        'cpu_load' => 'float',
        'memory_used_mb' => 'float',
        'memory_total_mb' => 'float',
        'disk_used_mb' => 'float',
        'disk_total_mb' => 'float',
        'network_in_mb' => 'float',
        'network_out_mb' => 'float',
        'active_connections' => 'integer',
        'queue_size' => 'integer',
        'request_rate_per_min' => 'float',
        'avg_response_time_ms' => 'float',
        'p95_response_time_ms' => 'float',
        'p99_response_time_ms' => 'float',
        'error_count_5m' => 'integer',
        'services' => 'array',
        'extra' => 'array',
        'recorded_at' => 'datetime',
    ];
}
