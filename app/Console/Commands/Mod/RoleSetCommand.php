<?php

namespace App\Console\Commands\Mod;

use App\Models\User;
use Illuminate\Console\Command;

class RoleSetCommand extends BaseModCommand
{
    protected $signature = 'role:set {user_id} {role}';
    protected $description = 'Rolle eines Users setzen (admin, vip, user).';

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

        $role = strtolower($this->argument('role'));
        $allowed = ['admin', 'vip', 'user'];
        if (!in_array($role, $allowed, true)) {
            $this->error('Ungültige Rolle. Erlaubt: admin, vip, user.');
            return Command::FAILURE;
        }

        $user->role = $role;
        $user->save();

        $this->info("Role auf {$role} gesetzt für User #{$user->id}.");
        return Command::SUCCESS;
    }
}
