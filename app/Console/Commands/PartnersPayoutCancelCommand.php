<?php

namespace App\Console\Commands;

use App\Console\Commands\Mod\BaseModCommand;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Modules\Partners\Services\PartnerManager;

class PartnersPayoutCancelCommand extends BaseModCommand
{
    protected $signature = 'partners:payout-cancel
        {batch_id : Prepared payout batch ID}';

    protected $description = 'Cancel a prepared manual partner payout batch and release its ledger entries back to approved.';

    public function handle(PartnerManager $partnerManager): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        try {
            $batch = $partnerManager->cancelManualPayoutBatch((int) $this->argument('batch_id'));
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'Validation failed.';
            $this->error($message);
            return Command::FAILURE;
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());
            return Command::FAILURE;
        }

        $this->info(sprintf(
            'Payout batch #%d canceled. Ledger entries are payout-ready again.',
            $batch->id,
        ));

        return Command::SUCCESS;
    }
}
