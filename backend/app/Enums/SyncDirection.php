<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the direction of data synchronization.
 */
enum SyncDirection: string
{
    case PUSH = 'push';
    case PULL = 'pull';
    case BIDIRECTIONAL = 'bidirectional';

    /**
     * Get the human-readable label for this sync direction.
     */
    public function label(): string
    {
        return match ($this) {
            self::PUSH => 'Push',
            self::PULL => 'Pull',
            self::BIDIRECTIONAL => 'Bidirectional',
        };
    }
}
