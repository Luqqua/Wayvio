<?php

namespace App\Console\Commands\Mod;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupRestoreCommand extends BaseModCommand
{
    protected $signature = 'backup:restore {backup_id} {--disk=backups} {--target=}';
    protected $description = 'Backup wiederherstellen (staged Kopie).';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $disk = $this->option('disk') ?: 'backups';
        $file = $this->argument('backup_id');

        if (!Storage::disk($disk)->exists($file)) {
            $this->error('Backup nicht gefunden: '.$file);
            return Command::FAILURE;
        }

        $targetDir = $this->option('target') ?: storage_path('app/restores');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = rtrim($targetDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($file);
        $contents = Storage::disk($disk)->get($file);
        file_put_contents($targetPath, $contents);

        $this->info('Backup nach '.$targetPath.' kopiert. Einspielen bitte manuell durchführen.');
        return Command::SUCCESS;
    }
}
