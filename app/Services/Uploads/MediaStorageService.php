<?php

namespace App\Services\Uploads;

use App\Models\AgencyHub;
use App\Models\UserData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaStorageService
{
    public const AVATAR_USER_DATA_KEY = 'avatar_media_key';
    public const BACKGROUND_USER_DATA_KEY = 'background_media_key';
    public const FAVICON_USER_DATA_KEY = 'favicon_media_key';

    /**
     * @var array<int,int>
     */
    private static array $tenantCache = [];

    public function __construct(
        private readonly ImageUploadSanitizer $sanitizer,
        private readonly UploadMalwareScanner $malwareScanner,
        private readonly ResponsiveImageVariantService $variantService
    )
    {
    }

    public function activeDiskName(): string
    {
        $configured = (string) config('media.disk', 'media_local');
        $disks = config('filesystems.disks', []);

        return is_array($disks) && array_key_exists($configured, $disks)
            ? $configured
            : 'media_local';
    }

    public function avatarPathForUser(int $userId): ?string
    {
        $key = $this->normalizePath(UserData::getData($userId, $this->avatarDataKey()));
        if ($key !== null && $this->exists($key)) {
            return $key;
        }

        return $this->legacyAvatarPathForUser($userId);
    }

    public function backgroundPathForUser(int $userId): ?string
    {
        $key = $this->normalizePath(UserData::getData($userId, $this->backgroundDataKey()));
        if ($key !== null && $this->exists($key)) {
            return $key;
        }

        return $this->legacyBackgroundPathForUser($userId);
    }

    public function avatarDataKey(): string
    {
        return (string) config('media.user_data_keys.avatar', self::AVATAR_USER_DATA_KEY);
    }

    public function backgroundDataKey(): string
    {
        return (string) config('media.user_data_keys.background', self::BACKGROUND_USER_DATA_KEY);
    }

    public function faviconDataKey(): string
    {
        return (string) config('media.user_data_keys.favicon', self::FAVICON_USER_DATA_KEY);
    }

    public function faviconPathForUser(int $userId): ?string
    {
        $key = $this->normalizePath(UserData::getData($userId, $this->faviconDataKey()));
        if ($key !== null && $this->exists($key)) {
            return $key;
        }

        return null;
    }

    public function deleteFaviconForUser(int $userId): void
    {
        $stored = $this->normalizePath(UserData::getData($userId, $this->faviconDataKey()));
        if ($stored !== null) {
            $this->delete($stored);
        }

        UserData::removeData($userId, $this->faviconDataKey());
    }

    public function storeUploadedFile(int $userId, UploadedFile $file, string $category): string
    {
        $extension = $this->normalizeExtension((string) ($file->extension() ?: $file->getClientOriginalExtension() ?: 'bin'));
        $realPath = $file->getRealPath();

        if (is_string($realPath) && $realPath !== '' && is_file($realPath) && is_readable($realPath)) {
            $preparedExtension = $this->prepareImageFileForStorage($realPath, $extension);
            $key = $this->buildUserKey($userId, $category, $preparedExtension);

            $stream = fopen($realPath, 'rb');
            if ($stream !== false) {
                try {
                    $this->disk()->put($key, $stream, ['visibility' => 'public']);
                } finally {
                    fclose($stream);
                }

                $this->dispatchResponsiveVariants($key, $preparedExtension);
                return $key;
            }

            $preparedBinary = @file_get_contents($realPath);
            if (is_string($preparedBinary)) {
                $this->disk()->put($key, $preparedBinary, ['visibility' => 'public']);
                $this->dispatchResponsiveVariants($key, $preparedExtension);
                return $key;
            }
        }

        return $this->storeBinary($userId, $category, $file->get(), $extension);
    }

    public function storeBinary(int $userId, string $category, string $binaryData, string $extension): string
    {
        $normalizedExtension = $this->normalizeExtension($extension);
        $preparedExtension = $normalizedExtension;
        $preparedBinary = $binaryData;

        $tempPath = tempnam(sys_get_temp_dir(), 'wayvio-media-');
        if (is_string($tempPath) && $tempPath !== '') {
            try {
                if (file_put_contents($tempPath, $binaryData) !== false) {
                    $preparedExtension = $this->prepareImageFileForStorage($tempPath, $normalizedExtension);
                    $fromTemp = @file_get_contents($tempPath);
                    if (is_string($fromTemp) && $fromTemp !== '') {
                        $preparedBinary = $fromTemp;
                    }
                }
            } finally {
                @unlink($tempPath);
            }
        }

        $key = $this->buildUserKey($userId, $category, $preparedExtension);
        $this->disk()->put($key, $preparedBinary, ['visibility' => 'public']);
        $this->dispatchResponsiveVariants($key, $preparedExtension);

        return $key;
    }

    public function exists(?string $pathOrKey): bool
    {
        $path = $this->normalizePath($pathOrKey);
        if ($path === null) {
            return false;
        }

        if ($this->isRemoteUrl($path)) {
            return true;
        }

        if ($this->isLegacyAssetPath($path)) {
            return is_file(base_path($path));
        }

        try {
            return $this->disk()->exists($path);
        } catch (\Throwable) {
            return false;
        }
    }

    public function url(?string $pathOrKey): ?string
    {
        $path = $this->normalizePath($pathOrKey);
        if ($path === null) {
            return null;
        }

        if ($this->isRemoteUrl($path)) {
            return $path;
        }

        if ($this->isLegacyAssetPath($path)) {
            return url($path);
        }

        try {
            return $this->disk()->url($path);
        } catch (\Throwable) {
            return null;
        }
    }

    public function delete(?string $pathOrKey): bool
    {
        $path = $this->normalizePath($pathOrKey);
        if ($path === null) {
            return false;
        }

        if ($this->isRemoteUrl($path)) {
            return false;
        }

        if ($this->isLegacyAssetPath($path)) {
            return $this->deleteLegacyAssetPath($path);
        }

        try {
            $deletedOriginal = $this->disk()->delete($path);
            $deletedVariants = $this->variantService->deleteVariantsForKey($this->activeDiskName(), $path);

            return $deletedOriginal || $deletedVariants > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function binaryContents(?string $pathOrKey): ?string
    {
        $path = $this->normalizePath($pathOrKey);
        if ($path === null || $this->isRemoteUrl($path)) {
            return null;
        }

        if ($this->isLegacyAssetPath($path)) {
            $absolutePath = base_path($path);
            if (!is_file($absolutePath) || !is_readable($absolutePath)) {
                return null;
            }

            $contents = @file_get_contents($absolutePath);
            return $contents === false ? null : $contents;
        }

        if (!$this->exists($path)) {
            return null;
        }

        try {
            return $this->disk()->get($path);
        } catch (\Throwable) {
            return null;
        }
    }

    public function deleteAvatarForUser(int $userId): void
    {
        $stored = $this->normalizePath(UserData::getData($userId, $this->avatarDataKey()));
        if ($stored !== null) {
            $this->delete($stored);
        }

        while (true) {
            $legacy = $this->legacyAvatarPathForUser($userId);
            if ($legacy === null) {
                break;
            }
            $this->delete($legacy);
        }

        UserData::removeData($userId, $this->avatarDataKey());
    }

    public function deleteBackgroundForUser(int $userId): void
    {
        $stored = $this->normalizePath(UserData::getData($userId, $this->backgroundDataKey()));
        if ($stored !== null) {
            $this->delete($stored);
        }

        while (true) {
            $legacy = $this->legacyBackgroundPathForUser($userId);
            if ($legacy === null) {
                break;
            }
            $this->delete($legacy);
        }

        UserData::removeData($userId, $this->backgroundDataKey());
    }

    protected function buildUserKey(int $userId, string $category, string $extension): string
    {
        $tenantId = $this->tenantIdForUser($userId);
        $tenantPrefix = trim((string) config('media.tenant_prefix', 'tenants'), '/');
        $categoryPath = trim(strtolower($category), '/');
        $filename = Str::uuid()->toString() . '.' . ltrim($extension, '.');

        return $tenantPrefix
            . '/' . $tenantId
            . '/users/' . $userId
            . '/' . $categoryPath
            . '/' . $filename;
    }

    protected function tenantIdForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        if (isset(self::$tenantCache[$userId])) {
            return self::$tenantCache[$userId];
        }

        if (!Schema::hasTable('agency_hubs')) {
            self::$tenantCache[$userId] = $userId;
            return $userId;
        }

        $agencyOwnerId = (int) AgencyHub::query()
            ->where('managed_user_id', $userId)
            ->where('status', 'active')
            ->value('agency_user_id');

        $tenantId = $agencyOwnerId > 0 ? $agencyOwnerId : $userId;
        self::$tenantCache[$userId] = $tenantId;

        return $tenantId;
    }

    protected function legacyAvatarPathForUser(int $userId): ?string
    {
        return $this->findLegacyPathByPattern(
            base_path('assets/img'),
            '/^' . preg_quote((string) $userId, '/') . '(_\\w+)?\\.\\w+$/i',
            'assets/img/'
        );
    }

    protected function legacyBackgroundPathForUser(int $userId): ?string
    {
        return $this->findLegacyPathByPattern(
            base_path('assets/img/background-img'),
            '/^' . preg_quote((string) $userId, '/') . '(_\\w+)?\\.\\w+$/i',
            'assets/img/background-img/'
        );
    }

    protected function findLegacyPathByPattern(string $directory, string $pattern, string $prefix): ?string
    {
        if (!is_dir($directory)) {
            return null;
        }

        $files = scandir($directory);
        if (!is_array($files)) {
            return null;
        }

        foreach ($files as $file) {
            if (preg_match($pattern, $file) === 1) {
                return $prefix . $file;
            }
        }

        return null;
    }

    protected function normalizePath(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $path = trim($value);
        if ($path === '' || strtolower($path) === 'null') {
            return null;
        }

        return ltrim($path, '/');
    }

    protected function isRemoteUrl(string $path): bool
    {
        return preg_match('/^https?:\/\//i', $path) === 1;
    }

    protected function isLegacyAssetPath(string $path): bool
    {
        return str_starts_with($path, 'assets/');
    }

    protected function deleteLegacyAssetPath(string $path): bool
    {
        $normalized = ltrim(trim($path), '/');
        if ($normalized === '') {
            return false;
        }

        $allowedPrefixes = [
            'assets/img/',
            'assets/media/',
        ];

        $allowed = false;
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            return false;
        }

        $absolutePath = base_path($normalized);
        $realPath = realpath($absolutePath);
        $assetsRoot = realpath(base_path('assets'));

        if (
            !$realPath
            || !$assetsRoot
            || !str_starts_with($realPath, rtrim($assetsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
            || !is_file($realPath)
        ) {
            return false;
        }

        return @unlink($realPath);
    }

    protected function normalizeExtension(string $extension): string
    {
        $normalized = strtolower(trim($extension));
        if ($normalized === 'jpeg') {
            $normalized = 'jpg';
        }

        return $normalized !== '' ? $normalized : 'bin';
    }

    protected function shouldConvertUploadsToWebp(): bool
    {
        return (bool) config('media.convert_uploads_to_webp', true);
    }

    protected function prepareImageFileForStorage(string $path, string $fallbackExtension): string
    {
        $extension = $this->normalizeExtension($fallbackExtension);

        if (!is_file($path) || !is_readable($path)) {
            return $extension;
        }

        $this->malwareScanner->assertClean($path);
        $this->sanitizer->stripMetadata($path);

        if ($this->shouldConvertUploadsToWebp() && $this->convertImageFileToWebp($path)) {
            return 'webp';
        }

        return $this->detectImageExtension($path) ?? $extension;
    }

    protected function detectImageExtension(string $path): ?string
    {
        $info = @getimagesize($path);
        $type = $info[2] ?? null;

        return match ($type) {
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => null,
        };
    }

    protected function convertImageFileToWebp(string $path): bool
    {
        if (!extension_loaded('gd') || !function_exists('imagewebp')) {
            return false;
        }

        $imageInfo = @getimagesize($path);
        $imageType = $imageInfo[2] ?? null;
        if (!is_int($imageType)) {
            return false;
        }

        // Avoid fatal OOM when decoding large/high-resolution uploads with GD.
        if (!$this->canSafelyDecodeWithGd(is_array($imageInfo) ? $imageInfo : [])) {
            return false;
        }

        $image = match ($imageType) {
            IMAGETYPE_JPEG => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            IMAGETYPE_PNG => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };

        if (!$image) {
            return false;
        }

        if (in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $this->prepareAlphaChannel($image);
        }

        $quality = max(30, min(100, (int) config('media.webp_quality', 82)));
        $written = @imagewebp($image, $path, $quality);
        imagedestroy($image);

        return (bool) $written;
    }

    /**
     * @param \GdImage|resource $image
     */
    protected function prepareAlphaChannel($image): void
    {
        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($image);
        }

        @imagealphablending($image, false);
        @imagesavealpha($image, true);
    }

    protected function isImageExtension(string $extension): bool
    {
        return in_array($this->normalizeExtension($extension), ['jpg', 'png', 'webp'], true);
    }

    protected function dispatchResponsiveVariants(string $key, string $extension): void
    {
        if (!$this->isImageExtension($extension)) {
            return;
        }

        $this->variantService->dispatchGeneration($this->activeDiskName(), $key);
    }

    protected function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk($this->activeDiskName());
    }

    /**
     * @param array<int,mixed> $imageInfo
     */
    protected function canSafelyDecodeWithGd(array $imageInfo): bool
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

        // Conservative estimate for decoded bitmap + conversion overhead.
        $required = ((float) $width * (float) $height * 8.0) + (32.0 * 1024.0 * 1024.0);

        return $required < ((float) $available * 0.9);
    }

    protected function memoryLimitBytes(): ?int
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
