<?php

namespace App\Services\Uploads;

class ImageUploadSanitizer
{
    public function stripMetadata(string $absolutePath): bool
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath) || !is_writable($absolutePath)) {
            return false;
        }

        if ($this->stripWithImagick($absolutePath)) {
            return true;
        }

        return $this->stripWithGd($absolutePath);
    }

    private function stripWithImagick(string $absolutePath): bool
    {
        if (!extension_loaded('imagick')) {
            return false;
        }

        try {
            $image = new \Imagick($absolutePath);
            $image->stripImage();
            $image->writeImage($absolutePath);
            $image->clear();
            $image->destroy();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function stripWithGd(string $absolutePath): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        $imageInfo = @getimagesize($absolutePath);
        $imageType = $imageInfo[2] ?? null;

        if (!is_int($imageType)) {
            return false;
        }

        // Guard against fatal OOM on large/high-resolution images when GD decodes pixels.
        if (!$this->canSafelyDecodeWithGd(is_array($imageInfo) ? $imageInfo : [])) {
            return false;
        }

        return match ($imageType) {
            IMAGETYPE_JPEG => $this->rewriteJpeg($absolutePath),
            IMAGETYPE_PNG => $this->rewritePng($absolutePath),
            IMAGETYPE_WEBP => $this->rewriteWebp($absolutePath),
            default => false,
        };
    }

    private function rewriteJpeg(string $absolutePath): bool
    {
        if (!function_exists('imagecreatefromjpeg') || !function_exists('imagejpeg')) {
            return false;
        }

        $image = @imagecreatefromjpeg($absolutePath);
        if (!$image) {
            return false;
        }

        $written = @imagejpeg($image, $absolutePath, 90);
        imagedestroy($image);

        return $written;
    }

    private function rewritePng(string $absolutePath): bool
    {
        if (!function_exists('imagecreatefrompng') || !function_exists('imagepng')) {
            return false;
        }

        $image = @imagecreatefrompng($absolutePath);
        if (!$image) {
            return false;
        }

        $this->prepareAlphaChannel($image);

        $written = @imagepng($image, $absolutePath, 6);
        imagedestroy($image);

        return $written;
    }

    private function rewriteWebp(string $absolutePath): bool
    {
        if (!function_exists('imagecreatefromwebp') || !function_exists('imagewebp')) {
            return false;
        }

        $image = @imagecreatefromwebp($absolutePath);
        if (!$image) {
            return false;
        }

        $this->prepareAlphaChannel($image);

        $written = @imagewebp($image, $absolutePath, 90);
        imagedestroy($image);

        return $written;
    }

    /**
     * @param \GdImage|resource $image
     */
    private function prepareAlphaChannel($image): void
    {
        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }

        @imagealphablending($image, false);
        @imagesavealpha($image, true);
    }

    /**
     * @param array<int,mixed> $imageInfo
     */
    private function canSafelyDecodeWithGd(array $imageInfo): bool
    {
        $width = (int) ($imageInfo[0] ?? 0);
        $height = (int) ($imageInfo[1] ?? 0);
        if ($width <= 0 || $height <= 0) {
            return false;
        }

        $memoryLimit = $this->memoryLimitBytes();
        if ($memoryLimit === null) {
            return true;
        }

        $available = $memoryLimit - memory_get_usage(true);
        if ($available <= 0) {
            return false;
        }

        // Conservative estimate for decoded bitmap + GD internals.
        $required = ((float) $width * (float) $height * 8.0) + (32.0 * 1024.0 * 1024.0);

        return $required < ((float) $available * 0.9);
    }

    private function memoryLimitBytes(): ?int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return null;
        }

        $unit = strtolower(substr($raw, -1));
        $value = (float) $raw;

        if ($unit === 'g') {
            $value *= 1024;
            $unit = 'm';
        }
        if ($unit === 'm') {
            $value *= 1024;
            $unit = 'k';
        }
        if ($unit === 'k') {
            $value *= 1024;
        }

        $bytes = (int) round($value);
        return $bytes > 0 ? $bytes : null;
    }
}
