<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Tiers\Models\Tier;
use Modules\Tiers\Services\TierResolver;

class TiersSyncCommand extends Command
{
    protected $signature = 'tiers:sync {--dry-run}';
    protected $description = 'Synchronisiert Tiers aus config/tiers.php in die Datenbank.';

    public function handle(): int
    {
        $resolver = app(TierResolver::class);
        $plans = $resolver->plans();
        if ($plans->isEmpty()) {
            $this->error('Keine Tiers in der config gefunden.');
            return Command::FAILURE;
        }

        $dry = $this->option('dry-run');
        foreach ($plans as $plan) {
            $payload = $resolver->databasePayload($plan);

            if ($dry) {
                $this->line('[dry-run] würde aktualisieren: '.$plan['slug']);
                continue;
            }

            Tier::updateOrCreate(['slug' => $plan['slug']], $payload);
            $this->info('Aktualisiert: '.$plan['slug']);
        }

        // Entferne Tiers, die nicht mehr in der Config stehen
        $slugs = $plans->keys()->all();
        if (!$dry) {
            Tier::whereNotIn('slug', $slugs)->delete();
        } else {
            $this->line('[dry-run] würde entfernen: '.Tier::whereNotIn('slug', $slugs)->pluck('slug')->implode(', '));
        }

        return Command::SUCCESS;
    }
}
