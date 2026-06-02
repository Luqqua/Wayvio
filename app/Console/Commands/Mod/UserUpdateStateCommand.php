<?php

namespace App\Console\Commands\Mod;

use App\Models\User;
use Illuminate\Console\Command;

class UserUpdateStateCommand extends BaseModCommand
{
    protected $signature = 'user:update-state {user_id} {--status= : yes|no|blocked|active} {--verification= : verified|unverified|pending}';
    protected $description = 'Status (block) und/oder Verification eines Users manuell setzen.';

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

        $statusInput = $this->option('status');
        $verificationInput = $this->option('verification');

        if ($statusInput === null && $verificationInput === null) {
            $this->error('Bitte mindestens eine Option setzen: --status oder --verification.');
            return Command::FAILURE;
        }

        $status = $this->normalizeStatus($statusInput);
        if ($statusInput !== null && $status === null) {
            $this->error('Ungültiger Status. Erlaubt: yes|no (auch blocked|active).');
            return Command::FAILURE;
        }

        $verification = $this->normalizeVerification($verificationInput);
        if ($verificationInput !== null && $verification === null) {
            $this->error('Ungültige Verification. Erlaubt: verified|unverified|pending.');
            return Command::FAILURE;
        }

        if ($status !== null) {
            $user->block = $status;
        }

        if ($verification !== null) {
            $user->email_verified_at = $verification ? now() : null;
        }

        if (!$user->isDirty()) {
            $this->info('Keine Änderung notwendig.');
            return Command::SUCCESS;
        }

        $user->save();

        $this->line('User #' . $user->id . ' aktualisiert:');
        $this->line('Status (block): ' . $user->block);
        $this->line('Verification: ' . ($user->email_verified_at ? 'verified' : 'unverified'));

        return Command::SUCCESS;
    }

    private function normalizeStatus(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        return match ($value) {
            'yes', 'blocked', 'block', 'pending' => 'yes',
            'no', 'active', 'approved', 'unblocked' => 'no',
            default => null,
        };
    }

    private function normalizeVerification(?string $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        $value = strtolower(trim($value));
        return match ($value) {
            'verified', 'verify', 'yes', 'true', '1' => true,
            'unverified', 'pending', 'no', 'false', '0' => false,
            default => null,
        };
    }
}
