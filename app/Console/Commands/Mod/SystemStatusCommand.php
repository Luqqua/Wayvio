<?php

namespace App\Console\Commands\Mod;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SystemStatusCommand extends BaseModCommand
{
    protected $signature = 'system:status';
    protected $description = 'Systemstatus anzeigen.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $version = @file_get_contents(base_path('version.json')) ?: 'unknown';
        $dbOk = false;
        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        $uploadBudget = disk_free_space(storage_path()) ?: 0;

        $this->line('Version: '.$version);
        $this->line('DB-Verbindung: '.($dbOk ? 'ok' : 'fehlt'));
        $this->line('Upload-Budget (MB): '.round($uploadBudget/1024/1024, 2));

        return Command::SUCCESS;
    }
}
