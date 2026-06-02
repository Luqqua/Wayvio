<?php

namespace App\Console\Commands;

use App\Console\Commands\Mod\BaseModCommand;
use Illuminate\Console\Command;
use Modules\Partners\Services\PartnerManager;

class PartnersApproveCommissionsCommand extends BaseModCommand
{
    protected $signature = 'partners:approve-commissions';

    protected $description = 'Release matured pending partner commissions into the approved state.';

    public function handle(PartnerManager $partnerManager): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $updated = $partnerManager->approveMaturedCommissions();
        $this->info("Approved {$updated} commission entries.");

        return Command::SUCCESS;
    }
}
