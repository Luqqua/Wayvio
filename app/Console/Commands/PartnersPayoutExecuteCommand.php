<?php

namespace App\Console\Commands;

use App\Console\Commands\Mod\BaseModCommand;
use App\Services\Partners\PartnersClient;
use Illuminate\Console\Command;

class PartnersPayoutExecuteCommand extends BaseModCommand
{
    protected $signature = 'partners:payout-execute
        {batch_id : Prepared payout batch ID}';

    protected $description = 'Create a Stripe transfer for a prepared partner payout batch through internal APIs.';

    public function handle(PartnersClient $partnersClient): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        if (!$partnersClient->enabled()) {
            $this->error('Partners internal API is disabled.');
            return Command::FAILURE;
        }

        $batchId = (int) $this->argument('batch_id');
        if ($batchId < 1) {
            $this->error('The batch_id argument must be a positive integer.');
            return Command::FAILURE;
        }

        $response = $partnersClient->executePayout($batchId);
        if (!$response) {
            $this->error('Payout execute request failed: partners internal API unavailable.');
            return Command::FAILURE;
        }

        $statusCode = (int) ($response['_status'] ?? 200);
        $errorCode = data_get($response, 'error.code');
        $errorMessage = data_get($response, 'error.message');

        if ($statusCode >= 400 || (is_string($errorCode) && $errorCode !== '')) {
            $this->error(sprintf(
                'Payout execute failed%s%s',
                is_string($errorCode) && $errorCode !== '' ? " [{$errorCode}]" : '',
                is_string($errorMessage) && $errorMessage !== '' ? ": {$errorMessage}" : '.'
            ));
            return Command::FAILURE;
        }

        $this->info(sprintf(
            'Transfer created for batch #%d: %s (%s).',
            (int) ($response['batch_id'] ?? $batchId),
            (string) ($response['transfer_id'] ?? 'n/a'),
            (string) ($response['status'] ?? 'ok')
        ));

        if (isset($response['transfer_group']) && is_string($response['transfer_group'])) {
            $this->line('Transfer group: ' . $response['transfer_group']);
        }

        $this->line('Batch remains pending until manual settle is confirmed.');

        return Command::SUCCESS;
    }
}
