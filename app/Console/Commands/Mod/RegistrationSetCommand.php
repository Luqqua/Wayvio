<?php

namespace App\Console\Commands\Mod;

use GeoSot\EnvEditor\Facades\EnvEditor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RegistrationSetCommand extends BaseModCommand
{
    protected $signature = 'registration:set {state : enable|disable}';
    protected $description = 'Aktiviert oder deaktiviert die öffentliche Nutzer-Registrierung.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $enabled = $this->normalizeState((string) $this->argument('state'));
        if ($enabled === null) {
            $this->error('Ungültiger state. Erlaubt: enable|disable.');
            return Command::FAILURE;
        }

        $persistedEnv = $this->persistEnv($enabled);
        $persistedDb = $this->persistPagesRegister($enabled);

        $this->line('Registration enabled: ' . $this->boolLabel($enabled));
        $this->line('ENV synced: ' . $this->boolLabel($persistedEnv));
        $this->line('DB override synced: ' . $this->boolLabel($persistedDb));

        return Command::SUCCESS;
    }

    private function normalizeState(string $state): ?bool
    {
        $value = strtolower(trim($state));

        return match ($value) {
            'enable', 'enabled', 'on', 'true', '1' => true,
            'disable', 'disabled', 'off', 'false', '0' => false,
            default => null,
        };
    }

    private function persistEnv(bool $enabled): bool
    {
        $value = $enabled ? 'true' : 'false';

        if (EnvEditor::keyExists('ALLOW_REGISTRATION')) {
            EnvEditor::editKey('ALLOW_REGISTRATION', $value);
            return true;
        }

        EnvEditor::addKey('ALLOW_REGISTRATION', $value);
        return true;
    }

    private function persistPagesRegister(bool $enabled): bool
    {
        if (!Schema::hasTable('pages') || !Schema::hasColumn('pages', 'register')) {
            return false;
        }

        $value = $enabled ? 'true' : 'false';
        $affected = DB::table('pages')->update(['register' => $value]);
        if ($affected > 0) {
            return true;
        }

        $insert = ['register' => $value];
        if (Schema::hasColumn('pages', 'created_at')) {
            $insert['created_at'] = now();
        }
        if (Schema::hasColumn('pages', 'updated_at')) {
            $insert['updated_at'] = now();
        }

        try {
            DB::table('pages')->insert($insert);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}

