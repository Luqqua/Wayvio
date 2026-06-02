<?php

namespace App\Console\Commands\Mod;

use App\Models\Link;
use Illuminate\Console\Command;

class LinkToggleCommand extends BaseModCommand
{
    protected $signature = 'link:toggle {link_id} {action : enable|disable}';
    protected $description = 'Link sperren oder freigeben.';


    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $link = Link::withDisabled()->find($this->argument('link_id'));
        if (!$link) {
            $this->error('Link nicht gefunden.');
            return Command::FAILURE;
        }

        $action = $this->argument('action');
        if (!in_array($action, ['enable', 'disable'], true)) {
            $this->error('Aktion muss enable oder disable sein.');
            return Command::FAILURE;
        }

        $link->is_disabled = $action === 'disable';
        $link->save();

        $this->info(sprintf('Link #%s %s.', $link->id, $action === 'disable' ? 'gesperrt' : 'freigegeben'));
        return Command::SUCCESS;
    }
}
