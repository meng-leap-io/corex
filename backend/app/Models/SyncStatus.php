<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncStatus extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'sync_status';

    protected $fillable = [
        'table_name',
        'record_id',
        'user_id',
        'action',
        'status',
        'version',
        'error_message',
        'synced_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'synced_at' => 'datetime',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSynced($query)
    {
        return $query->where('status', 'synced');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTable($query, string $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    public function scopeForRecord($query, string $tableName, string $recordId)
    {
        return $query->where('table_name', $tableName)->where('record_id', $recordId);
    }
}
