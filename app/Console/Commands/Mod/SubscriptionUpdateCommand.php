<?php

namespace App\Console\Commands\Mod;

use App\Models\AgencyHub;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Modules\Tiers\Helpers\SubscriptionHelper;
use App\Services\Agency\AgencyHubQuotaManager;
use Modules\Tiers\Models\Tier;
use Modules\Tiers\Services\SubscriptionManager;
use Modules\Tiers\Services\TierResolver;

class SubscriptionUpdateCommand extends BaseModCommand
{
    protected $signature = 'subscription:update
        {id}
        {plan}
        {--months= : Laufzeit in Monaten (optional)}
        {--hubs= : Agency total slots inkl. Owner-Page (within configured bounds)}
        {--delete= : Comma-separated managed hub IDs or slugs to deactivate first}
        {--strategy=least_links : least_links|newest|oldest}
        {--dry-run : Show hub deactivations without applying changes}';
    protected $description = 'Abo eines Users ändern inkl. Agency-Hub-Quota und kontrollierter Hub-Deaktivierung.';

    public function handle(SubscriptionManager $manager, AgencyHubQuotaManager $quotaManager): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $user = User::find($this->argument('id'));
        if (!$user) {
            $this->error('User nicht gefunden.');
            return Command::FAILURE;
        }

        $resolver = app(TierResolver::class);
        $plan = $resolver->normalizeSlug(strtolower($this->argument('plan')));
        $tier = Tier::where('slug', $plan)->first();
        if (!$tier) {
            $this->error('Tier nicht gefunden: ' . $plan);
            return Command::FAILURE;
        }

        $strategy = trim(strtolower((string) $this->option('strategy')));
        $agencyMinHubs = AgencyHubQuotaManager::minAgencyHubs();
        $agencyMaxHubs = AgencyHubQuotaManager::maxAgencyHubs();
        if (!in_array($strategy, AgencyHubQuotaManager::STRATEGIES, true)) {
            $this->error('Ungültige strategy. Erlaubt: '.implode(', ', AgencyHubQuotaManager::STRATEGIES));
            return Command::FAILURE;
        }

        $hubsOption = $this->option('hubs');
        $agencyHubSlots = null;
        if ($hubsOption !== null && $hubsOption !== '') {
            $agencyHubSlots = (int) $hubsOption;
            if ($agencyHubSlots < $agencyMinHubs || $agencyHubSlots > $agencyMaxHubs) {
                $this->error('Agency hubs müssen zwischen '.$agencyMinHubs.' und '.$agencyMaxHubs.' liegen.');
                return Command::FAILURE;
            }
        }

        $preferredDeleteIds = $this->resolveDeleteSelection($user, (string) $this->option('delete'));
        $months = $this->option('months');
        $dryRun = (bool) $this->option('dry-run');
        $periodMonths = null;
        if ($months !== null) {
            $periodMonths = (int) $months;
            if ($periodMonths <= 0) {
                $this->error('Monate müssen > 0 sein.');
                return Command::FAILURE;
            }
        }

        if ($dryRun) {
            $expiresAt = $periodMonths ? SubscriptionHelper::getExpirationDateByPlanLength($periodMonths) : null;
            $result = $quotaManager->applyTierTransition(
                $user,
                $tier,
                $expiresAt,
                $agencyHubSlots,
                $preferredDeleteIds,
                $strategy,
                true
            );

            $this->line('Dry-run: subscription:update');
            $this->line('Managed hubs: '.(int) Arr::get($result, 'used', 0).' -> target '.(int) Arr::get($result, 'target_managed_slots', Arr::get($result, 'target', 0)));
            if (array_key_exists('target_slots', $result)) {
                $this->line('Total slots (incl. owner): '.(int) Arr::get($result, 'target_slots', 0));
            }
            $this->line('Planned deactivations: '.(int) Arr::get($result, 'remove_count', 0));
            foreach ((array) Arr::get($result, 'victims', []) as $victim) {
                $this->line(sprintf(
                    '- #%d %s (%s), links=%d',
                    (int) ($victim['managed_user_id'] ?? 0),
                    (string) ($victim['display_name'] ?? 'n/a'),
                    (string) ($victim['slug'] ?? 'n/a'),
                    (int) ($victim['links_count'] ?? 0)
                ));
            }

            if (!empty($result['unresolved_preferences'])) {
                $this->warn('Nicht auflösbare Deactivate-Auswahl: '.implode(', ', $result['unresolved_preferences']));
            }

            $this->info('Dry-run abgeschlossen. Keine Änderungen gespeichert.');
            return Command::SUCCESS;
        }

        $manager->assignTier(
            $user,
            $tier,
            $periodMonths,
            $agencyHubSlots,
            $preferredDeleteIds,
            $strategy
        );

        $this->info("Abo für User #{$user->id} auf {$tier->slug} gesetzt.");
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
