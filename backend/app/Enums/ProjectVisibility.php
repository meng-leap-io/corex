<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the visibility level of a project.
 */
enum ProjectVisibility: string
{
    case PRIVATE = 'private';
    case TEAM = 'team';
    case PUBLIC = 'public';

    /**
     * Get the human-readable label for this visibility level.
     */
    public function label(): string
    {
        return match ($this) {
            self::PRIVATE => 'Private',
            self::TEAM => 'Team',
            self::PUBLIC => 'Public',
        };
    }

    /**
     * Determine if this visibility level is publicly accessible.
     */
    public function isPublic(): bool
    {
        return $this === self::PUBLIC;
    }

    /**
     * Determine if this visibility level requires authentication.
     */
    public function requiresAuth(): bool
    {
        return $this !== self::PUBLIC;
    }
}
