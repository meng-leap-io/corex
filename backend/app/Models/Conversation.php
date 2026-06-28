<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory, HasUuids;

    public const string PROVIDER_OPENAI = 'openai';

    public const string PROVIDER_ANTHROPIC = 'anthropic';

    public const string PROVIDER_GOOGLE = 'google';

    public const array PROVIDERS = [
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
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'tokens_used' => 'integer',
            'total_cost' => 'decimal:6',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Conversation $conversation) {
            if (! isset($conversation->tokens_used)) {
                $conversation->tokens_used = 0;
            }
            if (! isset($conversation->total_cost)) {
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

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('sequence');
    }

    public function getTokenCostRatioAttribute(): float
    {
        return $this->tokens_used > 0
            ? round($this->total_cost / $this->tokens_used, 8)
            : 0;
    }

    public function getMessageCountAttribute(): int
    {
        return $this->relationLoaded('messages')
            ? $this->messages->count()
            : $this->loadCount('messages')->messages_count;
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

    public function archive(): void
    {
        $this->update(['archived_at' => now()]);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function scopeWithoutArchived(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
