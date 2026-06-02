<?php

namespace App\Console\Commands\Mod;

use App\Models\User;
use App\Models\Link;
use App\Services\Agency\AgencyHubContext;
use Modules\Tiers\Services\SubscriptionManager;
use Modules\Tiers\Models\UserSubscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\Partners\Models\PartnerAccount;

class UserViewCommand extends BaseModCommand
{
    protected $signature = 'user:view {id}';
    protected $description = 'Details eines Nutzers anzeigen.';


    public function handle(SubscriptionManager $subscriptionManager, AgencyHubContext $agencyContext): int
    {
        if (!$this->guardAllowed()) {
            return Command::FAILURE;
        }

        $user = User::find($this->argument('id'));
        if (!$user) {
            $this->error('User nicht gefunden.');
            return Command::FAILURE;
        }

        $linksCount = Link::where('user_id', $user->id)->count();
        $tier = $subscriptionManager->getUserTier($user);
        $subscription = UserSubscription::where('user_id', $user->id)->first();
        $partnerStatus = null;

        if (Schema::hasTable('partner_accounts')) {
            $partnerStatus = strtolower((string) (PartnerAccount::query()
                ->where('user_id', $user->id)
                ->value('status') ?? ''));
        }

        $isPartner = in_array($partnerStatus, ['active', 'restricted', 'pending'], true);

        $this->line('ID: ' . $user->id);
        $this->line('Username: ' . $user->name);
        $this->line('Verification: ' . ($user->email_verified_at ? 'verified' : 'unverified'));
        $this->line('Partner: ' . $this->boolLabel($isPartner));
        $this->line('Partner-Status: ' . ($partnerStatus !== null && $partnerStatus !== '' ? $partnerStatus : 'none'));
        $this->line('Role: ' . $user->role);
        $this->line('Tier: ' . ($tier->slug ?? 'free'));
        $this->line('Links: ' . $linksCount);
        $this->line('Abo-Status: ' . ($subscription?->expires_at ? ('aktiv bis ' . $subscription->expires_at) : 'ohne Ablauf'));
        if ($agencyContext->isAgencyAccount($user)) {
            $slots = $agencyContext->slotSummary($user);
            $this->line('Agency Slots (inkl. Owner): ' . (int) ($slots['used'] ?? 0) . '/' . (int) ($slots['total'] ?? 0));
            $this->line('Agency Included/Add-on: ' . (int) ($slots['included'] ?? 0) . '/' . (int) ($slots['addon'] ?? 0));
        }
        if (config('mod.user_view_show_email', false)) {
            $this->line('Email: ' . ($user->email ?? '-'));
        }

        return Command::SUCCESS;
    }
}
