<?php

namespace App\Console\Commands\Mod;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;

class BackupListCommand extends BaseModCommand
{
    protected $signature = 'backup:list {--disk=backups}';
    protected $description = 'Liste der Backups anzeigen.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $disk = $this->option('disk') ?: 'backups';
        $driver = Storage::disk($disk)->getDriver();

        $files = collect();
        foreach ($driver->listContents('', true) as $item) {
            if (!($item instanceof FileAttributes)) {
                continue;
            }
            $files->push([
                'path' => $item->path(),
                'size' => (int) ($item->fileSize() ?? 0),
                'updated' => (int) ($item->lastModified() ?? 0),
            ]);
        }

        $files = $files->sortByDesc('updated')->values();

        if ($files->isEmpty()) {
            $this->info('Keine Backups gefunden.');
            return Command::SUCCESS;
        }

        foreach ($files as $file) {
            $this->line(sprintf(
                '%s | %s KB | %s',
                $file['path'],
                round(((int) $file['size']) / 1024, 1),
                date('c', (int) $file['updated'])
            ));
        }

        return Command::SUCCESS;
    }
}
