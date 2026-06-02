<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ComplianceAuditLogPruneCommand extends Command
{
    protected $signature = 'compliance:prune-audit-log {--days= : Retention in days (overrides COMPLIANCE_AUDIT_LOG_RETENTION_DAYS)}';
    protected $description = 'Delete compliance_audit_log entries older than the configured retention period (DSGVO Art. 5 Abs. 1 lit. e).';

    public function handle(): int
    {
        if (!Schema::hasTable('compliance_audit_log')) {
            $this->warn('Table compliance_audit_log does not exist – skipping.');
            return self::SUCCESS;
        }

        $days = (int) ($this->option('days') ?: config('compliance.audit_log_retention_days', 365));

        if ($days <= 0) {
            $this->info('Retention disabled (days=0) – nothing deleted.');
            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $deleted = DB::table('compliance_audit_log')
            ->where('created_at', '<', $cutoff)
            ->delete();

        Log::info('Compliance audit log pruned', [
            'job' => 'compliance:prune-audit-log',
            'retention_days' => $days,
            'cutoff' => $cutoff->toIso8601String(),
            'deleted' => $deleted,
        ]);

        $this->info("Deleted {$deleted} compliance_audit_log entries older than {$days} days.");
        return self::SUCCESS;
    }
}
