<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the roles a user can have within the application.
 */
enum UserRole: string
{
    case USER = 'user';
    case ADMIN = 'admin';
    case MODERATOR = 'moderator';
    case ANONYMOUS = 'anonymous';

    /**
     * Get the human-readable label for this role.
     */
    public function label(): string
    {
        return match ($this) {
            self::USER => 'User',
            self::ADMIN => 'Admin',
            self::MODERATOR => 'Moderator',
            self::ANONYMOUS => 'Anonymous',
        };
    }

    /**
     * Determine if this role has administrator privileges.
     */
    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    /**
     * Determine if this role has moderator privileges.
     */
    public function isModerator(): bool
    {
        return $this === self::MODERATOR;
    }
}
