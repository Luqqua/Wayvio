<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserData;
use App\Services\Uploads\MediaStorageService;
use Illuminate\Console\Command;

class MediaBackfillLegacyCommand extends Command
{
    protected $signature = 'media:backfill-legacy
        {--user-id= : Migrate only one user id}
        {--chunk=200 : Chunk size for batch mode}
        {--dry-run : Preview only, no writes}
        {--keep-legacy : Keep legacy files after successful migration}';

    protected $description = 'Migrate legacy local media paths into tenant-aware media keys for S3-ready storage.';

    public function __construct(private readonly MediaStorageService $mediaStorage)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $userIdFilter = $this->option('user-id');
        $dryRun = (bool) $this->option('dry-run');
        $keepLegacy = (bool) $this->option('keep-legacy');
        $chunk = max(10, (int) $this->option('chunk'));

        $query = User::query()->select('id')->orderBy('id');
        if ($userIdFilter !== null && $userIdFilter !== '') {
            $query->where('id', (int) $userIdFilter);
        }

        $stats = [
            'users_scanned' => 0,
            'migrated_avatar' => 0,
            'migrated_background' => 0,
            'migrated_header' => 0,
            'migrated_branding' => 0,
            'failures' => 0,
        ];

        $query->chunkById($chunk, function ($users) use (&$stats, $dryRun, $keepLegacy): void {
            foreach ($users as $user) {
                $userId = (int) $user->id;
                $stats['users_scanned']++;

                try {
                    if ($this->migrateAvatar($userId, $dryRun, $keepLegacy)) {
                        $stats['migrated_avatar']++;
                    }
                    if ($this->migrateBackground($userId, $dryRun, $keepLegacy)) {
                        $stats['migrated_background']++;
                    }
                    if ($this->migrateUserDataPath($userId, 'header_image', 'headers', 'assets/img/header-img/', $dryRun, $keepLegacy)) {
                        $stats['migrated_header']++;
                    }
                    if ($this->migrateUserDataPath($userId, 'agency_branding_asset', 'agency-branding', 'assets/img/agency-branding/', $dryRun, $keepLegacy)) {
                        $stats['migrated_branding']++;
                    }
                } catch (\Throwable $e) {
                    $stats['failures']++;
                    $this->warn('Migration failed for user ' . $userId . ': ' . $e->getMessage());
                }
            }
        });

        $this->info('Legacy media backfill completed.');
        foreach ($stats as $key => $value) {
            $this->line($key . ': ' . $value);
        }

        return $stats['failures'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function migrateAvatar(int $userId, bool $dryRun, bool $keepLegacy): bool
    {
        $currentStored = $this->normalizePath(UserData::getData($userId, $this->mediaStorage->avatarDataKey()));
        if ($currentStored !== null && !$this->isLegacyPath($currentStored, 'assets/img/')) {
            return false;
        }

        $resolved = $this->mediaStorage->avatarPathForUser($userId);
        if (!$this->isLegacyPath($resolved, 'assets/img/')) {
            return false;
        }

        $binary = $this->mediaStorage->binaryContents($resolved);
        if (!is_string($binary) || $binary === '') {
            return false;
        }

        if ($dryRun) {
            $this->line('[dry-run] avatar user=' . $userId . ' from=' . $resolved);
            return true;
        }

        $extension = $this->extensionFromPath($resolved, 'jpg');
        $newKey = $this->mediaStorage->storeBinary($userId, 'avatars', $binary, $extension);
        UserData::saveData($userId, $this->mediaStorage->avatarDataKey(), $newKey);

        if (!$keepLegacy) {
            $this->mediaStorage->delete($resolved);
        }

        return true;
    }

    protected function migrateBackground(int $userId, bool $dryRun, bool $keepLegacy): bool
    {
        $currentStored = $this->normalizePath(UserData::getData($userId, $this->mediaStorage->backgroundDataKey()));
        if ($currentStored !== null && !$this->isLegacyPath($currentStored, 'assets/img/background-img/')) {
            return false;
        }

        $resolved = $this->mediaStorage->backgroundPathForUser($userId);
        if (!$this->isLegacyPath($resolved, 'assets/img/background-img/')) {
            return false;
        }

        $binary = $this->mediaStorage->binaryContents($resolved);
        if (!is_string($binary) || $binary === '') {
            return false;
        }

        if ($dryRun) {
            $this->line('[dry-run] background user=' . $userId . ' from=' . $resolved);
            return true;
        }

        $extension = $this->extensionFromPath($resolved, 'jpg');
        $newKey = $this->mediaStorage->storeBinary($userId, 'backgrounds', $binary, $extension);
        UserData::saveData($userId, $this->mediaStorage->backgroundDataKey(), $newKey);

        if (!$keepLegacy) {
            $this->mediaStorage->delete($resolved);
        }

        return true;
    }

    protected function migrateUserDataPath(
        int $userId,
        string $userDataKey,
        string $targetCategory,
        string $legacyPrefix,
        bool $dryRun,
        bool $keepLegacy
    ): bool {
        $path = $this->normalizePath(UserData::getData($userId, $userDataKey));
        if (!$this->isLegacyPath($path, $legacyPrefix)) {
            return false;
        }

        $binary = $this->mediaStorage->binaryContents($path);
        if (!is_string($binary) || $binary === '') {
            return false;
        }

        if ($dryRun) {
            $this->line('[dry-run] ' . $userDataKey . ' user=' . $userId . ' from=' . $path);
            return true;
        }

        $extension = $this->extensionFromPath($path, 'jpg');
        $newKey = $this->mediaStorage->storeBinary($userId, $targetCategory, $binary, $extension);
        UserData::saveData($userId, $userDataKey, $newKey);

        if (!$keepLegacy) {
            $this->mediaStorage->delete($path);
        }

        return true;
    }

    protected function extensionFromPath(string $path, string $fallback): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === '') {
            return $fallback;
        }

        return $extension;
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

    protected function isLegacyPath(?string $path, string $requiredPrefix): bool
    {
        if ($path === null) {
            return false;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return false;
        }

        return str_starts_with($path, ltrim($requiredPrefix, '/'));
    }
}
