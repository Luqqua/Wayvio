<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PrivacyPruneOperationalDataCommand extends Command
{
    protected $signature = 'privacy:prune-operational-data {--dry-run : Report counts without deleting rows}';

    protected $description = 'Prune operational personal data from sessions, logs, webhooks and notification tables.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $summary = [];

        $summary['sessions'] = $this->pruneSessions(
            $this->hours('sessions_hours', 26),
            $dryRun
        );
        $summary['admin_login_attempts'] = $this->pruneDateTable(
            'admin_login_attempts',
            'last_attempt_at',
            now()->subHours($this->hours('admin_login_attempt_hours', 24)),
            $dryRun
        );
        $summary['billing_webhook_events_payloads_redacted'] = $this->redactJsonPayloads(
            'billing_webhook_events',
            'payload',
            'created_at',
            now()->subDays($this->days('billing_webhook_payload_redaction_days', 90)),
            $dryRun
        );
        $summary['stripe_webhook_logs_payloads_redacted'] = $this->redactJsonPayloads(
            'stripe_webhook_logs',
            'payload',
            'created_at',
            now()->subDays($this->days('stripe_webhook_payload_redaction_days', 90)),
            $dryRun
        );
        $summary['partner_webhook_events_payloads_redacted'] = $this->redactJsonPayloads(
            'partner_webhook_events',
            'payload',
            'created_at',
            now()->subDays($this->days('partner_webhook_payload_redaction_days', 90)),
            $dryRun
        );

        foreach ([
            'audit_log' => ['created_at', 'audit_log_days', 365],
            'admin_audit_log' => ['created_at', 'admin_audit_log_days', 365],
            'partner_audit_log' => ['created_at', 'partner_audit_log_days', 365],
            'account_deletion_audit_log' => ['created_at', 'account_deletion_audit_log_days', 365],
            'billing_webhook_events' => ['created_at', 'billing_webhook_event_days', 730],
            'stripe_webhook_logs' => ['created_at', 'stripe_webhook_log_days', 730],
            'partner_webhook_events' => ['created_at', 'partner_webhook_event_days', 730],
            'billing_notification_outbox' => ['created_at', 'billing_notification_outbox_days', 365],
        ] as $table => [$column, $configKey, $defaultDays]) {
            $summary[$table] = $this->pruneDateTable(
                $table,
                $column,
                now()->subDays($this->days($configKey, $defaultDays)),
                $dryRun
            );
        }

        Log::info('Operational privacy data pruned', [
            'job' => 'privacy:prune-operational-data',
            'dry_run' => $dryRun,
            'summary' => $summary,
        ]);

        foreach ($summary as $table => $deleted) {
            $this->line(sprintf('%s=%d', $table, $deleted));
        }

        return self::SUCCESS;
    }

    private function pruneSessions(int $retentionHours, bool $dryRun): int
    {
        if (!Schema::hasTable('sessions') || !Schema::hasColumn('sessions', 'last_activity')) {
            return 0;
        }

        $cutoffUnix = now()->subHours($retentionHours)->getTimestamp();
        $query = DB::table('sessions')->where('last_activity', '<', $cutoffUnix);
        $count = (int) $query->count();
        if ($dryRun || $count === 0) {
            return $count;
        }

        return (int) $query->delete();
    }

    private function pruneDateTable(string $table, string $column, \DateTimeInterface $cutoff, bool $dryRun): int
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return 0;
        }

        $query = DB::table($table)->where($column, '<', $cutoff);
        $count = (int) $query->count();
        if ($dryRun || $count === 0) {
            return $count;
        }

        return (int) $query->delete();
    }

    private function redactJsonPayloads(string $table, string $payloadColumn, string $dateColumn, \DateTimeInterface $cutoff, bool $dryRun): int
    {
        if (
            !Schema::hasTable($table)
            || !Schema::hasColumn($table, $payloadColumn)
            || !Schema::hasColumn($table, $dateColumn)
        ) {
            return 0;
        }

        $query = DB::table($table)
            ->where($dateColumn, '<', $cutoff)
            ->whereNotNull($payloadColumn)
            ->whereRaw("JSON_EXTRACT(`{$payloadColumn}`, '$.redacted') IS NULL");

        $count = (int) $query->count();
        if ($dryRun || $count === 0) {
            return $count;
        }

        return (int) $query->update([
            $payloadColumn => json_encode([
                'redacted' => true,
                'redacted_at' => now()->toIso8601String(),
                'reason' => 'privacy_payload_retention',
            ], JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function days(string $key, int $default): int
    {
        return max(1, (int) config('compliance.operational_retention.' . $key, $default));
    }

    private function hours(string $key, int $default): int
    {
        return max(1, (int) config('compliance.operational_retention.' . $key, $default));
    }
}
