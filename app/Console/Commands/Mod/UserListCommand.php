<?php

namespace App\Console\Commands\Mod;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class UserListCommand extends BaseModCommand
{
    protected $signature = 'user:list {--email=} {--status=} {--page=1} {--show-email}';
    protected $description = 'Alle Nutzer anzeigen (ID, Username, Rolle, Verification, Partner) – paginiert (50 pro Seite).';


    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $partnerSchemaReady = Schema::hasTable('partner_accounts');

        $query = User::query()
            ->withoutAgencyHubAccounts()
            ->select('users.id', 'users.name', 'users.email', 'users.email_verified_at', 'users.role', 'users.littlelink_name');

        if ($partnerSchemaReady) {
            $query
                ->leftJoin('partner_accounts', 'partner_accounts.user_id', '=', 'users.id')
                ->addSelect('partner_accounts.status as partner_status');
        }

        if ($email = $this->option('email')) {
            $query->where('users.email', 'like', "%{$email}%");
        }

        $statusFilterInput = $this->option('status');
        if ($statusFilterInput !== null && trim((string) $statusFilterInput) !== '') {
            $statusFilter = $this->normalizeStatus((string) $statusFilterInput);
            if ($statusFilter === null) {
                $this->error('Ungültiger Status-Filter. Erlaubt: yes|no (auch blocked|active).');
                return Command::FAILURE;
            }

            $query->where('users.block', $statusFilter);
        }

        $page = max(1, (int) $this->option('page'));
        $perPage = 50;

        $users = $query->orderBy('users.id')->forPage($page, $perPage)->get();
        if ($users->isEmpty()) {
            $this->info('Keine Nutzer gefunden.');
            return Command::SUCCESS;
        }

        $showEmail = (bool) $this->option('show-email') || config('mod.user_list_show_email', false);

        foreach ($users as $user) {
            $partnerStatus = strtolower(trim((string) ($user->partner_status ?? '')));
            $isPartner = in_array($partnerStatus, ['active', 'restricted', 'pending'], true);
            $verification = $user->email_verified_at ? 'verified' : 'unverified';

            $line = sprintf(
                '#%s | %s | role=%s | verification=%s | page=%s | partner=%s',
                $user->id,
                $user->name,
                $user->role,
                $verification,
                $user->littlelink_name ?? '-',
                $this->boolLabel($isPartner)
            );

            if ($partnerSchemaReady) {
                $line .= ' | partner_status=' . ($partnerStatus !== '' ? $partnerStatus : 'none');
            }

            if ($showEmail) {
                $line .= ' | email=' . ($user->email ?? '-');
            }
            $this->line($line);
        }

        $this->line(sprintf('Seite %d, max %d pro Seite.', $page, $perPage));

        return Command::SUCCESS;
    }

    private function normalizeStatus(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'yes', 'blocked', 'block', 'pending' => 'yes',
            'no', 'active', 'approved', 'unblocked' => 'no',
            default => null,
        };
    }
}
