<?php

namespace App\Console\Commands\Mod;

use Illuminate\Console\Command;

abstract class BaseModCommand extends Command
{
    /**
     * Prüft, ob der aktuelle Befehl gemäß Config/ENV erlaubt ist.
     */
    protected function guardAllowed(): bool
    {
        $allowed = config('mod.allowed_commands', []);
        // '*' erlaubt alle
        if (in_array('*', $allowed, true)) {
            return true;
        }

        $name = $this->getName();
        if (!$allowed || !in_array($name, $allowed, true)) {
            $this->error("Command '{$name}' ist nicht freigeschaltet (IAM/ENV MOD_ALLOWED_COMMANDS).");
            return false;
        }
        return true;
    }

    protected function boolLabel(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }
}
