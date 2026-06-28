<?php

namespace App\Models;

use App\Models\Scopes\RlsScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const string STATUS_DRAFT = 'draft';
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_ARCHIVED = 'archived';
    public const string STATUS_DELETED = 'deleted';

    public const array STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
        self::STATUS_DELETED,
    ];

    public const string VISIBILITY_PRIVATE = 'private';
    public const string VISIBILITY_TEAM = 'team';
    public const string VISIBILITY_PUBLIC = 'public';

    public const array VISIBILITIES = [
        self::VISIBILITY_PRIVATE,
        self::VISIBILITY_TEAM,
        self::VISIBILITY_PUBLIC,
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
        'visibility',
        'is_public',
        'last_accessed_at',
    ];

    protected function casts(): array
    {
        return [
            'files' => 'array',
            'structure' => 'array',
            'is_public' => 'boolean',
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
            $project->visibility ??= self::VISIBILITY_PRIVATE;
            $project->is_public ??= false;
        });

        static::addGlobalScope(new RlsScope());
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

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->using(ProjectUser::class)
            ->withPivot(['role', 'permissions', 'joined_at'])
            ->withTimestamps();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_team')
            ->withPivot('access_level');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function isAccessibleBy(User $user): bool
    {
        if ($this->isOwnedBy($user)) {
            return true;
        }

        if ($this->is_public) {
            return true;
        }

        if ($this->members()->where('user_id', $user->id)->exists()) {
            return true;
        }

        $teamIds = $user->teams()->pluck('teams.id');

        return $this->teams()->whereIn('team_id', $teamIds)->exists();
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->isAccessibleBy($user);
    }

    public function makePublic(): void
    {
        $this->update(['is_public' => true, 'visibility' => self::VISIBILITY_PUBLIC]);
    }

    public function makePrivate(): void
    {
        $this->update(['is_public' => false, 'visibility' => self::VISIBILITY_PRIVATE]);
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

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopePrivate(Builder $query): Builder
    {
        return $query->where('is_public', false);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhere('is_public', true)
              ->orWhereHas('members', function (Builder $mq) use ($user) {
                  $mq->where('user_id', $user->id);
              })
              ->orWhereHas('teams.members', function (Builder $tq) use ($user) {
                  $tq->where('user_id', $user->id);
              });
        });
    }
}
