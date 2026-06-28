<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasFactory, HasUuids;

    const UPDATED_AT = null;

    public const string ROLE_USER = 'user';
    public const string ROLE_ASSISTANT = 'assistant';
    public const string ROLE_SYSTEM = 'system';
    public const string ROLE_TOOL = 'tool';

    public const array ROLES = [
        self::ROLE_USER,
        self::ROLE_ASSISTANT,
        self::ROLE_SYSTEM,
        self::ROLE_TOOL,
    ];

    protected $fillable = [
        'conversation_id',
        'user_id',
        'role',
        'content',
        'model_used',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'cost',
        'metadata',
        'sequence',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'total_tokens' => 'integer',
        'cost' => 'decimal:6',
        'metadata' => 'array',
        'sequence' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Message $message) {
            $message->total_tokens = $message->prompt_tokens + $message->completion_tokens;
            if ($message->sequence === null) {
                $lastSeq = static::where('conversation_id', $message->conversation_id)
                    ->max('sequence');
                $message->sequence = ($lastSeq ?? -1) + 1;
            }
        });

        static::created(function (Message $message) {
            $conversation = $message->conversation;
            if ($conversation) {
                $conversation->increment('tokens_used', $message->total_tokens);
                $conversation->increment('total_cost', $message->cost);
                $conversation->touch();
            }
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeInConversation($query, string $conversationId)
    {
        return $query->where('conversation_id', $conversationId)->orderBy('sequence');
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeByModel($query, string $model)
    {
        return $query->where('model_used', $model);
    }

    public function scopeSince($query, $date)
    {
        return $query->where('created_at', '>=', $date);
    }
}
