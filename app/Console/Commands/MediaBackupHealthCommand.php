<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MediaBackupHealthCommand extends Command
{
    protected $signature = 'media:backup-health
        {--disk=backups : Backup disk}
        {--prefix= : Backup prefix path}
        {--snapshot= : Snapshot id to validate (defaults to latest)}
        {--max-age-hours= : Maximum allowed backup age in hours}
        {--allow-failed-files : Do not fail when manifest reports failed files}';

    protected $description = 'Validate backup recency and manifest health for media snapshots.';

    public function handle(): int
    {
        $diskName = (string) ($this->option('disk') ?: config('media.backup_target_disk', 'backups'));
        $prefix = trim((string) ($this->option('prefix') ?: config('media.backup_prefix', 'media-backups')), '/');
        $snapshot = trim((string) ($this->option('snapshot') ?: ''));
        $maxAgeHours = (int) ($this->option('max-age-hours') ?: config('media.backup_health_max_age_hours', 30));
        $allowFailedFiles = (bool) $this->option('allow-failed-files');

        $disk = Storage::disk($diskName);

        $manifestPath = $snapshot !== ''
            ? trim($prefix . '/' . $snapshot . '/manifest.json', '/')
            : $this->latestManifestPath($diskName, $prefix);

        if (!is_string($manifestPath) || $manifestPath === '' || !$disk->exists($manifestPath)) {
            $this->error('No backup manifest found.');
            return self::FAILURE;
        }

        $manifestRaw = $disk->get($manifestPath);
        $manifest = json_decode((string) $manifestRaw, true);
        if (!is_array($manifest)) {
            $this->error('Backup manifest is invalid JSON: ' . $manifestPath);
            return self::FAILURE;
        }

        $createdAt = isset($manifest['created_at_utc']) ? (string) $manifest['created_at_utc'] : null;
        if (!is_string($createdAt) || trim($createdAt) === '') {
            $this->error('Manifest has no created_at_utc timestamp.');
            return self::FAILURE;
        }

        try {
            $createdAtUtc = CarbonImmutable::parse($createdAt)->utc();
        } catch (\Throwable $e) {
            $this->error('Manifest has invalid created_at_utc: ' . $e->getMessage());
            return self::FAILURE;
        }

        $ageHours = $createdAtUtc->diffInHours(CarbonImmutable::now('UTC'));
        if ($maxAgeHours > 0 && $ageHours > $maxAgeHours) {
            $this->error('Latest backup is too old (' . $ageHours . 'h > ' . $maxAgeHours . 'h).');
            return self::FAILURE;
        }

        $failedFiles = (int) ($manifest['failed_files'] ?? 0);
        if (!$allowFailedFiles && $failedFiles > 0) {
            $this->error('Backup manifest reports failed files: ' . $failedFiles);
            return self::FAILURE;
        }

        $this->info('Media backup health check passed.');
        $this->line('Manifest: ' . $manifestPath);
        $this->line('Age (hours): ' . $ageHours);
        $this->line('Failed files: ' . $failedFiles);

        return self::SUCCESS;
    }

    protected function latestManifestPath(string $diskName, string $prefix): ?string
    {
        try {
            $files = Storage::disk($diskName)->allFiles($prefix);
        } catch (\Throwable) {
            return null;
        }

        $manifests = array_values(array_filter($files, static function ($path) {
            return is_string($path) && str_ends_with($path, '/manifest.json');
        }));

        if (empty($manifests)) {
            return null;
        }

        rsort($manifests, SORT_STRING);

        return $manifests[0];
    }
}
