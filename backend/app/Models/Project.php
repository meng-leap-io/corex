<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_DELETED = 'deleted';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
        self::STATUS_DELETED,
    ];

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'slug',
        'language',
        'framework',
        'files',
        'structure',
        'status',
        'last_accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'structure' => 'array',
            'last_accessed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Project $project) {
            if (empty($project->slug)) {
                $project->slug = Str::slug($project->name) . '-' . Str::random(6);
            }
            if (empty($project->status)) {
                $project->status = self::STATUS_DRAFT;
            }
            if (empty($project->files)) {
                $project->files = [];
            }
            if (empty($project->structure)) {
                $project->structure = [];
            }
        });

        static::created(function (Project $project) {
            // project_created hook
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function codeGenerations(): HasMany
    {
        return $this->hasMany(CodeGeneration::class)
            ->where('status', CodeGeneration::STATUS_COMPLETED);
    }

    public function touchLastAccessed(): void
    {
        $this->update(['last_accessed_at' => now()]);
    }

    public function markAsActive(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    public function markAsArchived(): void
    {
        $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    public function getFileCountAttribute(): int
    {
        return count($this->files ?? []);
    }

    public function getLanguageLabelAttribute(): string
    {
        $labels = [
            'php' => 'PHP',
            'javascript' => 'JavaScript',
            'typescript' => 'TypeScript',
            'python' => 'Python',
            'go' => 'Go',
            'rust' => 'Rust',
        ];

        return $labels[strtolower($this->language ?? '')] ?? $this->language ?? 'Unknown';
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }

    public function scopeRecentlyAccessed(Builder $query, int $days = 30): Builder
    {
        return $query->where('last_accessed_at', '>=', now()->subDays($days));
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
              ->orWhere('description', 'ilike', "%{$term}%")
              ->orWhere('language', 'ilike', "%{$term}%");
        });
    }
}
