<?php

namespace App\Console\Commands\Mod;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;
use Throwable;

class BackupRunFullCommand extends BaseModCommand
{
    protected $signature = 'backup:run-full
        {--snapshot= : Snapshot id (default: UTC timestamp)}
        {--db-disk= : Target disk for DB backup zip}
        {--media-target-disk= : Target disk for media snapshot}
        {--media-prefix= : Prefix for media snapshots}
        {--manifest-disk= : Disk for full backup manifest}
        {--manifest-prefix= : Prefix for full backup manifest}';

    protected $description = 'Führt DB + Media Backup in einem gemeinsamen Snapshot-Lauf aus.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $snapshot = trim((string) ($this->option('snapshot') ?: now('UTC')->format('Ymd_His')));
        if ($snapshot === '' || preg_match('/^[A-Za-z0-9._-]{6,64}$/', $snapshot) !== 1) {
            $this->error('Ungültiger Snapshot-Wert. Erlaubt sind [A-Za-z0-9._-], Länge 6..64.');
            return Command::FAILURE;
        }

        $dbDisk = (string) ($this->option('db-disk') ?: config('backup.automation.full.db_disk', config('backup.automation.db.disk', 'backups')));
        $mediaTargetDisk = (string) ($this->option('media-target-disk') ?: config('backup.automation.full.media_target_disk', config('media.backup_target_disk', 'backups')));
        $mediaPrefix = trim((string) ($this->option('media-prefix') ?: config('backup.automation.full.media_prefix', config('media.backup_prefix', 'media-backups'))), '/');
        $manifestDisk = (string) ($this->option('manifest-disk') ?: config('backup.automation.full.manifest_disk', $mediaTargetDisk));
        $manifestPrefix = trim((string) ($this->option('manifest-prefix') ?: config('backup.automation.full.manifest_prefix', 'full-backups')), '/');

        $beforeDbState = $this->captureDiskState($dbDisk);

        $dbExit = Artisan::call('backup:run', [
            '--only-db' => true,
            '--only-to-disk' => $dbDisk,
            '--disable-notifications' => true,
        ]);
        $dbOutput = mb_substr((string) Artisan::output(), 0, 12000);
        $dbSuccess = $dbExit === 0;

        $dbArtifact = $this->detectLatestDbArtifact($dbDisk, $beforeDbState);

        $mediaExit = Artisan::call('media:backup', [
            '--target-disk' => $mediaTargetDisk,
            '--prefix' => $mediaPrefix,
            '--snapshot' => $snapshot,
        ]);
        $mediaOutput = mb_substr((string) Artisan::output(), 0, 12000);
        $mediaSuccess = $mediaExit === 0;

        $mediaManifestPath = trim($mediaPrefix . '/' . $snapshot . '/manifest.json', '/');
        $mediaManifestExists = false;
        try {
            $mediaManifestExists = Storage::disk($mediaTargetDisk)->exists($mediaManifestPath);
        } catch (Throwable) {
            $mediaManifestExists = false;
        }

        $fullManifest = [
            'schema_version' => 1,
            'snapshot' => $snapshot,
            'created_at_utc' => now('UTC')->toIso8601String(),
            'db' => [
                'ok' => $dbSuccess,
                'exit_code' => $dbExit,
                'disk' => $dbDisk,
                'artifact' => $dbArtifact,
                'output' => $dbOutput,
            ],
            'media' => [
                'ok' => $mediaSuccess,
                'exit_code' => $mediaExit,
                'target_disk' => $mediaTargetDisk,
                'prefix' => $mediaPrefix,
                'manifest_path' => $mediaManifestPath,
                'manifest_exists' => $mediaManifestExists,
                'output' => $mediaOutput,
            ],
            'overall_ok' => $dbSuccess && $mediaSuccess,
        ];

        $manifestPath = trim($manifestPrefix . '/' . $snapshot . '/manifest.json', '/');
        try {
            Storage::disk($manifestDisk)->put(
                $manifestPath,
                json_encode($fullManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        } catch (Throwable $e) {
            $this->error('Full manifest konnte nicht geschrieben werden: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->line('Full snapshot: ' . $snapshot);
        $this->line('DB success: ' . $this->boolLabel($dbSuccess));
        $this->line('Media success: ' . $this->boolLabel($mediaSuccess));
        $this->line('Manifest: ' . $manifestDisk . ':' . $manifestPath);

        return ($dbSuccess && $mediaSuccess) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array<string,int>|null
     */
    protected function captureDiskState(string $disk): ?array
    {
        $files = $this->listDiskFileAttributes($disk);
        if ($files === null) {
            return null;
        }

        $state = [];
        foreach ($files as $file) {
            $modified = $file->lastModified();
            if ($modified !== null) {
                $state[$file->path()] = (int) $modified;
            }
        }

        return $state;
    }

    /**
     * @param array<string,int>|null $beforeState
     * @return array<string,mixed>|null
     */
    protected function detectLatestDbArtifact(string $disk, ?array $beforeState): ?array
    {
        $files = $this->listDiskFileAttributes($disk);
        if ($files === null) {
            return null;
        }

        $candidates = [];
        foreach ($files as $file) {
            $path = $file->path();
            if (!str_ends_with(strtolower($path), '.zip')) {
                continue;
            }

            $modified = $file->lastModified();
            if ($modified === null) {
                continue;
            }
            $size = (int) ($file->fileSize() ?? 0);

            $previous = $beforeState[$path] ?? null;
            $isNewOrUpdated = $previous === null || (int) $modified > $previous;

            $candidates[] = [
                'path' => $path,
                'modified' => (int) $modified,
                'size_bytes' => $size,
                'is_new_or_updated' => $isNewOrUpdated,
            ];
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            if ($a['is_new_or_updated'] !== $b['is_new_or_updated']) {
                return $a['is_new_or_updated'] ? -1 : 1;
            }
            return $b['modified'] <=> $a['modified'];
        });

        $best = $candidates[0];

        return [
            'disk' => $disk,
            'path' => $best['path'],
            'size_bytes' => $best['size_bytes'],
            'modified_at_utc' => date('c', $best['modified']),
            'new_or_updated' => $best['is_new_or_updated'],
        ];
    }

    /**
     * @return array<int,FileAttributes>|null
     */
    protected function listDiskFileAttributes(string $disk, string $prefix = ''): ?array
    {
        try {
            $items = [];
            foreach (Storage::disk($disk)->getDriver()->listContents($prefix, true) as $item) {
                if ($item instanceof FileAttributes) {
                    $items[] = $item;
                }
            }

            return $items;
        } catch (Throwable) {
            return null;
        }
    }
}
