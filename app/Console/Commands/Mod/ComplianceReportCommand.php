<?php

namespace App\Console\Commands\Mod;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ComplianceReportCommand extends BaseModCommand
{
    protected $signature = 'compliance:report {user_id} {--days=365 : Reporting window in days}';
    protected $description = 'Generate a compliance/dispute activity summary for one user.';

    public function handle(): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $userId = (int) $this->argument('user_id');
        $days = max(1, (int) $this->option('days'));

        $user = User::query()->find($userId);
        if (!$user) {
            $this->error('User not found.');
            return Command::FAILURE;
        }

        if (!Schema::hasTable('compliance_audit_log')) {
            $this->error('compliance_audit_log table not found. Run migrations first.');
            return Command::FAILURE;
        }

        $since = Carbon::now()->subDays($days);
        $entries = DB::table('compliance_audit_log')
            ->select('event_type', 'created_at', 'metadata')
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->orderBy('created_at')
            ->get();

        if ($entries->isEmpty()) {
            $this->warn("No compliance events found for user #{$userId} in the last {$days} day(s).");
            return Command::SUCCESS;
        }

        $eventCounts = $entries->groupBy('event_type')->map->count()->sortDesc();

        $agbAccepted = $entries->firstWhere('event_type', 'agb_accepted')
            ?: $entries->firstWhere('event_type', 'tos_accepted');
        $avvAccepted = $entries->firstWhere('event_type', 'avv_accepted');
        $logins = $entries->where('event_type', 'login_success');
        $loginDays = $logins
            ->map(function ($entry): string {
                return Carbon::parse((string) $entry->created_at)->toDateString();
            })
            ->unique()
            ->count();
        $lastHubUpdated = $entries->where('event_type', 'hub_updated')->last();
        $lastDomainSet = $entries->where('event_type', 'domain_set')->last();

        $this->line("User #{$user->id} (@{$user->littlelink_name})");
        $this->line("Window: {$since->toDateTimeString()} -> " . Carbon::now()->toDateTimeString());
        $this->newLine();

        $this->line('Dispute summary:');
        $this->line('1) AGB accepted: ' . ($agbAccepted ? Carbon::parse((string) $agbAccepted->created_at)->toDateTimeString() : 'not found in window'));
        $this->line('2) AVV accepted: ' . ($avvAccepted ? Carbon::parse((string) $avvAccepted->created_at)->toDateTimeString() : 'not found in window'));
        $this->line('3) Login successes: ' . $logins->count() . " on {$loginDays} distinct day(s)");
        $this->line('4) Last hub update: ' . ($lastHubUpdated ? Carbon::parse((string) $lastHubUpdated->created_at)->toDateTimeString() : 'not found in window'));
        $this->line('5) Last domain set: ' . ($lastDomainSet ? Carbon::parse((string) $lastDomainSet->created_at)->toDateTimeString() : 'not found in window'));
        $this->newLine();

        $this->line('Event counts:');
        foreach ($eventCounts as $eventType => $count) {
            $this->line(sprintf('- %s: %d', $eventType, $count));
        }

        return Command::SUCCESS;
    }
}
