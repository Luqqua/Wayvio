<?php

namespace App\Console\Commands\Mod;

use App\Models\User;
use App\Models\UserData;
use App\Services\Templates\TemplateCatalogService;
use Illuminate\Console\Command;

class TemplateAssignCommand extends BaseModCommand
{
    protected $signature = 'template:assign {user_id} {template_id} {variant_id?}';
    protected $description = 'Globales Template (und optional Variante) einem User zuweisen.';


    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $user = User::find($this->argument('user_id'));
        if (!$user) {
            $this->error('User nicht gefunden.');
            return Command::FAILURE;
        }

        $templateCatalog = app(TemplateCatalogService::class);
        $templateId = (string) $this->argument('template_id');
        $template = $templateCatalog->template($templateId);

        if (!$template) {
            $this->error('Template nicht gefunden oder nicht freigegeben: '.$templateId);
            return Command::FAILURE;
        }

        $variantInput = $this->argument('variant_id');
        $variantId = $templateCatalog->normalizeVariantId((string) $template['id'], is_string($variantInput) ? $variantInput : null);
        $theme = (string) ($template['theme'] ?? 'default');

        $user->theme = $theme;
        $user->save();

        UserData::saveData($user->id, 'template', (string) $template['id']);
        UserData::saveData($user->id, 'theme_template_id', (string) $template['id']);
        UserData::saveData($user->id, 'theme_variant_id', (string) ($variantId ?? $template['default_variant_id'] ?? 'default'));
        UserData::saveData($user->id, 'background_mode', $theme === 'default' ? 'color' : 'template');
        UserData::saveData($user->id, 'template_background_mode', 'default');

        $this->info(sprintf(
            "Template '%s' (theme=%s, variant=%s) zugewiesen an User #%d.",
            (string) $template['id'],
            $theme,
            (string) ($variantId ?? $template['default_variant_id'] ?? 'default'),
            (int) $user->id
        ));
        return Command::SUCCESS;
    }
}
