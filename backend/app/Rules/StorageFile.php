<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class StorageFile implements ValidationRule
{
    private string $bucket;

    private ?int $maxSize;

    private ?array $allowedMimes;

    private ?array $allowedExtensions;

    private ?int $maxWidth;

    private ?int $maxHeight;

    public function __construct(
        string $bucket = 'projects',
        ?int $maxSize = null,
        ?array $allowedMimes = null,
        ?array $allowedExtensions = null,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
    ) {
        $this->bucket = $bucket;
        $this->maxSize = $maxSize;
        $this->allowedMimes = $allowedMimes;
        $this->allowedExtensions = $allowedExtensions;
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('The :attribute must be a valid uploaded file.');

            return;
        }

        $config = config("supabase.storage.buckets.{$this->bucket}", config('supabase.storage.buckets.*', []));

        $maxSize = $this->maxSize ?? ($config['max_size'] ?? 10 * 1024 * 1024);
        $allowedMimes = $this->allowedMimes ?? ($config['mime_types'] ?? []);
        $allowedExtensions = $this->allowedExtensions ?? ($config['extensions'] ?? []);

        if ($value->getSize() > $maxSize) {
            $fail('The :attribute exceeds the maximum file size of '.($maxSize / 1024 / 1024).' MB.');
        }

        if (! empty($allowedMimes) && ! in_array($value->getMimeType(), $allowedMimes, true)) {
            $fail("The :attribute type '{$value->getMimeType()}' is not allowed.");
        }

        if (! empty($allowedExtensions)) {
            $ext = strtolower($value->getClientOriginalExtension());

            if (! in_array($ext, $allowedExtensions, true)) {
                $fail("The :attribute extension '.{$ext}' is not allowed.");
            }
        }

        if ($this->maxWidth !== null || $this->maxHeight !== null) {
            $this->validateImageDimensions($value, $fail);
        }
    }

    private function validateImageDimensions(UploadedFile $file, Closure $fail): void
    {
        if (! str_starts_with($file->getMimeType() ?? '', 'image/')) {
            return;
        }

        $dimensions = @getimagesize($file->getPathname());

        if ($dimensions === false) {
            $fail('Could not read image dimensions.');

            return;
        }

        [$width, $height] = $dimensions;

        if ($this->maxWidth !== null && $width > $this->maxWidth) {
            $fail("Image width {$width}px exceeds the maximum of {$this->maxWidth}px.");
        }

        if ($this->maxHeight !== null && $height > $this->maxHeight) {
            $fail("Image height {$height}px exceeds the maximum of {$this->maxHeight}px.");
        }
    }
}
