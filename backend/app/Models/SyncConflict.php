<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncConflict extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'sync_conflicts';

    protected $fillable = [
        'table_name',
        'record_id',
        'user_id',
        'local_version',
        'remote_version',
        'local_data',
        'remote_data',
        'diff',
        'resolution_data',
        'reason',
        'strategy',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'local_version' => 'integer',
        'remote_version' => 'integer',
        'local_data' => 'json',
        'remote_data' => 'json',
        'diff' => 'json',
        'resolution_data' => 'json',
        'resolved_at' => 'datetime',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForTable($query, string $tableName)
    {
        return $query->where('table_name', $tableName);
    }

    public function scopeByStrategy($query, string $strategy)
    {
        return $query->where('strategy', $strategy);
    }
}
