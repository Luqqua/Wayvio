<?php

namespace App\Console\Commands\Mod;

use App\Models\PageReport;
use App\Models\User;
use Illuminate\Console\Command;

class ReportProcessedCommand extends BaseModCommand
{
    protected $signature = 'report:processed {report_id} {--undo} {--by-user=}';
    protected $description = 'Meldung als bearbeitet markieren oder zurücksetzen.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $report = PageReport::withTrashed()->find((int) $this->argument('report_id'));
        if (!$report) {
            $this->error('Meldung nicht gefunden.');
            return Command::FAILURE;
        }
        if ($report->trashed()) {
            $this->error('Meldung ist gelöscht. Erst wiederherstellen.');
            return Command::FAILURE;
        }

        $processedBy = null;
        $byUserOption = $this->option('by-user');
        if ($byUserOption !== null && $byUserOption !== '') {
            if (!ctype_digit((string) $byUserOption)) {
                $this->error('--by-user muss numerisch sein.');
                return Command::FAILURE;
            }
            $processedBy = User::find((int) $byUserOption);
            if (!$processedBy) {
                $this->error('User für --by-user nicht gefunden.');
                return Command::FAILURE;
            }
        }

        if ($this->option('undo')) {
            $report->status = PageReport::STATUS_OPEN;
            $report->processed_at = null;
            $report->processed_by_user_id = null;
            $report->save();
            $this->info("Meldung #{$report->id} auf offen zurückgesetzt.");
            return Command::SUCCESS;
        }

        $report->status = PageReport::STATUS_PROCESSED;
        $report->processed_at = now();
        $report->processed_by_user_id = $processedBy?->id;
        $report->save();

        $this->info("Meldung #{$report->id} als bearbeitet markiert.");
        return Command::SUCCESS;
    }
}
