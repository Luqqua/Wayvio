<?php

namespace App\Console\Commands\Mod;

use App\Models\AgencyHub;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Modules\Tiers\Models\Tier;
use App\Services\Agency\AgencyHubQuotaManager;
use Modules\Tiers\Services\TierResolver;
use Carbon\Carbon;

class TierSetCommand extends BaseModCommand
{
    protected $signature = 'tier:set
        {user_id}
        {tier}
        {--expires=}
        {--months=}
        {--hubs= : Agency total slots incl. owner page (within configured bounds)}
        {--delete= : Comma-separated managed hub IDs or slugs to deactivate first}
        {--strategy=least_links : least_links|newest|oldest}
        {--dry-run : Show hub deactivations without applying changes}';
    protected $description = 'Tier eines Users setzen inkl. Agency-Hub-Quota, Laufzeit und kontrollierter Hub-Deaktivierung.';

    public function handle(AgencyHubQuotaManager $quotaManager): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $user = User::find($this->argument('user_id'));
        if (!$user) {
            $this->error('User nicht gefunden.');
            return Command::FAILURE;
        }

        $resolver = app(TierResolver::class);
        $slug = $resolver->normalizeSlug(strtolower($this->argument('tier')));
        $tier = Tier::where('slug', $slug)->first();
        if (!$tier) {
            $this->error('Tier nicht gefunden: '.$slug);
            return Command::FAILURE;
        }

        $freeSlug = config('tiers.default_free_slug', 'free');
        $expiresInput = $this->option('expires');
        $monthsInput = $this->option('months');
        $hubsInput = $this->option('hubs');
        $dryRun = (bool) $this->option('dry-run');
        $strategy = trim(strtolower((string) $this->option('strategy')));
        $agencyMinHubs = AgencyHubQuotaManager::minAgencyHubs();
        $agencyMaxHubs = AgencyHubQuotaManager::maxAgencyHubs();
        if (!in_array($strategy, AgencyHubQuotaManager::STRATEGIES, true)) {
            $this->error('Ungültige strategy. Erlaubt: '.implode(', ', AgencyHubQuotaManager::STRATEGIES));
            return Command::FAILURE;
        }

        $expiresAt = null;
        if ($slug !== $freeSlug) {
            if ($expiresInput) {
                try {
                    $expiresAt = Carbon::parse($expiresInput);
                } catch (\Throwable $e) {
                    $this->error('Ungültiges expires-Datum. Erwartet z. B. 2024-12-31');
                    return Command::FAILURE;
                }
            } elseif ($monthsInput !== null) {
                $months = (int) $monthsInput;
                if ($months <= 0) {
                    $this->error('Ungültiger months-Wert. Muss > 0 sein.');
                    return Command::FAILURE;
                }
                $expiresAt = Carbon::now()->addMonths($months);
            } else {
                $this->error('Bitte für nicht-free ein Ablauf setzen: --expires=YYYY-MM-DD oder --months=<n>.');
                return Command::FAILURE;
            }
        }

        $agencyHubSlots = null;
        if ($hubsInput !== null && $hubsInput !== '') {
            $agencyHubSlots = (int) $hubsInput;
            if ($agencyHubSlots < $agencyMinHubs || $agencyHubSlots > $agencyMaxHubs) {
                $this->error('Agency hubs müssen zwischen '.$agencyMinHubs.' und '.$agencyMaxHubs.' liegen.');
                return Command::FAILURE;
            }
        }

        $preferredDeleteIds = $this->resolveDeleteSelection($user, (string) $this->option('delete'));

        $result = $quotaManager->applyTierTransition(
            $user,
            $tier,
            $expiresAt,
            $agencyHubSlots,
            $preferredDeleteIds,
            $strategy,
            $dryRun
        );

        $this->line('Tier: '.$slug);
        $this->line('Managed hubs: '.(int) Arr::get($result, 'used', 0).' -> target '.(int) Arr::get($result, 'target_managed_slots', Arr::get($result, 'target', 0)));
        if (array_key_exists('target_slots', $result)) {
            $this->line('Total slots (incl. owner): '.(int) Arr::get($result, 'target_slots', 0));
        }
        $this->line('Planned deactivations: '.(int) Arr::get($result, 'remove_count', 0));

        $victims = collect((array) Arr::get($result, 'victims', []));
        if ($victims->isNotEmpty()) {
            $this->line('Hubs to deactivate:');
            foreach ($victims as $victim) {
                $this->line(sprintf(
                    '- #%d %s (%s), links=%d',
                    (int) ($victim['managed_user_id'] ?? 0),
                    (string) ($victim['display_name'] ?? 'n/a'),
                    (string) ($victim['slug'] ?? 'n/a'),
                    (int) ($victim['links_count'] ?? 0)
                ));
            }
        }

        if (!empty($result['unresolved_preferences'])) {
            $this->warn('Nicht auflösbare Deactivate-Auswahl: '.implode(', ', $result['unresolved_preferences']));
        }

        if ($dryRun) {
            $this->info('Dry-run abgeschlossen. Keine Änderungen gespeichert.');
            return Command::SUCCESS;
        }

        $this->info("Tier {$slug} für User #{$user->id} gesetzt" . ($expiresAt ? ' (expires '.$expiresAt->toDateString().')' : ' (ohne Ablauf)') . '.');
        if ((int) Arr::get($result, 'removed', 0) > 0) {
            $this->info('Deaktivierte Hubs: '.(int) Arr::get($result, 'removed', 0));
        }
        return Command::SUCCESS;
    }

    /**
     * @return array<int,int>
     */
    private function resolveDeleteSelection(User $agencyUser, string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $tokens = collect(explode(',', $raw))
            ->map(fn ($token) => trim((string) $token))
            ->filter()
            ->values();

        if ($tokens->isEmpty()) {
            return [];
        }

        $resolved = [];
        foreach ($tokens as $token) {
            if (ctype_digit($token)) {
                $resolved[] = (int) $token;
                continue;
            }

            $slug = ltrim($token, '@');
            $managedUserId = AgencyHub::query()
                ->where('agency_user_id', $agencyUser->id)
                ->where('status', 'active')
                ->whereHas('managedUser', function ($query) use ($slug): void {
                    $query->where('littlelink_name', $slug);
                })
                ->value('managed_user_id');

            if ($managedUserId) {
                $resolved[] = (int) $managedUserId;
                continue;
            }

            $this->warn("Deactivate-Auswahl '{$token}' konnte nicht aufgelöst werden.");
        }

        return collect($resolved)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
