<?php

namespace App\Console\Commands\Mod;

use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\Exceptions\AccountDeletionBlockedException;
use Illuminate\Console\Command;

class UserDeleteCommand extends BaseModCommand
{
    protected $signature = 'user:delete {id}';
    protected $description = 'Nutzer löschen.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $id = $this->argument('id');
        $user = User::find($id);
        if (!$user) {
            $this->error('User nicht gefunden.');
            return Command::FAILURE;
        }

        $deletionService = app(AccountDeletionService::class);

        try {
            $deletionService->deleteUser($user, [
                'source' => 'mod_console',
                'deletion_reason' => 'admin',
            ]);
        } catch (AccountDeletionBlockedException $e) {
            $this->error('User konnte nicht gelöscht werden: Stripe-Abo konnte nicht sicher gekündigt werden.');
            return Command::FAILURE;
        } catch (\Throwable $e) {
            report($e);
            $this->error('User konnte nicht gelöscht werden.');
            return Command::FAILURE;
        }

        $this->info("User #{$id} gelöscht.");
        return Command::SUCCESS;
    }
}
