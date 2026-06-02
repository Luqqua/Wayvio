<?php

namespace App\Console\Commands\Mod;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ThemeAssignCommand extends BaseModCommand
{
    protected $signature = 'theme:assign {user_id} {theme_id}';
    protected $description = 'Theme / Module zuweisen.';


    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $user = User::find($this->argument('user_id'));
        if (!$user) {
            $this->error('User nicht gefunden.');
            return Command::FAILURE;
        }

        $theme = $this->argument('theme_id');
        $themePath = base_path('themes/'.$theme);
        if (!File::exists($themePath)) {
            $this->error('Theme nicht gefunden: '.$theme);
            return Command::FAILURE;
        }

        $user->theme = $theme;
        $user->save();

        $this->info("Theme '{$theme}' zugewiesen an User #{$user->id}.");
        return Command::SUCCESS;
    }
}
