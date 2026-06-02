<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaRestoreCommand extends Command
{
    protected $signature = 'media:restore
        {snapshot : Snapshot id to restore}
        {--source-disk=backups : Source backup disk}
        {--target-disk= : Target media disk (defaults to media.disk config)}
        {--prefix= : Backup prefix path on source disk}
        {--wipe-target : Delete all existing files on target disk before restore}
        {--force : Required together with --wipe-target}';

    protected $description = 'Restore a media snapshot from backup disk into target media disk.';

    public function handle(): int
    {
        $snapshot = trim((string) $this->argument('snapshot'));
        $sourceDisk = (string) ($this->option('source-disk') ?: 'backups');
        $targetDisk = (string) ($this->option('target-disk') ?: config('media.disk', 'media_local'));
        $prefix = trim((string) ($this->option('prefix') ?: config('media.backup_prefix', 'media-backups')), '/');

        if ($snapshot === '') {
            $this->error('Snapshot id cannot be empty.');
            return self::FAILURE;
        }

        $snapshotRoot = trim($prefix . '/' . $snapshot, '/');
        $objectsRoot = $snapshotRoot . '/objects';
        $compression = '';
        $objectsSuffix = '';

        $source = Storage::disk($sourceDisk);
        $target = Storage::disk($targetDisk);

        $manifestPath = $snapshotRoot . '/manifest.json';
        if (!$source->exists($manifestPath)) {
            $this->warn('Manifest not found; proceeding with best-effort restore.');
        } else {
            try {
                $manifestRaw = $source->get($manifestPath);
                $manifest = json_decode((string) $manifestRaw, true);
                if (is_array($manifest)) {
                    $manifestObjectsRoot = trim((string) ($manifest['objects_root'] ?? ''), '/');
                    if ($manifestObjectsRoot !== '') {
                        $objectsRoot = $manifestObjectsRoot;
                    }
                    $compression = trim((string) ($manifest['compression'] ?? ''));
                    $objectsSuffix = trim((string) ($manifest['objects_suffix'] ?? ''));
                }
            } catch (\Throwable) {
                // Best-effort restore should proceed even if manifest parsing fails.
            }
        }

        try {
            $backupFiles = $source->allFiles($objectsRoot);
        } catch (\Throwable $e) {
            $this->error('Failed to enumerate snapshot files: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($backupFiles)) {
            $this->error('No files found under snapshot objects root: ' . $objectsRoot);
            return self::FAILURE;
        }

        if ($this->option('wipe-target')) {
            if (!$this->option('force')) {
                $this->error('--wipe-target requires --force.');
                return self::FAILURE;
            }

            $this->warn('Wiping target disk before restore...');
            try {
                $targetFiles = $target->allFiles();
                if (!empty($targetFiles)) {
                    $target->delete($targetFiles);
                }
            } catch (\Throwable $e) {
                $this->error('Failed to wipe target disk: ' . $e->getMessage());
                return self::FAILURE;
            }
        }

        $copied = 0;
        $failed = 0;

        foreach ($backupFiles as $sourcePath) {
            $relative = ltrim(Str::after($sourcePath, $objectsRoot), '/');
            if ($relative === '') {
                continue;
            }

            if ($objectsSuffix !== '' && str_ends_with($relative, $objectsSuffix)) {
                $relative = substr($relative, 0, -strlen($objectsSuffix));
            }
            if ($relative === '') {
                $failed++;
                continue;
            }

            $stream = $source->readStream($sourcePath);
            if ($stream === false) {
                $failed++;
                continue;
            }

            try {
                if ($compression === 'gzip-per-file') {
                    $filter = @stream_filter_append($stream, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 31]);
                    if ($filter === false) {
                        $failed++;
                        continue;
                    }
                }

                $written = $target->writeStream($relative, $stream, ['visibility' => 'public']);
                if ($written) {
                    $copied++;
                } else {
                    $failed++;
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }

        $this->info('Media restore completed.');
        $this->line('Snapshot: ' . $snapshot);
        $this->line('Source disk: ' . $sourceDisk);
        $this->line('Target disk: ' . $targetDisk);
        $this->line('Copied files: ' . $copied);
        $this->line('Failed files: ' . $failed);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
