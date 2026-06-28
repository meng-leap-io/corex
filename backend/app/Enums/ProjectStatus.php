<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the lifecycle status of a project.
 */
enum ProjectStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
    case DELETED = 'deleted';

    /**
     * Get the human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::ARCHIVED => 'Archived',
            self::DELETED => 'Deleted',
        };
    }

    /**
     * Determine if this status represents an active project.
     */
    public function isActive(): bool
    {
        return $this === self::ACTIVE;
    }
}
