<?php

namespace App\Console\Commands\Mod;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class BackupCreateCommand extends BaseModCommand
{
    protected $signature = 'backup:create {--disk= : Target disk (defaults to backup.automation.db.disk config)} {--path=}';
    protected $description = 'Datenbank-Backup erstellen.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $disk = $this->option('disk') ?: config('backup.automation.db.disk', 'backups');
        $path = $this->option('path');

        $result = Artisan::call('backup:run', [
            '--only-db' => true,
            '--only-to-disk' => $disk,
            '--disable-notifications' => true,
        ]);

        $output = Artisan::output();
        $this->line(trim($output));

        if ($path) {
            $latest = collect(Storage::disk($disk)->allFiles())
                ->map(function (string $file) use ($disk): array {
                    return [
                        'path' => $file,
                        'modified' => Storage::disk($disk)->lastModified($file),
                    ];
                })
                ->sortByDesc('modified')
                ->pluck('path')
                ->first();

            if ($latest && Storage::disk($disk)->exists($latest)) {
                $target = rtrim($path, '/');
                if (!is_dir($target)) {
                    mkdir($target, 0755, true);
                }
                $sourcePath = Storage::disk($disk)->path($latest);
                copy($sourcePath, $target.'/'.basename($latest));
                $this->info('Backup zusätzlich kopiert nach: '.$target.'/'.basename($latest));
            }
        }

        return $result === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
