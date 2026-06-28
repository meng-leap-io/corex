<?php

declare(strict_types=1);

namespace App\Services\Supabase\Storage;

use Illuminate\Http\UploadedFile;

class FileValidationService
{
    private array $bucketRules;

    public function __construct()
    {
        $this->bucketRules = config('supabase.storage.buckets', []);
    }

    public function validateUpload(UploadedFile $file, string $bucket): array
    {
        $errors = [];

        $rules = $this->bucketRules[$bucket] ?? $this->bucketRules['*'] ?? [];

        $maxSize = $rules['max_size'] ?? 10 * 1024 * 1024;
        $allowedMimes = $rules['mime_types'] ?? [];
        $allowedExtensions = $rules['extensions'] ?? [];
        $minSize = $rules['min_size'] ?? 0;
        $maxWidth = $rules['max_width'] ?? null;
        $maxHeight = $rules['max_height'] ?? null;

        if ($file->getSize() > $maxSize) {
            $errors[] = "File exceeds maximum size of " . ($maxSize / 1024 / 1024) . "MB.";
        }

        if ($file->getSize() < $minSize) {
            $errors[] = "File is below minimum size of " . $minSize . " bytes.";
        }

        if (!empty($allowedMimes) && !in_array($file->getMimeType(), $allowedMimes, true)) {
            $errors[] = "File type '{$file->getMimeType()}' is not allowed. Allowed: " . implode(', ', $allowedMimes);
        }

        if (!empty($allowedExtensions)) {
            $ext = strtolower($file->getClientOriginalExtension());
            if (!in_array($ext, $allowedExtensions, true)) {
                $errors[] = "File extension '.{$ext}' is not allowed. Allowed: " . implode(', ', $allowedExtensions);
            }
        }

        if ($maxWidth !== null || $maxHeight !== null) {
            $imageErrors = $this->validateImageDimensions($file, $maxWidth, $maxHeight);
            $errors = array_merge($errors, $imageErrors);
        }

        return $errors;
    }

    public function validateImageDimensions(UploadedFile $file, ?int $maxWidth, ?int $maxHeight): array
    {
        $errors = [];

        if (!str_starts_with($file->getMimeType() ?? '', 'image/')) {
            return $errors;
        }

        $dimensions = @getimagesize($file->getPathname());

        if ($dimensions === false) {
            return ['Could not read image dimensions.'];
        }

        [$width, $height] = $dimensions;

        if ($maxWidth !== null && $width > $maxWidth) {
            $errors[] = "Image width {$width}px exceeds maximum of {$maxWidth}px.";
        }

        if ($maxHeight !== null && $height > $maxHeight) {
            $errors[] = "Image height {$height}px exceeds maximum of {$maxHeight}px.";
        }

        return $errors;
    }

    public function isValidPath(string $path): bool
    {
        if (trim($path) === '') {
            return false;
        }

        if (preg_match('/[<>:"|?*\\\\]/', $path)) {
            return false;
        }

        if (preg_match('/\.\.\//', $path) || str_starts_with($path, '/')) {
            return false;
        }

        return true;
    }

    public function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[<>:"\/\\\\|?*]/', '_', $name);

        $name = preg_replace('/\s+/', '_', $name);

        $name = preg_replace('/_{2,}/', '_', $name);

        $name = trim($name, '._');

        if (empty($name)) {
            $name = 'untitled';
        }

        return $name;
    }

    public function getAllowedMimesForBucket(string $bucket): array
    {
        return $this->bucketRules[$bucket]['mime_types']
            ?? $this->bucketRules['*']['mime_types']
            ?? [];
    }

    public function getMaxSizeForBucket(string $bucket): int
    {
        return $this->bucketRules[$bucket]['max_size']
            ?? $this->bucketRules['*']['max_size']
            ?? 10 * 1024 * 1024;
    }

    public function isImage(UploadedFile $file): bool
    {
        return str_starts_with($file->getMimeType() ?? '', 'image/');
    }
}
