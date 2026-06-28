<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookEvent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'log_id',
        'event_type',
        'status',
        'data',
        'result',
        'error_message',
        'attempts',
        'processed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'result' => 'array',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];

    public function log(): BelongsTo
    {
        return $this->belongsTo(WebhookLog::class, 'log_id');
    }
}
