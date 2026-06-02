<?php

namespace App\Console\Commands;

use App\Console\Commands\Mod\BaseModCommand;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Modules\Partners\Services\PartnerManager;

class PartnersPayoutSettleCommand extends BaseModCommand
{
    protected $signature = 'partners:payout-settle
        {batch_id : Prepared payout batch ID}
        {--reference= : External payout reference (recommended: Stripe transfer or payout ID)}
        {--paid-at= : Optional paid timestamp override}';

    protected $description = 'Mark a prepared partner payout batch as paid after Stripe transfer verification passes.';

    public function handle(PartnerManager $partnerManager): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $paidAt = null;
        $paidAtOption = $this->option('paid-at');
        if (is_string($paidAtOption) && trim($paidAtOption) !== '') {
            try {
                $paidAt = CarbonImmutable::parse($paidAtOption);
            } catch (\Throwable) {
                $this->error('The --paid-at value could not be parsed.');
                return Command::FAILURE;
            }
        }

        try {
            $batch = $partnerManager->settleManualPayoutBatch(
                (int) $this->argument('batch_id'),
                (string) $this->option('reference'),
                $paidAt,
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'Validation failed.';
            $this->error($message);
            return Command::FAILURE;
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());
            return Command::FAILURE;
        }

        $this->info(sprintf(
            'Payout batch #%d marked as paid: %0.2f %s.',
            $batch->id,
            ((int) $batch->net_amount_cents) / 100,
            strtoupper((string) $batch->currency),
        ));
        $this->line('Reference: ' . $batch->stripe_transfer_id);

        return Command::SUCCESS;
    }
}
