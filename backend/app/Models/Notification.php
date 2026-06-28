<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory, HasUuids;

    public const TYPE_MESSAGE = 'message';

    public const TYPE_PROJECT_UPDATE = 'project_update';

    public const TYPE_TEAM_INVITE = 'team_invite';

    public const TYPE_SYSTEM = 'system';

    public const TYPE_AI_COMPLETE = 'ai_complete';

    public const TYPES = [
        self::TYPE_MESSAGE,
        self::TYPE_PROJECT_UPDATE,
        self::TYPE_TEAM_INVITE,
        self::TYPE_SYSTEM,
        self::TYPE_AI_COMPLETE,
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_NORMAL = 'normal';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'body',
        'data',
        'priority',
        'channel',
        'event',
        'read_at',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markAsRead(): void
    {
        if (!$this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }

    public function markAsDismissed(): void
    {
        $this->update(['dismissed_at' => now()]);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeRecent(Builder $query, int $limit = 20): Builder
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
