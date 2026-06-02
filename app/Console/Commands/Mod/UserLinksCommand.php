<?php

namespace App\Console\Commands\Mod;

use App\Models\Link;
use App\Models\User;
use Illuminate\Console\Command;

class UserLinksCommand extends BaseModCommand
{
    protected $signature = 'user:links {id}';
    protected $description = 'Alle Links eines Users anzeigen.';


    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $user = User::find($this->argument('id'));
        if (!$user) {
            $this->error('User nicht gefunden.');
            return Command::FAILURE;
        }

        $links = Link::withDisabled()->where('user_id', $user->id)->orderBy('up_link')->orderBy('order')->get(['id','title','link','is_disabled']);
        if ($links->isEmpty()) {
            $this->info('Keine Links gefunden.');
            return Command::SUCCESS;
        }

        foreach ($links as $link) {
            $this->line(sprintf('#%s | %s | %s | disabled=%s', $link->id, $link->title, $link->link, $this->boolLabel($link->is_disabled)));
        }

        return Command::SUCCESS;
    }
}
