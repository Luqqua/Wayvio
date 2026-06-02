<?php

namespace App\Console\Commands\Mod;

use Illuminate\Console\Command;
use Modules\Tiers\Models\UserSubscription;

class SubscriptionListCommand extends BaseModCommand
{
    protected $signature = 'subscription:list';
    protected $description = 'Alle Abos anzeigen';


    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $subs = UserSubscription::with(['user:id,name,email', 'tier:id,name,slug'])->get();
        if ($subs->isEmpty()) {
            $this->info('Keine Abos gefunden.');
            return Command::SUCCESS;
        }

        foreach ($subs as $sub) {
            $this->line(sprintf(
                'User #%s (%s) | Plan=%s | Start=%s | Ende=%s',
                $sub->user_id,
                $sub->user?->name,
                $sub->tier?->slug ?? 'free',
                optional($sub->created_at)->toDateString(),
                optional($sub->expires_at)->toDateString() ?: 'offen'
            ));
        }

        return Command::SUCCESS;
    }
}
