<?php

namespace App\Console\Commands\Mod;

use App\Models\PageReport;
use Illuminate\Console\Command;

class ReportListCommand extends BaseModCommand
{
    protected $signature = 'report:list {--page=1} {--status=all} {--user_id=} {--with-deleted}';
    protected $description = 'Meldungen nach Anzahl sortiert anzeigen.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $status = strtolower((string) $this->option('status'));
        if (!in_array($status, ['all', PageReport::STATUS_OPEN, PageReport::STATUS_PROCESSED], true)) {
            $this->error('Ungültiger Status. Erlaubt: all, open, processed');
            return Command::FAILURE;
        }

        $userId = $this->option('user_id');
        if ($userId !== null && $userId !== '' && !ctype_digit((string) $userId)) {
            $this->error('user_id muss numerisch sein.');
            return Command::FAILURE;
        }

        $query = $this->option('with-deleted')
            ? PageReport::withTrashed()
            : PageReport::query();

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($userId !== null && $userId !== '') {
            $query->where('reported_user_id', (int) $userId);
        }

        $page = max(1, (int) $this->option('page'));
        $perPage = 50;

        $reports = $query
            ->orderByDesc('report_count')
            ->orderByDesc('last_reported_at')
            ->forPage($page, $perPage)
            ->get();

        if ($reports->isEmpty()) {
            $this->info('Keine Meldungen gefunden.');
            return Command::SUCCESS;
        }

        foreach ($reports as $report) {
            $desc = trim((string) ($report->last_report_message ?? ''));
            $desc = $desc === '' ? '-' : mb_strimwidth($desc, 0, 40, '...');
            $this->line(sprintf(
                'report_id=%s | user=%s | page=%s | count=%s | status=%s | type=%s | desc=%s | last=%s | deleted=%s',
                $report->id,
                $report->reported_user_id,
                $report->reported_page_name_snapshot,
                $report->report_count,
                $report->status,
                $report->last_report_type ?? '-',
                $desc,
                optional($report->last_reported_at)->toDateTimeString() ?? '-',
                $this->boolLabel($report->trashed())
            ));
        }

        $this->line(sprintf('Seite %d, max %d pro Seite.', $page, $perPage));
        return Command::SUCCESS;
    }
}
