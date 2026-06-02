<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaRestoreDrillCommand extends Command
{
    protected $signature = 'media:restore-drill
        {--source-disk=backups : Source backup disk}
        {--prefix= : Backup prefix path}
        {--snapshot= : Snapshot id (defaults to latest)}
        {--sample= : Number of files to test-restore}
        {--scratch= : Scratch directory for drill extraction}';

    protected $description = 'Perform a non-destructive restore drill by extracting sampled files from a backup snapshot.';

    public function handle(): int
    {
        $sourceDisk = (string) ($this->option('source-disk') ?: config('media.backup_target_disk', 'backups'));
        $prefix = trim((string) ($this->option('prefix') ?: config('media.backup_prefix', 'media-backups')), '/');
        $snapshot = trim((string) ($this->option('snapshot') ?: ''));
        $sampleSize = max(1, (int) ($this->option('sample') ?: config('media.restore_drill_sample_files', 20)));
        $scratchRoot = trim((string) ($this->option('scratch') ?: storage_path('app/media-restore-drill')));

        $disk = Storage::disk($sourceDisk);
        $manifestPath = $snapshot !== ''
            ? trim($prefix . '/' . $snapshot . '/manifest.json', '/')
            : $this->latestManifestPath($sourceDisk, $prefix);

        if (!is_string($manifestPath) || $manifestPath === '' || !$disk->exists($manifestPath)) {
            $this->error('No backup manifest found for restore drill.');
            return self::FAILURE;
        }

        $manifestRaw = $disk->get($manifestPath);
        $manifest = json_decode((string) $manifestRaw, true);
        if (!is_array($manifest)) {
            $this->error('Backup manifest is invalid JSON: ' . $manifestPath);
            return self::FAILURE;
        }

        $objectsRoot = trim((string) ($manifest['objects_root'] ?? ''), '/');
        if ($objectsRoot === '') {
            $this->error('Manifest does not include objects_root.');
            return self::FAILURE;
        }
        $compression = trim((string) ($manifest['compression'] ?? ''));
        $objectsSuffix = trim((string) ($manifest['objects_suffix'] ?? ''));

        try {
            $allObjects = $disk->allFiles($objectsRoot);
        } catch (\Throwable $e) {
            $this->error('Failed to enumerate snapshot objects: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($allObjects)) {
            $this->error('Snapshot has no objects to drill.');
            return self::FAILURE;
        }

        sort($allObjects, SORT_STRING);
        $selected = array_slice($allObjects, 0, min($sampleSize, count($allObjects)));
        if (!empty($allObjects) && count($allObjects) > count($selected)) {
            $selected = $this->spreadSample($allObjects, $sampleSize);
        }

        $scratchPath = rtrim($scratchRoot, '/\\') . '/run_' . now('UTC')->format('Ymd_His');
        $copied = 0;
        $failed = 0;

        foreach ($selected as $sourcePath) {
            $relative = ltrim(Str::after((string) $sourcePath, $objectsRoot), '/');
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

            $targetPath = $scratchPath . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
            $targetDir = dirname($targetPath);

            if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                $failed++;
                continue;
            }

            $sourceStream = $disk->readStream((string) $sourcePath);
            if ($sourceStream === false) {
                $failed++;
                continue;
            }

            $targetStream = @fopen($targetPath, 'wb');
            if ($targetStream === false) {
                fclose($sourceStream);
                $failed++;
                continue;
            }

            try {
                if ($compression === 'gzip-per-file') {
                    $filter = @stream_filter_append($sourceStream, 'zlib.inflate', STREAM_FILTER_READ, ['window' => 31]);
                    if ($filter === false) {
                        $failed++;
                        continue;
                    }
                }

                $bytes = stream_copy_to_stream($sourceStream, $targetStream);
                if (!is_int($bytes) || $bytes <= 0) {
                    $failed++;
                } else {
                    $copied++;
                }
            } finally {
                fclose($sourceStream);
                fclose($targetStream);
            }
        }

        $this->deleteDirectory($scratchPath);

        if ($failed > 0) {
            $this->error('Restore drill completed with failures. Copied=' . $copied . ', Failed=' . $failed);
            return self::FAILURE;
        }

        $this->info('Restore drill completed successfully.');
        $this->line('Manifest: ' . $manifestPath);
        $this->line('Files tested: ' . $copied);

        return self::SUCCESS;
    }

    /**
     * @param array<int,string> $files
     * @return array<int,string>
     */
    protected function spreadSample(array $files, int $sampleSize): array
    {
        $count = count($files);
        if ($sampleSize >= $count) {
            return $files;
        }

        $sample = [];
        $step = max(1, (int) floor($count / $sampleSize));
        for ($i = 0; $i < $count && count($sample) < $sampleSize; $i += $step) {
            $sample[] = $files[$i];
        }

        while (count($sample) < $sampleSize) {
            $index = count($sample) - 1;
            $candidate = $files[min($count - 1, max(0, $index))];
            if (!in_array($candidate, $sample, true)) {
                $sample[] = $candidate;
            } else {
                break;
            }
        }

        return array_values(array_unique($sample));
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

    protected function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }

        @rmdir($directory);
    }
}
