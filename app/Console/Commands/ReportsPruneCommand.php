<?php

namespace App\Console\Commands;

use App\Models\PageReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportsPruneCommand extends Command
{
    protected $signature = 'reports:prune {--days=}';
    protected $description = 'Alte verarbeitete/gelöschte Meldungen endgültig löschen.';

    public function handle(): int
    {
        $days = $this->option('days');
        $days = $days === null || $days === ''
            ? (int) config('reports.retention_days', 180)
            : (int) $days;

        if ($days <= 0) {
            $this->info('Retention deaktiviert (days <= 0).');
            return Command::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        $query = PageReport::withTrashed()->where(function ($q) use ($cutoff): void {
            $q->where(function ($q2) use ($cutoff): void {
                $q2->where('status', PageReport::STATUS_PROCESSED)
                    ->whereNotNull('processed_at')
                    ->where('processed_at', '<=', $cutoff);
            })->orWhere(function ($q2) use ($cutoff): void {
                $q2->whereNotNull('deleted_at')
                    ->where('deleted_at', '<=', $cutoff);
            });
        });

        $ids = $query->pluck('id');
        if ($ids->isEmpty()) {
            $this->info('Keine alten Meldungen zum Bereinigen gefunden.');
            return Command::SUCCESS;
        }

        if (Schema::hasTable('page_report_events') && Schema::hasColumn('page_report_events', 'page_report_id')) {
            DB::table('page_report_events')->whereIn('page_report_id', $ids)->delete();
        }

        $deleted = PageReport::withTrashed()->whereIn('id', $ids)->forceDelete();
        $this->info("Bereinigt: {$deleted} Meldungen.");
        return Command::SUCCESS;
    }
}
