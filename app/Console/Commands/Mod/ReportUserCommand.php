<?php

namespace App\Console\Commands\Mod;

use App\Models\PageReport;
use Illuminate\Console\Command;

class ReportUserCommand extends BaseModCommand
{
    protected $signature = 'report:user {user_id} {--with-deleted}';
    protected $description = 'Prüfen, ob eine Seite eines Users gemeldet wurde.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $userId = (int) $this->argument('user_id');
        if ($userId <= 0) {
            $this->error('user_id muss > 0 sein.');
            return Command::FAILURE;
        }

        $query = $this->option('with-deleted')
            ? PageReport::withTrashed()
            : PageReport::query();

        $report = $query->where('reported_user_id', $userId)->first();
        if (!$report) {
            $this->info("Für User #{$userId} liegt keine Meldung vor.");
            return Command::SUCCESS;
        }

        $this->line('Meldung gefunden:');
        $this->line('ID: ' . $report->id);
        $this->line('User: #' . $report->reported_user_id);
        $this->line('Page: ' . $report->reported_page_name_snapshot);
        $this->line('URL Snapshot: ' . $report->reported_page_url_snapshot);
        $this->line('Anzahl Meldungen: ' . $report->report_count);
        $this->line('Status: ' . $report->status);
        $this->line('Letzter Grund: ' . ($report->last_report_type ?? '-'));
        $this->line('Beschreibung (erste Meldung): ' . ($report->last_report_message ?? '-'));
        $this->line('Erste Meldung: ' . (optional($report->first_reported_at)->toDateTimeString() ?? '-'));
        $this->line('Letzte Meldung: ' . (optional($report->last_reported_at)->toDateTimeString() ?? '-'));
        $this->line('Moderator-Kommentar: ' . ($report->moderator_comment ?? '-'));
        $this->line('Gelöscht: ' . $this->boolLabel($report->trashed()));

        return Command::SUCCESS;
    }
}
