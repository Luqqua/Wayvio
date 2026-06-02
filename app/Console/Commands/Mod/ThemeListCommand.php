<?php

namespace App\Console\Commands\Mod;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ThemeListCommand extends BaseModCommand
{
    protected $signature = 'theme:list';
    protected $description = 'Verfügbare Themes / Module anzeigen.';


    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $themePath = base_path('themes');
        if (!File::exists($themePath)) {
            $this->warn('Kein Theme-Ordner gefunden.');
            return Command::SUCCESS;
        }

        $themes = collect(File::directories($themePath))->map(fn($dir) => basename($dir));
        if ($themes->isEmpty()) {
            $this->info('Keine Themes vorhanden.');
            return Command::SUCCESS;
        }

        foreach ($themes as $theme) {
            $this->line($theme);
        }

        return Command::SUCCESS;
    }
}
