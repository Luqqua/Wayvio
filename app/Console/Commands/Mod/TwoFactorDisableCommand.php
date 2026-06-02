<?php

namespace App\Console\Commands\Mod;

use App\Models\User;
use Illuminate\Console\ConfirmableTrait;
use App\Notifications\TwoFactorDisabledByAdminNotification;
use Illuminate\Support\Str;

class TwoFactorDisableCommand extends BaseModCommand
{
    use ConfirmableTrait;

    protected $signature = 'twofactor:disable {user_id : ID des Users} {--force : Ohne Rückfrage ausführen}';

    protected $description = 'Deaktiviert 2FA und löscht Secret/Recovery-Codes für einen User (Admin/Mod Use Only).';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return self::FAILURE;
        }

        $userId = $this->argument('user_id');
        $user = User::find($userId);

        if (!$user) {
            $this->error("User {$userId} nicht gefunden.");
            return self::FAILURE;
        }

        if (!$this->option('force') && !$this->confirm("2FA wirklich deaktivieren und Codes löschen für {$user->id} ({$user->email})?")) {
            $this->info('Abgebrochen.');
            return self::SUCCESS;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Session flags are session-bound; ensure fresh login by clearing remember token.
        $user->setRememberToken(Str::random(60));
        $user->save();

        try {
            $user->notify(new TwoFactorDisabledByAdminNotification());
        } catch (\Throwable $e) {
            $this->warn('E-Mail-Benachrichtigung konnte nicht gesendet werden.');
        }

        $this->info("2FA für User {$user->id} wurde deaktiviert und alle Secrets/Codes gelöscht.");
        return self::SUCCESS;
    }
}
