<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the available subscription plans for users.
 */
enum UserPlan: string
{
    case FREE = 'free';
    case PRO = 'pro';
    case TEAM = 'team';
    case ENTERPRISE = 'enterprise';

    /**
     * Get the human-readable label for this plan.
     */
    public function label(): string
    {
        return match ($this) {
            self::FREE => 'Free',
            self::PRO => 'Pro',
            self::TEAM => 'Team',
            self::ENTERPRISE => 'Enterprise',
        };
    }

    /**
     * Get the monthly price in USD for this plan.
     */
    public function monthlyPrice(): int
    {
        return match ($this) {
            self::FREE => 0,
            self::PRO => 29,
            self::TEAM => 99,
            self::ENTERPRISE => 299,
        };
    }

    /**
     * Get the maximum number of projects allowed for this plan.
     */
    public function maxProjects(): int
    {
        return match ($this) {
            self::FREE => 3,
            self::PRO => 10,
            self::TEAM => 50,
            self::ENTERPRISE => -1, // Unlimited
        };
    }

    /**
     * Get the maximum number of team members allowed for this plan.
     */
    public function maxTeamMembers(): int
    {
        return match ($this) {
            self::FREE => 0,
            self::PRO => 0,
            self::TEAM => 10,
            self::ENTERPRISE => -1, // Unlimited
        };
    }

    /**
     * Get the maximum storage in megabytes for this plan.
     */
    public function maxStorageMb(): int
    {
        return match ($this) {
            self::FREE => 100,
            self::PRO => 1_024,
            self::TEAM => 10_240,
            self::ENTERPRISE => 102_400,
        };
    }

    /**
     * Determine if this plan includes advanced analytics features.
     */
    public function includesAdvancedAnalytics(): bool
    {
        return match ($this) {
            self::FREE => false,
            self::PRO => false,
            self::TEAM => true,
            self::ENTERPRISE => true,
        };
    }

    /**
     * Determine if this plan includes API access.
     */
    public function includesApiAccess(): bool
    {
        return match ($this) {
            self::FREE => false,
            self::PRO => true,
            self::TEAM => true,
            self::ENTERPRISE => true,
        };
    }
}
