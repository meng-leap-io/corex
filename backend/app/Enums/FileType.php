<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Represents the categories of files that can be uploaded.
 */
enum FileType: string
{
    case IMAGE = 'image';
    case DOCUMENT = 'document';
    case CODE = 'code';
    case ARCHIVE = 'archive';
    case OTHER = 'other';

    /**
     * Get the human-readable label for this file type.
     */
    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Image',
            self::DOCUMENT => 'Document',
            self::CODE => 'Code',
            self::ARCHIVE => 'Archive',
            self::OTHER => 'Other',
        };
    }

    /**
     * Get the list of associated MIME types for this file type.
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return match ($this) {
            self::IMAGE => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
            self::DOCUMENT => ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'text/csv'],
            self::CODE => ['text/plain', 'text/x-php', 'application/json', 'text/javascript', 'text/x-python', 'text/x-java'],
            self::ARCHIVE => ['application/zip', 'application/gzip', 'application/x-tar', 'application/x-7z-compressed'],
            self::OTHER => ['application/octet-stream'],
        };
    }

    /**
     * Get the maximum allowed file size in bytes for this file type.
     */
    public function maxSize(): int
    {
        return match ($this) {
            self::IMAGE => 10 * 1_024 * 1_024,       // 10 MB
            self::DOCUMENT => 50 * 1_024 * 1_024,     // 50 MB
            self::CODE => 5 * 1_024 * 1_024,           // 5 MB
            self::ARCHIVE => 100 * 1_024 * 1_024,      // 100 MB
            self::OTHER => 25 * 1_024 * 1_024,         // 25 MB
        };
    }
}
