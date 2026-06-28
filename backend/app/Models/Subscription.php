<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    public const PLAN_FREE = 'free';

    public const PLAN_PRO = 'pro';

    public const PLAN_TEAM = 'team';

    public const PLANS = [
        self::PLAN_FREE,
        self::PLAN_PRO,
        self::PLAN_TEAM,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_TRIALING,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
        self::STATUS_PAST_DUE,
    ];

    protected $fillable = [
        'user_id',
        'plan',
        'status',
        'stripe_id',
        'stripe_status',
        'stripe_price',
        'quantity',
        'trial_ends_at',
        'ends_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription) {
            if (empty($subscription->plan)) {
                $subscription->plan = self::PLAN_FREE;
            }
            if (empty($subscription->status)) {
                $subscription->status = self::STATUS_ACTIVE;
            }
            if (! isset($subscription->quantity)) {
                $subscription->quantity = 1;
            }
        });

        static::created(function (Subscription $subscription) {
            $subscription->user->update([
                'plan' => $subscription->plan,
                'plan_expires_at' => $subscription->ends_at,
                'api_usage_limit' => User::PLAN_API_LIMITS[$subscription->plan] ?? User::PLAN_API_LIMITS[User::PLAN_FREE],
            ]);
        });

        static::updated(function (Subscription $subscription) {
            if ($subscription->wasChanged('plan')) {
                $subscription->user->update([
                    'plan' => $subscription->plan,
                    'api_usage_limit' => User::PLAN_API_LIMITS[$subscription->plan] ?? User::PLAN_API_LIMITS[User::PLAN_FREE],
                ]);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && (! $this->ends_at || $this->ends_at->isFuture());
    }

    public function isOnTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isCancelled(): bool
    {
        return ! is_null($this->cancelled_at);
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED || ($this->ends_at && $this->ends_at->isPast());
    }

    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function resume(): void
    {
        if ($this->isCancelled()) {
            $this->update([
                'status' => self::STATUS_ACTIVE,
                'cancelled_at' => null,
                'ends_at' => null,
            ]);
        }
    }

    public function markExpired(): void
    {
        $this->update(['status' => self::STATUS_EXPIRED]);

        $this->user->update([
            'plan' => User::PLAN_FREE,
            'plan_expires_at' => null,
            'api_usage_limit' => User::PLAN_API_LIMITS[User::PLAN_FREE],
        ]);
    }

    public function daysRemaining(): ?int
    {
        if ($this->ends_at) {
            return max(0, now()->diffInDays($this->ends_at, false));
        }

        return null;
    }

    public function trialDaysRemaining(): ?int
    {
        if ($this->trial_ends_at) {
            return max(0, now()->diffInDays($this->trial_ends_at, false));
        }

        return null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOnPlan(Builder $query, string $plan): Builder
    {
        return $query->where('plan', $plan);
    }

    public function scopeTrialing(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TRIALING);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->whereNotNull('cancelled_at');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('status', self::STATUS_EXPIRED)
                ->orWhere('ends_at', '<=', now());
        });
    }

    public function scopePastDue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAST_DUE);
    }

    public function scopeNeedsAttention(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PAST_DUE, self::STATUS_EXPIRED]);
    }
}
