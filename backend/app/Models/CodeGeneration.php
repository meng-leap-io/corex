<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodeGeneration extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PENDING = 'pending';

    public const STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_PROCESSING,
        self::STATUS_PENDING,
    ];

    protected $fillable = [
        'user_id',
        'project_id',
        'prompt',
        'code_generated',
        'language',
        'model_used',
        'tokens_used',
        'cost',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tokens_used' => 'integer',
            'cost' => 'decimal:6',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CodeGeneration $generation) {
            if (empty($generation->status)) {
                $generation->status = self::STATUS_PENDING;
            }
            if (!isset($generation->tokens_used)) {
                $generation->tokens_used = 0;
            }
            if (!isset($generation->cost)) {
                $generation->cost = 0;
            }
        });

        static::created(function (CodeGeneration $generation) {
            if ($generation->user && $generation->tokens_used > 0) {
                $generation->user->incrementApiUsage($generation->tokens_used);
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

    public function getCodeLinesAttribute(): int
    {
        return substr_count($this->code_generated ?? '', "\n") + 1;
    }

    public function getTokenEfficiencyAttribute(): ?float
    {
        if ($this->tokens_used > 0) {
            $charCount = strlen($this->code_generated ?? '');
            return round($charCount / $this->tokens_used, 4);
        }
        return null;
    }

    public function getCostPerCodeLineAttribute(): ?float
    {
        $lines = $this->getCodeLinesAttribute();
        if ($lines > 0 && $this->cost > 0) {
            return round($this->cost / $lines, 8);
        }
        return null;
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    public function scopeByModel(Builder $query, string $model): Builder
    {
        return $query->where('model_used', $model);
    }

    public function scopeByProject(Builder $query, string $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeRecent(Builder $query, int $limit = 20): Builder
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }

    public function scopeWithHighCost(Builder $query, float $threshold = 0.01): Builder
    {
        return $query->where('cost', '>=', $threshold);
    }
}
