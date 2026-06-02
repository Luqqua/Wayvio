<?php

namespace App\Console\Commands;

use App\Console\Commands\Mod\BaseModCommand;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Partners\Models\PartnerAccount;
use Modules\Partners\Services\PartnerManager;

class PartnersActivateCommand extends BaseModCommand
{
    protected $signature = 'partners:activate
        {user_id : The user ID to activate as a partner}
        {--rate-bps=3000 : Default commission rate in basis points}
        {--connect= : Stripe Connect account ID}';

    protected $description = 'Activate or update a partner account for a user.';

    public function handle(PartnerManager $partnerManager): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $user = User::query()->find($this->argument('user_id'));
        if (!$user) {
            $this->error('User not found.');
            return Command::FAILURE;
        }

        $rateBps = (int) $this->option('rate-bps');
        if ($rateBps < 1 || $rateBps > 10000) {
            $this->error('rate-bps must be between 1 and 10000.');
            return Command::FAILURE;
        }

        $existingStatus = null;
        if (Schema::hasTable('partner_accounts')) {
            $existingStatus = strtolower((string) (PartnerAccount::query()
                ->where('user_id', $user->id)
                ->value('status') ?? ''));
        }

        if (in_array($existingStatus, ['active', 'restricted', 'pending'], true)) {
            $this->error(sprintf(
                'User #%d (%s) is already a partner (status: %s).',
                $user->id,
                $user->email,
                $existingStatus,
            ));
            $this->line('No changes applied.');
            return Command::FAILURE;
        }

        try {
            $partner = $partnerManager->activatePartner(
                (int) $user->id,
                $rateBps,
                $this->option('connect') ? (string) $this->option('connect') : null,
            );
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());
            return Command::FAILURE;
        }

        $this->info("Partner activated for user #{$user->id} ({$user->email}).");
        $this->line('Commission rate: ' . $partner->default_commission_rate_bps . ' bps');
        $this->line('Dashboard access: /dashboard/partner');
        if (!$partner->stripe_connect_account_id) {
            $this->line('Stripe onboarding can now be started from the partner dashboard.');
        }

        return Command::SUCCESS;
    }
}
