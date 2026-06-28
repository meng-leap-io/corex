<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncSnapshot extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'sync_snapshots';

    protected $fillable = [
        'table_name',
        'record_id',
        'user_id',
        'data',
        'version',
        'reason',
        'restored_at',
    ];

    protected $casts = [
        'data' => 'json',
        'version' => 'integer',
        'restored_at' => 'datetime',
    ];

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

    public function scopeSnapshot($query, string $reason)
    {
        return $query->where('reason', $reason);
    }

    public function scopeRestored($query)
    {
        return $query->whereNotNull('restored_at');
    }
}
