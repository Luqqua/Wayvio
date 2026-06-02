<?php

namespace App\Console\Commands\Mod;

use App\Services\Templates\TemplateCatalogService;
use Illuminate\Console\Command;

class TemplateListCommand extends BaseModCommand
{
    protected $signature = 'template:list';
    protected $description = 'Verfügbare globale Templates anzeigen.';


    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $catalog = app(TemplateCatalogService::class);
        $templates = $catalog->templates();
        if (empty($templates)) {
            $this->info('Keine globalen Templates vorhanden.');
            return Command::SUCCESS;
        }

        foreach ($templates as $tpl) {
            $this->line(sprintf(
                '%s | theme=%s | variants=%d',
                (string) ($tpl['id'] ?? ''),
                (string) ($tpl['theme'] ?? ''),
                count((array) ($tpl['variants'] ?? []))
            ));
        }

        return Command::SUCCESS;
    }
}
