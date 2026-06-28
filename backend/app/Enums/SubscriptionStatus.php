<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the possible states of a subscription.
 */
enum SubscriptionStatus: string
{
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    case EXPIRED = 'expired';
    case TRIALING = 'trialing';
    case INCOMPLETE = 'incomplete';
    case INCOMPLETE_EXPIRED = 'incomplete_expired';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::PAST_DUE => 'Past Due',
            self::CANCELED => 'Canceled',
            self::EXPIRED => 'Expired',
            self::TRIALING => 'Trialing',
            self::INCOMPLETE => 'Incomplete',
            self::INCOMPLETE_EXPIRED => 'Incomplete Expired',
        };
    }

    /**
     * Determine if this status represents an active subscription.
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * Determine if this status represents a canceled subscription.
     */
    public function isCanceled(): bool
    {
        return $this === self::CANCELED;
    }
}
