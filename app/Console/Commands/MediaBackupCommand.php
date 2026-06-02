<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MediaBackupCommand extends Command
{
    protected $signature = 'media:backup
        {--source-disk= : Source media disk (defaults to media.disk config)}
        {--target-disk= : Target backup disk (defaults to media.backup_target_disk config)}
        {--prefix= : Backup prefix path on target disk}
        {--snapshot= : Snapshot id (defaults to UTC timestamp)}';

    protected $description = 'Create a media snapshot by copying all objects from source disk to backup disk.';

    public function handle(): int
    {
        $sourceDisk = (string) ($this->option('source-disk') ?: config('media.disk', 'media_local'));
        $targetDisk = (string) ($this->option('target-disk') ?: config('media.backup_target_disk', 'backups'));
        $prefix = trim((string) ($this->option('prefix') ?: config('media.backup_prefix', 'media-backups')), '/');
        $snapshot = trim((string) ($this->option('snapshot') ?: now('UTC')->format('Ymd_His')));

        if ($snapshot === '') {
            $this->error('Snapshot id cannot be empty.');
            return self::FAILURE;
        }

        $snapshotRoot = trim($prefix . '/' . $snapshot, '/');
        $objectsRoot = $snapshotRoot . '/objects';

        $source = Storage::disk($sourceDisk);
        $target = Storage::disk($targetDisk);

        try {
            $sourceFiles = $source->allFiles();
        } catch (\Throwable $e) {
            $this->error('Failed to enumerate source files: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($sourceFiles)) {
            $this->warn('No media objects found on source disk. Writing empty manifest.');
        }

        $copied = 0;
        $failed = 0;
        $totalBytes = 0;
        $compressedBytes = 0;
        $failures = [];
        $compression = 'gzip-per-file';
        $objectsSuffix = '.gz';

        foreach ($sourceFiles as $file) {
            $relative = ltrim($file, '/');
            $targetPath = $objectsRoot . '/' . $relative . $objectsSuffix;

            $stream = $source->readStream($file);
            if ($stream === false) {
                $failed++;
                $failures[] = ['file' => $file, 'error' => 'readStream failed'];
                continue;
            }

            $tmpPath = null;
            try {
                try {
                    $tmpPath = $this->compressStreamToTempFile($stream);
                } catch (Throwable $e) {
                    $failed++;
                    $failures[] = ['file' => $file, 'error' => 'compression failed: ' . $e->getMessage()];
                    continue;
                }

                $compressedStream = @fopen($tmpPath, 'rb');
                if ($compressedStream === false) {
                    $failed++;
                    $failures[] = ['file' => $file, 'error' => 'compressed stream open failed'];
                    continue;
                }

                $written = false;
                try {
                    $written = $target->writeStream($targetPath, $compressedStream, ['visibility' => 'private']);
                } finally {
                    fclose($compressedStream);
                }

                if (!$written) {
                    $failed++;
                    $failures[] = ['file' => $file, 'error' => 'writeStream failed'];
                    continue;
                }

                $copied++;
                try {
                    $totalBytes += (int) $source->size($file);
                } catch (Throwable) {
                    // Skip size accounting failures; backup data is still usable.
                }
                $compressedSize = @filesize($tmpPath);
                if (is_int($compressedSize) && $compressedSize > 0) {
                    $compressedBytes += $compressedSize;
                }
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
                if (is_string($tmpPath) && $tmpPath !== '' && is_file($tmpPath)) {
                    @unlink($tmpPath);
                }
            }
        }

        $manifest = [
            'schema_version' => 1,
            'created_at_utc' => now('UTC')->toIso8601String(),
            'source_disk' => $sourceDisk,
            'target_disk' => $targetDisk,
            'snapshot' => $snapshot,
            'objects_root' => $objectsRoot,
            'compression' => $compression,
            'objects_suffix' => $objectsSuffix,
            'copied_files' => $copied,
            'failed_files' => $failed,
            'source_total_bytes' => $totalBytes,
            'compressed_total_bytes' => $compressedBytes,
            'failures' => $failures,
        ];

        $target->put($snapshotRoot . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('Media backup completed.');
        $this->line('Snapshot: ' . $snapshot);
        $this->line('Source disk: ' . $sourceDisk);
        $this->line('Target disk: ' . $targetDisk);
        $this->line('Copied files: ' . $copied);
        $this->line('Failed files: ' . $failed);
        if ($copied > 0) {
            $ratio = $totalBytes > 0
                ? round(($compressedBytes / $totalBytes) * 100, 1)
                : 0;
            $this->line('Compression: ' . $compression . ' (' . $ratio . '% of source size)');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param resource $stream
     */
    protected function compressStreamToTempFile($stream): string
    {
        if (!is_resource($stream)) {
            throw new \RuntimeException('Invalid source stream');
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'media-backup-');
        if (!is_string($tmpPath) || $tmpPath === '') {
            throw new \RuntimeException('Unable to create temp file');
        }

        $gz = @gzopen($tmpPath, 'wb9');
        if ($gz === false) {
            @unlink($tmpPath);
            throw new \RuntimeException('Unable to open gzip writer');
        }

        $chunkSize = 1024 * 1024;
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, $chunkSize);
                if ($chunk === false) {
                    throw new \RuntimeException('Failed reading source stream');
                }
                if ($chunk === '') {
                    continue;
                }

                $written = gzwrite($gz, $chunk);
                if (!is_int($written) || $written <= 0) {
                    throw new \RuntimeException('Failed writing gzip stream');
                }
            }
        } finally {
            gzclose($gz);
        }

        return $tmpPath;
    }
}
