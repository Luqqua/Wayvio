<?php

namespace App\Console\Commands;

use App\Console\Commands\Mod\BaseModCommand;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Modules\Partners\Services\PartnerManager;

class PartnersDeactivateCommand extends BaseModCommand
{
    protected $signature = 'partners:deactivate {user_id : The partner user ID to suspend}';

    protected $description = 'Suspend a partner account and disable future new partner accruals.';

    public function handle(PartnerManager $partnerManager): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        try {
            $partner = $partnerManager->deactivatePartner((int) $this->argument('user_id'));
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'Validation failed.';
            $this->error($message);
            return Command::FAILURE;
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());
            return Command::FAILURE;
        }

        $this->info("Partner account for user #{$partner->user_id} suspended.");

        return Command::SUCCESS;
    }
}
