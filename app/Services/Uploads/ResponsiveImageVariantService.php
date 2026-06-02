<?php

namespace App\Services\Uploads;

use App\Jobs\GenerateResponsiveMediaVariantsJob;
use Illuminate\Support\Facades\Storage;

class ResponsiveImageVariantService
{
    public function dispatchGeneration(string $diskName, string $key): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        if ($this->isVariantKey($key)) {
            return;
        }

        $queueName = trim((string) config('media.responsive_variants_queue', 'media'));
        $dispatch = GenerateResponsiveMediaVariantsJob::dispatch($diskName, $key)->afterResponse();
        if ($queueName !== '') {
            $dispatch->onQueue($queueName);
        }
    }

    /**
     * @return array<int,string> Generated variant keys
     */
    public function generateForKey(string $diskName, string $key): array
    {
        if (!$this->isEnabled() || $this->isVariantKey($key)) {
            return [];
        }

        $disk = Storage::disk($diskName);
        $normalizedKey = ltrim(trim($key), '/');
        if ($normalizedKey === '' || !$disk->exists($normalizedKey)) {
            return [];
        }

        $binary = $disk->get($normalizedKey);
        if (!is_string($binary) || $binary === '') {
            return [];
        }

        if (!extension_loaded('gd') || !function_exists('imagewebp') || !function_exists('imagecreatefromstring')) {
            return [];
        }

        $imageInfo = @getimagesizefromstring($binary);
        $sourceWidth = (int) ($imageInfo[0] ?? 0);
        $sourceHeight = (int) ($imageInfo[1] ?? 0);
        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            return [];
        }

        $sourceImage = @imagecreatefromstring($binary);
        if ($sourceImage === false) {
            return [];
        }

        $generated = [];
        $quality = max(30, min(100, (int) config('media.responsive_variant_quality', 80)));
        $widths = $this->variantWidths();

        foreach ($widths as $targetWidth) {
            if ($targetWidth <= 0 || $targetWidth >= $sourceWidth) {
                continue;
            }

            $targetHeight = max(1, (int) round(($sourceHeight / $sourceWidth) * $targetWidth));
            $variantImage = imagecreatetruecolor($targetWidth, $targetHeight);
            if ($variantImage === false) {
                continue;
            }

            $this->prepareTransparentCanvas($variantImage, $targetWidth, $targetHeight);
            @imagecopyresampled(
                $variantImage,
                $sourceImage,
                0,
                0,
                0,
                0,
                $targetWidth,
                $targetHeight,
                $sourceWidth,
                $sourceHeight
            );

            ob_start();
            $written = @imagewebp($variantImage, null, $quality);
            $variantBinary = ob_get_clean();
            imagedestroy($variantImage);

            if (!$written || !is_string($variantBinary) || $variantBinary === '') {
                continue;
            }

            $variantKey = $this->variantKey($normalizedKey, $targetWidth);
            $disk->put($variantKey, $variantBinary, ['visibility' => 'public']);
            $generated[] = $variantKey;
        }

        imagedestroy($sourceImage);

        return $generated;
    }

    public function deleteVariantsForKey(string $diskName, string $key): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $normalizedKey = ltrim(trim($key), '/');
        if ($normalizedKey === '' || $this->isVariantKey($normalizedKey)) {
            return 0;
        }

        $disk = Storage::disk($diskName);

        $directory = dirname($normalizedKey);
        if ($directory === '.' || $directory === DIRECTORY_SEPARATOR) {
            $directory = '';
        }

        $baseName = pathinfo($normalizedKey, PATHINFO_FILENAME);
        if ($baseName === '') {
            return 0;
        }

        $pattern = '/^' . preg_quote($baseName, '/') . '__w\d+\.webp$/i';
        $files = $directory === '' ? $disk->files() : $disk->files($directory);

        $toDelete = [];
        foreach ($files as $file) {
            $candidate = pathinfo((string) $file, PATHINFO_BASENAME);
            if (preg_match($pattern, $candidate) === 1) {
                $toDelete[] = $file;
            }
        }

        if (empty($toDelete)) {
            return 0;
        }

        $disk->delete($toDelete);

        return count($toDelete);
    }

    public function isEnabled(): bool
    {
        return (bool) config('media.responsive_variants_enabled', false);
    }

    protected function variantKey(string $key, int $width): string
    {
        $directory = dirname($key);
        if ($directory === '.' || $directory === DIRECTORY_SEPARATOR) {
            $directory = '';
        }

        $baseName = pathinfo($key, PATHINFO_FILENAME);
        $fileName = $baseName . '__w' . $width . '.webp';

        return $directory !== '' ? $directory . '/' . $fileName : $fileName;
    }

    protected function isVariantKey(string $key): bool
    {
        return preg_match('/__w\d+\.webp$/i', $key) === 1;
    }

    /**
     * @return array<int,int>
     */
    protected function variantWidths(): array
    {
        $widths = config('media.responsive_variant_widths', [256, 512, 1024]);
        if (!is_array($widths)) {
            $widths = [256, 512, 1024];
        }

        $normalized = array_values(array_unique(array_filter(array_map(static function ($width) {
            return (int) $width;
        }, $widths), static function (int $width) {
            return $width > 0;
        })));

        sort($normalized, SORT_NUMERIC);

        return $normalized;
    }

    /**
     * @param \GdImage|resource $image
     */
    protected function prepareTransparentCanvas($image, int $width, int $height): void
    {
        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }

        @imagealphablending($image, false);
        @imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        imagefilledrectangle($image, 0, 0, max(0, $width - 1), max(0, $height - 1), $transparent);
    }
}

