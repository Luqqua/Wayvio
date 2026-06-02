<?php

namespace App\Console\Commands;

use App\Console\Commands\Mod\BaseModCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PartnersBackfillLedgerBillingReasonCommand extends BaseModCommand
{
    private const ELIGIBLE_REASONS = ['subscription_create', 'subscription_cycle'];

    protected $signature = 'partners:backfill-ledger-billing-reasons
        {--dry-run : Show what would change without writing updates}
        {--limit=5000 : Maximum commission ledger rows to inspect}';

    protected $description = 'Backfill partner commission ledger billing reasons from stored Stripe invoice.paid webhook payloads.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        if (!Schema::hasTable('partner_commission_ledger') || !Schema::hasTable('billing_webhook_events')) {
            $this->error('Required tables are missing (partner_commission_ledger or billing_webhook_events).');

            return Command::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, min(50000, (int) $this->option('limit')));

        $rows = DB::table('partner_commission_ledger')
            ->select(['id', 'source_invoice_id', 'meta'])
            ->where('entry_type', 'commission')
            ->where('gross_amount_cents', '>', 0)
            ->whereNotNull('source_invoice_id')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No commission rows found for inspection.');

            return Command::SUCCESS;
        }

        $candidates = $rows->filter(function ($row): bool {
            $meta = $this->decodeMeta($row->meta);

            return !array_key_exists('invoice_billing_reason', $meta);
        })->values();

        if ($candidates->isEmpty()) {
            $this->info('No rows require billing reason backfill.');

            return Command::SUCCESS;
        }

        $invoiceIds = $candidates
            ->pluck('source_invoice_id')
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->unique()
            ->values()
            ->all();

        $reasonMap = $this->invoiceBillingReasonMap($invoiceIds);
        $nowIso = now()->toISOString();
        $updated = 0;
        $unknown = 0;

        foreach ($candidates as $row) {
            $invoiceId = is_string($row->source_invoice_id) ? trim($row->source_invoice_id) : '';
            $reason = $invoiceId !== '' ? ($reasonMap[$invoiceId] ?? null) : null;
            $eligible = in_array($reason, self::ELIGIBLE_REASONS, true);
            $meta = $this->decodeMeta($row->meta);

            $meta['invoice_billing_reason'] = $reason;
            $meta['payment_gate_eligible'] = $eligible;
            $meta['payment_gate_rule'] = $reason === null
                ? 'excluded_missing_reason'
                : ($eligible ? 'create_or_cycle' : 'excluded');
            $meta['billing_reason_source'] = 'billing_webhook_events_backfill';
            $meta['billing_reason_backfilled_at'] = $nowIso;

            if ($reason === null) {
                $unknown++;
            }

            if (!$dryRun) {
                DB::table('partner_commission_ledger')
                    ->where('id', (int) $row->id)
                    ->update([
                        'meta' => json_encode($meta, JSON_UNESCAPED_SLASHES),
                        'updated_at' => now(),
                    ]);
            }

            $updated++;
        }

        $this->info(sprintf(
            '%s %d row(s); unresolved billing reason for %d row(s).',
            $dryRun ? 'Would backfill' : 'Backfilled',
            $updated,
            $unknown,
        ));

        if ($dryRun) {
            $this->line('Dry-run mode: no database changes were written.');
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<int,string> $invoiceIds
     * @return array<string,string|null>
     */
    private function invoiceBillingReasonMap(array $invoiceIds): array
    {
        if ($invoiceIds === []) {
            return [];
        }

        $lookup = array_fill_keys($invoiceIds, true);
        $resolved = [];
        $events = DB::table('billing_webhook_events')
            ->where('event_type', 'invoice.paid')
            ->whereNotNull('payload')
            ->orderByDesc('id')
            ->get(['payload']);

        foreach ($events as $event) {
            $payload = json_decode((string) ($event->payload ?? ''), true);
            if (!is_array($payload)) {
                continue;
            }

            $invoiceId = strtolower(trim((string) data_get($payload, 'data.object.id', '')));
            if ($invoiceId === '' || !isset($lookup[$invoiceId]) || array_key_exists($invoiceId, $resolved)) {
                continue;
            }

            $billingReason = strtolower(trim((string) data_get($payload, 'data.object.billing_reason', '')));
            $resolved[$invoiceId] = $billingReason !== '' ? $billingReason : null;

            if (count($resolved) >= count($lookup)) {
                break;
            }
        }

        $result = [];
        foreach ($invoiceIds as $invoiceId) {
            $key = strtolower(trim($invoiceId));
            $result[$invoiceId] = $resolved[$key] ?? null;
        }

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeMeta(mixed $rawMeta): array
    {
        if (is_array($rawMeta)) {
            return $rawMeta;
        }

        if (is_string($rawMeta) && trim($rawMeta) !== '') {
            $decoded = json_decode($rawMeta, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}
