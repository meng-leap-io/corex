<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncQueue extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'sync_queue';

    protected $fillable = [
        'job_id',
        'table_name',
        'record_id',
        'user_id',
        'action',
        'data',
        'priority',
        'attempts',
        'max_attempts',
        'status',
        'error_message',
        'scheduled_at',
        'completed_at',
    ];

    protected $casts = [
        'data' => 'json',
        'priority' => 'integer',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

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

    public function scopeDead($query)
    {
        return $query->where('status', 'dead');
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTable($query, string $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', '>=', 5);
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('priority', 'desc')
            ->orderBy('created_at');
    }
}
