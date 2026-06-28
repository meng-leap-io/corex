<?php

declare(strict_types=1);

namespace App\Services\Supabase\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class ImageOptimizerService
{
    private const SUPPORTED_FORMATS = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private const OUTPUT_FORMAT = 'webp';

    private const QUALITY = 85;

    public function optimize(UploadedFile $file, array $options = []): ?string
    {
        if (! extension_loaded('gd')) {
            Log::warning('image.gd_not_loaded', ['message' => 'GD extension required for image optimization']);

            return null;
        }

        $mimeType = $file->getMimeType();

        if (! $this->isSupported($mimeType)) {
            return null;
        }

        $image = $this->createImageFromFile($file->getPathname(), $mimeType);

        if ($image === null) {
            return null;
        }

        $image = $this->resize($image, $options);

        if (in_array('crop', $options['transforms'] ?? [])) {
            $image = $this->crop($image, $options);
        }

        $outputPath = tempnam(sys_get_temp_dir(), 'img_opt_').'.webp';

        imagewebp($image, $outputPath, $options['quality'] ?? self::QUALITY);

        imagedestroy($image);

        return $outputPath;
    }

    public function optimizeFromPath(string $sourcePath, string $outputPath, array $options = []): bool
    {
        if (! extension_loaded('gd')) {
            Log::warning('image.gd_not_loaded');

            return false;
        }

        $mimeType = mime_content_type($sourcePath);

        if (! $this->isSupported($mimeType)) {
            return false;
        }

        $image = $this->createImageFromFile($sourcePath, $mimeType);

        if ($image === null) {
            return false;
        }

        $image = $this->resize($image, $options);

        $saved = imagewebp($image, $outputPath, $options['quality'] ?? self::QUALITY);

        imagedestroy($image);

        return $saved;
    }

    public function createAvatar(string $sourcePath, int $size = 256): ?string
    {
        return $this->optimizeFromPath($sourcePath, tempnam(sys_get_temp_dir(), 'avatar_').'.webp', [
            'max_width' => $size,
            'max_height' => $size,
            'quality' => 90,
            'transforms' => ['crop'],
        ]) ? tempnam(sys_get_temp_dir(), 'avatar_').'.webp' : null;
    }

    private function createImageFromFile(string $path, string $mimeType): ?\GdImage
    {
        return match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => null,
        };
    }

    private function isSupported(?string $mimeType): bool
    {
        return $mimeType !== null && in_array($mimeType, self::SUPPORTED_FORMATS, true);
    }

    private function resize(\GdImage $image, array $options): \GdImage
    {
        $maxWidth = $options['max_width'] ?? null;
        $maxHeight = $options['max_height'] ?? null;

        if ($maxWidth === null && $maxHeight === null) {
            return $image;
        }

        $origWidth = imagesx($image);
        $origHeight = imagesy($image);
        $ratio = $origWidth / $origHeight;

        $newWidth = $origWidth;
        $newHeight = $origHeight;

        if ($maxWidth !== null && $newWidth > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) round($newWidth / $ratio);
        }

        if ($maxHeight !== null && $newHeight > $maxHeight) {
            $newHeight = $maxHeight;
            $newWidth = (int) round($newHeight * $ratio);
        }

        if ($newWidth === $origWidth && $newHeight === $origHeight) {
            return $image;
        }

        $resized = imagecreatetruecolor($newWidth, $newHeight);

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        imagedestroy($image);

        return $resized;
    }

    private function crop(\GdImage $image, array $options): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if ($width === $height) {
            return $image;
        }

        $size = min($width, $height);
        $x = (int) (($width - $size) / 2);
        $y = (int) (($height - $size) / 2);

        $cropped = imagecreatetruecolor($size, $size);

        imagecopy($cropped, $image, 0, 0, $x, $y, $size, $size);

        imagedestroy($image);

        return $cropped;
    }
}
