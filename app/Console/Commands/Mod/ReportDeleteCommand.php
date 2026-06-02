<?php

namespace App\Console\Commands\Mod;

use App\Models\PageReport;
use Illuminate\Console\Command;

class ReportDeleteCommand extends BaseModCommand
{
    protected $signature = 'report:delete {report_id} {--force}';
    protected $description = 'Meldung löschen (Soft-Delete, optional Force-Delete).';

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

        if ($this->option('force')) {
            $report->forceDelete();
            $this->info("Meldung #{$report->id} endgültig gelöscht.");
            return Command::SUCCESS;
        }

        if ($report->trashed()) {
            $this->info("Meldung #{$report->id} ist bereits soft-gelöscht.");
            return Command::SUCCESS;
        }

        $report->delete();
        $this->info("Meldung #{$report->id} soft-gelöscht.");
        return Command::SUCCESS;
    }
}
