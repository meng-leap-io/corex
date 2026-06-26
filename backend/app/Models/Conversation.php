<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conversation extends Model
{
    use HasFactory, HasUuids;

    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_ANTHROPIC = 'anthropic';
    public const PROVIDER_GOOGLE = 'google';

    public const PROVIDERS = [
        self::PROVIDER_OPENAI,
        self::PROVIDER_ANTHROPIC,
        self::PROVIDER_GOOGLE,
    ];

    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'model_used',
        'messages',
        'tokens_used',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'tokens_used' => 'integer',
            'total_cost' => 'decimal:6',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            if (empty($conversation->messages)) {
                $conversation->messages = [];
            }
            if (!isset($conversation->tokens_used)) {
                $conversation->tokens_used = 0;
            }
            if (!isset($conversation->total_cost)) {
                $conversation->total_cost = 0;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function getMessageCountAttribute(): int
    {
        return count($this->messages ?? []);
    }

    public function getUserMessageCountAttribute(): int
    {
        return count(array_filter($this->messages ?? [], fn(array $msg) => ($msg['role'] ?? '') === 'user'));
    }

    public function getAssistantMessageCountAttribute(): int
    {
        return count(array_filter($this->messages ?? [], fn(array $msg) => ($msg['role'] ?? '') === 'assistant'));
    }

    public function getTokenCostRatioAttribute(): float
    {
        return $this->tokens_used > 0
            ? round($this->total_cost / $this->tokens_used, 8)
            : 0;
    }

    public function getLastMessageAttribute(): ?array
    {
        $messages = $this->messages ?? [];
        return !empty($messages) ? end($messages) : null;
    }

    public function appendMessage(string $role, string $content, int $tokens = 0, float $cost = 0): void
    {
        $messages = $this->messages ?? [];
        $messages[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->update([
            'messages' => $messages,
            'tokens_used' => $this->tokens_used + $tokens,
            'total_cost' => $this->total_cost + $cost,
        ]);
    }

    public function scopeByModel(Builder $query, string $model): Builder
    {
        return $query->where('model_used', $model);
    }

    public function scopeByProject(Builder $query, string $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeRecent(Builder $query, int $limit = 10): Builder
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    public function scopeWithHighTokens(Builder $query, int $threshold = 1000): Builder
    {
        return $query->where('tokens_used', '>=', $threshold);
    }
}
