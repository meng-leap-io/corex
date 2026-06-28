<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the roles a user can have within a team.
 */
enum TeamRole: string
{
    case MEMBER = 'member';
    case EDITOR = 'editor';
    case ADMIN = 'admin';
    case OWNER = 'owner';

    /**
     * Get the human-readable label for this role.
     */
    public function label(): string
    {
        return match ($this) {
            self::MEMBER => 'Member',
            self::EDITOR => 'Editor',
            self::ADMIN => 'Admin',
            self::OWNER => 'Owner',
        };
    }

    /**
     * Determine if this role can invite new members.
     */
    public function canInvite(): bool
    {
        return match ($this) {
            self::MEMBER => false,
            self::EDITOR => false,
            self::ADMIN => true,
            self::OWNER => true,
        };
    }

    /**
     * Determine if this role can remove team members.
     */
    public function canRemove(): bool
    {
        return match ($this) {
            self::MEMBER => false,
            self::EDITOR => false,
            self::ADMIN => true,
            self::OWNER => true,
        };
    }

    /**
     * Determine if this role can edit team projects.
     */
    public function canEditProjects(): bool
    {
        return match ($this) {
            self::MEMBER => false,
            self::EDITOR => true,
            self::ADMIN => true,
            self::OWNER => true,
        };
    }

    /**
     * Determine if this role can delete the team.
     */
    public function canDeleteTeam(): bool
    {
        return $this === self::OWNER;
    }
}
