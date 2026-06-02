<?php

namespace App\Console\Commands\Mod;

use App\Models\PageReport;
use Illuminate\Console\Command;

class ReportCommentCommand extends BaseModCommand
{
    protected $signature = 'report:comment {report_id} {comment?} {--clear}';
    protected $description = 'Kommentar für eine Meldung setzen (max 40 Zeichen).';

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
            $this->error('Meldung ist gelöscht. Kommentar nicht möglich.');
            return Command::FAILURE;
        }

        if ($this->option('clear')) {
            $report->moderator_comment = null;
            $report->save();
            $this->info("Kommentar für Meldung #{$report->id} gelöscht.");
            return Command::SUCCESS;
        }

        $comment = trim((string) $this->argument('comment'));
        if ($comment === '') {
            $this->error('Kommentar fehlt. Oder nutze --clear zum Löschen.');
            return Command::FAILURE;
        }

        if (mb_strlen($comment) > 40) {
            $this->error('Kommentar darf maximal 40 Zeichen haben.');
            return Command::FAILURE;
        }

        $report->moderator_comment = $comment;
        $report->save();

        $this->info("Kommentar für Meldung #{$report->id} gespeichert.");
        return Command::SUCCESS;
    }
}
