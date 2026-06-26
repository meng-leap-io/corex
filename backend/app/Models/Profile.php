<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasFactory, HasUuids;

    public const NOTIFICATION_DIGEST_DAILY = 'daily';
    public const NOTIFICATION_DIGEST_WEEKLY = 'weekly';
    public const NOTIFICATION_DIGEST_MONTHLY = 'monthly';
    public const NOTIFICATION_DIGEST_NONE = 'none';

    public const NOTIFICATION_DIGESTS = [
        self::NOTIFICATION_DIGEST_DAILY,
        self::NOTIFICATION_DIGEST_WEEKLY,
        self::NOTIFICATION_DIGEST_MONTHLY,
        self::NOTIFICATION_DIGEST_NONE,
    ];

    protected $fillable = [
        'user_id',
        'bio',
        'company',
        'website',
        'location',
        'twitter',
        'github',
        'expertise',
        'skills',
        'public_email',
        'notification_settings',
    ];

    protected function casts(): array
    {
        return [
            'expertise' => 'array',
            'skills' => 'array',
            'notification_settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Profile $profile) {
            if (empty($profile->notification_settings)) {
                $profile->notification_settings = [
                    'email' => true,
                    'push' => true,
                    'digest' => self::NOTIFICATION_DIGEST_WEEKLY,
                    'marketing' => false,
                ];
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullLocationAttribute(): ?string
    {
        return $this->location;
    }

    public function getSocialLinksAttribute(): array
    {
        return array_filter([
            'twitter' => $this->twitter,
            'github' => $this->github,
            'website' => $this->website,
        ]);
    }

    public function getExpertiseListAttribute(): string
    {
        return implode(', ', $this->expertise ?? []);
    }

    public function getSkillsListAttribute(): string
    {
        return implode(', ', $this->skills ?? []);
    }

    public function hasExpertise(string $area): bool
    {
        return in_array($area, $this->expertise ?? []);
    }

    public function hasSkill(string $skill): bool
    {
        return in_array($skill, $this->skills ?? []);
    }

    public function scopeByLocation(Builder $query, string $location): Builder
    {
        return $query->where('location', 'ilike', "%{$location}%");
    }

    public function scopeWithSkills(Builder $query, array $skills): Builder
    {
        foreach ($skills as $skill) {
            $query->whereJsonContains('skills', $skill);
        }
        return $query;
    }

    public function scopeWithExpertise(Builder $query, array $expertise): Builder
    {
        foreach ($expertise as $area) {
            $query->whereJsonContains('expertise', $area);
        }
        return $query;
    }

    public function scopePublicProfiles(Builder $query): Builder
    {
        return $query->whereNotNull('public_email');
    }
}
