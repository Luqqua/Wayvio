<?php

namespace App\Console\Commands;

use App\Console\Commands\Mod\BaseModCommand;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Modules\Partners\Services\PartnerManager;

class PartnersPayoutPrepareCommand extends BaseModCommand
{
    protected $signature = 'partners:payout-prepare
        {partner_id : Partner user ID}
        {--dry-run : Only show payout-ready balances without creating payout batches}';

    protected $description = 'Prepare manual partner payout batches from approved commission balances.';

    public function handle(PartnerManager $partnerManager): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $partnerId = (int) $this->argument('partner_id');

        try {
            if ((bool) $this->option('dry-run')) {
                $preview = $partnerManager->manualPayoutPreview($partnerId);
                $partner = $preview['partner'];
                $openBatch = $preview['open_batch'];
                $groups = collect($preview['groups']);

                $this->line("Partner #{$partner->user_id} Stripe Connect: {$partner->stripe_connect_account_id}");
                if ($openBatch) {
                    $this->warn(sprintf(
                        'Open payout batch #%d already exists: %0.2f %s (%s).',
                        $openBatch->id,
                        ((int) $openBatch->net_amount_cents) / 100,
                        strtoupper((string) $openBatch->currency),
                        $openBatch->status,
                    ));
                }

                $eligible = $groups->where('eligible', true)->values();
                if ($eligible->isEmpty()) {
                    $this->line('No payout-ready balances found.');
                    return Command::SUCCESS;
                }

                foreach ($eligible as $group) {
                    $this->line(sprintf(
                        'Ready: %0.2f %s (%d entries).',
                        ((int) $group['net_amount_cents']) / 100,
                        strtoupper((string) $group['currency']),
                        (int) $group['entry_count'],
                    ));
                }

                return Command::SUCCESS;
            }

            $batches = $partnerManager->prepareManualPayoutBatches($partnerId);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'Validation failed.';
            $this->error($message);
            return Command::FAILURE;
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());
            return Command::FAILURE;
        }

        foreach ($batches as $batch) {
            $this->info(sprintf(
                'Prepared payout batch #%d for partner #%d: %0.2f %s.',
                $batch->id,
                $batch->partner_user_id,
                ((int) $batch->net_amount_cents) / 100,
                strtoupper((string) $batch->currency),
            ));
        }

        $this->line('Create the transfer manually in the Stripe Dashboard, then finalize with:');
        foreach ($batches as $batch) {
            $this->line(sprintf(
                'php artisan partners:payout-settle %d --reference=<stripe_transfer_or_payout_id>',
                $batch->id,
            ));
        }

        return Command::SUCCESS;
    }
}
