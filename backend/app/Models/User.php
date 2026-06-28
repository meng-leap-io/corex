<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\RlsScope;
use App\Traits\HasEncryptedAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\Syncable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes, Syncable;

    public const string ROLE_USER = 'user';
    public const string ROLE_ADMIN = 'admin';
    public const string ROLE_MODERATOR = 'moderator';

    public const array ROLES = [
        self::ROLE_USER,
        self::ROLE_ADMIN,
        self::ROLE_MODERATOR,
    ];

    public const string PLAN_FREE = 'free';
    public const string PLAN_PRO = 'pro';
    public const string PLAN_TEAM = 'team';

    public const array PLANS = [
        self::PLAN_FREE,
        self::PLAN_PRO,
        self::PLAN_TEAM,
    ];

    public const array PLAN_API_LIMITS = [
        self::PLAN_FREE => 1000,
        self::PLAN_PRO => 10000,
        self::PLAN_TEAM => 50000,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'supabase_id',
        'github_id',
        'google_id',
        'provider',
        'provider_id',
        'plan',
        'role',
        'plan_expires_at',
        'api_usage_limit',
        'api_usage_current',
        'settings',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'plan_expires_at' => 'datetime',
            'api_usage_limit' => 'integer',
            'api_usage_current' => 'integer',
            'role' => 'string',
            'settings' => 'array',
            'preferences' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->role ??= self::ROLE_USER;
            if (!isset($user->attributes['api_usage_limit'])) {
                $user->api_usage_limit = self::PLAN_API_LIMITS[$user->plan ?? self::PLAN_FREE];
            }
            if (!isset($user->attributes['api_usage_current'])) {
                $user->api_usage_current = 0;
            }
        });

        static::created(function (User $user) {
            if (!$user->profile()->exists()) {
                $user->profile()->create(['user_id' => $user->id]);
            }
        });

        static::addGlobalScope(new RlsScope());
    }

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function codeGenerations(): HasMany
    {
        return $this->hasMany(CodeGeneration::class);
    }

    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(File::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_user')
            ->using(TeamUser::class)
            ->withPivot(['role', 'permissions', 'joined_at'])
            ->withTimestamps();
    }

    public function ownedTeams(): HasMany
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    public function sharedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_user')
            ->using(ProjectUser::class)
            ->withPivot(['role', 'permissions', 'joined_at'])
            ->withTimestamps();
    }

    public function allProjects(): Builder
    {
        $projectIds = $this->sharedProjects()->pluck('project_user.project_id');

        return Project::where(function (Builder $q) use ($projectIds) {
            $q->where('user_id', $this->id)
              ->orWhereIn('id', $projectIds);
        });
    }

    public function teamProjects(): Builder
    {
        $teamIds = $this->teams()->pluck('teams.id');

        $projectIdsFromTeams = \DB::table('project_team')
            ->whereIn('team_id', $teamIds)
            ->pluck('project_id');

        return Project::where(function (Builder $q) use ($projectIdsFromTeams) {
            $q->whereIn('id', $projectIdsFromTeams);
        });
    }

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::needsRehash($value) ? Hash::make($value) : $value;
    }

    public function getNameAttribute(?string $value): ?string
    {
        return $value ? ucwords(strtolower($value)) : null;
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->role === self::ROLE_ADMIN
            || $this->email === config('app.admin_email');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ?? $this->gravatarUrl();
    }

    public function gravatarUrl(int $size = 200): string
    {
        $hash = md5(strtolower(trim($this->email)));
        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=mp";
    }

    public function isVerified(): bool
    {
        return !is_null($this->email_verified_at);
    }

    public function markAsVerified(): void
    {
        $this->update(['email_verified_at' => now()]);
    }

    public function isOnPlan(string $plan): bool
    {
        return $this->plan === $plan;
    }

    public function hasApiCapacity(int $estimatedTokens = 1): bool
    {
        return $this->api_usage_current + $estimatedTokens <= $this->api_usage_limit;
    }

    public function incrementApiUsage(int $tokens = 1): void
    {
        $this->increment('api_usage_current', $tokens);
    }

    public function resetApiUsage(): void
    {
        $this->update(['api_usage_current' => 0]);
    }

    public function canAccessFeature(string $feature): bool
    {
        return match ($this->plan) {
            self::PLAN_TEAM => in_array($feature, ['advanced_analytics', 'team_members', 'priority_support', 'unlimited_projects', 'api_access']),
            self::PLAN_PRO => in_array($feature, ['advanced_analytics', 'unlimited_projects', 'api_access']),
            self::PLAN_FREE => in_array($feature, ['basic_projects']),
            default => false,
        };
    }

    public static function findByEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeUnverified(Builder $query): Builder
    {
        return $query->whereNull('email_verified_at');
    }

    public function scopeOnPlan(Builder $query, string $plan): Builder
    {
        return $query->where('plan', $plan);
    }

    public function scopeRecentlyActive(Builder $query, int $days = 7): Builder
    {
        return $query->whereHas('aiUsageLogs', function (Builder $q) use ($days) {
            $q->where('created_at', '>=', now()->subDays($days));
        });
    }

    public function scopeWithApiCapacity(Builder $query): Builder
    {
        return $query->whereColumn('api_usage_current', '<', 'api_usage_limit');
    }

    public function scopeAdmin(Builder $query): Builder
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeWithoutRole(Builder $query): Builder
    {
        return $query->whereNull('role')->orWhere('role', self::ROLE_USER);
    }
}
