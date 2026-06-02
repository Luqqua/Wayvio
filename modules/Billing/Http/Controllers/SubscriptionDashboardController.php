<?php

namespace Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Agency\AgencyHubQuotaManager;
use App\Services\Billing\BillingClient;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Modules\Tiers\Models\Tier;
use Modules\Tiers\Services\SubscriptionManager;
use Modules\Billing\Services\BillingRecorder;
use Illuminate\Support\Facades\View;
use Modules\Tiers\Services\TierResolver;
use Illuminate\Support\Arr;

class SubscriptionDashboardController extends Controller
{
    public function show(Request $request, SubscriptionManager $manager, BillingRecorder $recorder, TierResolver $resolver)
    {
        $user = $request->user();
        $billingState = null;
        /** @var BillingClient $billingClient */
        $billingClient = app(BillingClient::class);
        if ($billingClient->enabled()) {
            $billingState = $billingClient->subscriptionState((int) $user->id, (int) $user->id, true);
        }
        $tier = $manager->getUserTier($user);
        $expired = $manager->isExpired($user);
        $inGrace = $manager->isInGracePeriod($user);
        $subscription = $manager->getUserSubscriptionRecord($user);

        $tierOrder = config('tiers.order', ['free', 'basic', 'pro', 'agency']);
        $freeSlug = $resolver->normalizeSlug(config('tiers.default_free_slug', 'free'));
        $currentSlug = $resolver->normalizeSlug($tier?->slug ?? config('tiers.default_free_slug', 'free'));
        $currentIdx = array_search($currentSlug, $tierOrder, true);
        if ($currentIdx === false) { $currentIdx = 0; }
        $allowedSlugs = array_slice($tierOrder, $currentIdx + 1); // any higher plan
        $startsNewSubscription = $currentSlug === $freeSlug;
        $remoteSubscription = is_array($billingState) ? ($billingState['subscription'] ?? null) : null;
        $remoteHasPendingTierId = is_array($remoteSubscription) && array_key_exists('pending_tier_id', $remoteSubscription);
        $remoteHasPendingHubCount = is_array($remoteSubscription) && array_key_exists('pending_hub_slots_included', $remoteSubscription);
        $cancelAtPeriodEnd = (bool) (($remoteSubscription['cancel_at_period_end'] ?? null) ?? ($subscription?->cancel_at_period_end ?? false));
        $pendingTierId = $remoteHasPendingTierId
            ? $remoteSubscription['pending_tier_id']
            : $subscription?->pending_tier_id;
        $pendingTier = $subscription?->pendingTier;
        if ($pendingTierId === null) {
            $pendingTier = null;
        }
        if (!$pendingTier && $pendingTierId) {
            $pendingTier = Tier::query()->find((int) $pendingTierId);
        }
        $rawPendingTierSlug = $pendingTier?->slug ?? ($remoteSubscription['pending_tier_slug'] ?? null);
        $pendingTierSlug = is_string($rawPendingTierSlug) && $rawPendingTierSlug !== ''
            ? $resolver->normalizeSlug($rawPendingTierSlug)
            : '';
        $pendingTierName = $pendingTier?->name ?? ($remoteSubscription['pending_tier_name'] ?? null);
        $pendingHubCount = $remoteHasPendingHubCount
            ? $remoteSubscription['pending_hub_slots_included']
            : $subscription?->pending_hub_slots_included;
        $hasPendingChange = $cancelAtPeriodEnd || $pendingTierId !== null || $pendingHubCount !== null;
        $subscriptionExpiresAt = null;
        $rawExpiresAt = $subscription?->expires_at;
        if ($rawExpiresAt instanceof CarbonInterface) {
            $subscriptionExpiresAt = $rawExpiresAt;
        } elseif ($rawExpiresAt instanceof \DateTimeInterface) {
            $subscriptionExpiresAt = Carbon::parse($rawExpiresAt->format(DATE_ATOM));
        } elseif (is_string($rawExpiresAt) && trim($rawExpiresAt) !== '') {
            try {
                $subscriptionExpiresAt = Carbon::parse($rawExpiresAt);
            } catch (\Throwable $e) {
                $subscriptionExpiresAt = null;
            }
        }
        $manageInPortal = (bool) (($billingState['portal_available'] ?? null) ?? (!empty($subscription?->stripe_customer_id)));
        $rawUpgradePreviews = is_array($billingState) ? ($billingState['upgrade_previews'] ?? []) : [];
        $upgradePreviews = collect(is_array($rawUpgradePreviews) ? $rawUpgradePreviews : [])
            ->filter(static fn ($preview): bool => is_array($preview))
            ->map(function (array $preview) use ($resolver): array {
                $rawSlug = $preview['tier_slug'] ?? null;
                $tierSlug = is_string($rawSlug) && $rawSlug !== ''
                    ? $resolver->normalizeSlug($rawSlug)
                    : '';
                $amountDueNow = isset($preview['amount_due_now']) && is_numeric($preview['amount_due_now'])
                    ? (int) $preview['amount_due_now']
                    : null;
                $prorationAmount = isset($preview['proration_amount']) && is_numeric($preview['proration_amount'])
                    ? (int) $preview['proration_amount']
                    : null;
                $currencyRaw = $preview['currency'] ?? null;
                $currency = is_string($currencyRaw) && $currencyRaw !== ''
                    ? strtolower(substr($currencyRaw, 0, 3))
                    : 'usd';

                return [
                    'tier_id' => isset($preview['tier_id']) && is_numeric($preview['tier_id']) ? (int) $preview['tier_id'] : null,
                    'tier_slug' => $tierSlug,
                    'hub_count' => isset($preview['hub_count']) && is_numeric($preview['hub_count']) ? (int) $preview['hub_count'] : null,
                    'amount_due_now' => $amountDueNow,
                    'proration_amount' => $prorationAmount,
                    'currency' => $currency,
                    'line_items' => is_array($preview['line_items'] ?? null) ? $preview['line_items'] : [],
                    'available' => $amountDueNow !== null,
                    'unavailable_reason' => is_string($preview['unavailable_reason'] ?? null)
                        ? trim($preview['unavailable_reason'])
                        : '',
                ];
            })
            ->filter(static fn (array $preview): bool => $preview['tier_slug'] !== '')
            ->values();
        $upgradePreviewBySlug = $upgradePreviews->keyBy('tier_slug');

        $agencyHubMin = max(2, AgencyHubQuotaManager::minAgencyHubs());
        $agencyHubMax = max($agencyHubMin, min(10, AgencyHubQuotaManager::maxAgencyHubs()));

        $planConfig = $resolver->plans()
            ->map(function ($plan) {
                $limits = $plan['limits'] ?? [];
                $features = $plan['features'] ?? [];
                return array_merge($plan, [
                    'max_pages' => $limits['max_pages'] ?? 1,
                    'max_links_per_page' => $limits['max_links_per_page'] ?? 10,
                    'analytics_history_days' => $limits['analytics_history_days'] ?? Arr::get($features, 'analytics.history_days'),
                    'analytics_enabled' => (bool) Arr::get($features, 'analytics.enabled', false),
                    'custom_domain_enabled' => (bool) Arr::get($features, 'domains.custom_domain', false),
                    'design_customization_enabled' => (bool) Arr::get($features, 'design.link_styling', false)
                        || (bool) Arr::get($features, 'design.custom_colors', false)
                        || (bool) Arr::get($features, 'design.background_image', false)
                        || (bool) Arr::get($features, 'design.header_image', false),
                ]);
            })
            ->keyBy('slug');
        $tierModels = Tier::query()
            ->get()
            ->mapWithKeys(function ($model) use ($resolver): array {
                return [$resolver->normalizeSlug($model->slug) => $model];
            });
        $upgrades = collect($allowedSlugs)
            ->map(function ($slug) use ($planConfig, $tierModels) {
                $cfg = $planConfig[$slug] ?? null;
                if (!$cfg) {
                    return null;
                }
                $model = $tierModels[$slug] ?? null;
                return (object) array_merge($cfg, ['id' => $model?->id]);
            })
            ->filter();

        $allPlans = collect($tierOrder)
            ->map(function ($slug) use ($planConfig, $tierModels, $currentSlug, $tierOrder, $pendingTierSlug, $startsNewSubscription, $upgradePreviewBySlug) {
                $cfg = $planConfig[$slug] ?? null;
                if (!$cfg) {
                    return null;
                }

                $model = $tierModels[$slug] ?? null;
                $idx = array_search($slug, $tierOrder, true);
                $currentIdx = array_search($currentSlug, $tierOrder, true);
                if ($idx === false) { $idx = 0; }
                if ($currentIdx === false) { $currentIdx = 0; }
                $isUpgrade = $idx > $currentIdx;
                $isPaidUpgrade = !$startsNewSubscription && $isUpgrade;
                $preview = $isPaidUpgrade ? $upgradePreviewBySlug->get($slug) : null;

                return (object) array_merge($cfg, [
                    'id' => $model?->id,
                    'is_current' => $slug === $currentSlug,
                    'is_upgrade' => $isUpgrade,
                    'is_paid_upgrade' => $isPaidUpgrade,
                    'is_downgrade' => $idx < $currentIdx,
                    'is_pending_target' => $pendingTierSlug !== '' && $slug === $pendingTierSlug,
                    'upgrade_preview_available' => is_array($preview) && (bool) ($preview['available'] ?? false),
                    'upgrade_preview_amount_due_now' => is_array($preview) ? ($preview['amount_due_now'] ?? null) : null,
                    'upgrade_preview_proration_amount' => is_array($preview) ? ($preview['proration_amount'] ?? null) : null,
                    'upgrade_preview_currency' => is_array($preview) ? ($preview['currency'] ?? null) : null,
                    'upgrade_preview_hub_count' => is_array($preview) ? ($preview['hub_count'] ?? null) : null,
                    'upgrade_preview_unavailable_reason' => is_array($preview) ? ($preview['unavailable_reason'] ?? '') : '',
                    'upgrade_preview_lines' => is_array($preview) ? ($preview['line_items'] ?? []) : [],
                ]);
            })
            ->filter()
            ->values();

        $currentHubSlots = 1;
        if ($currentSlug === 'agency') {
            $currentHubSlots = max(
                $agencyHubMin,
                min(
                    $agencyHubMax,
                    (int) ($subscription?->hub_slots_included ?? $agencyHubMin)
                )
            );
        }

        $currentLimits = $resolver->limits($tier);
        $formatCurrencyCents = static function (?int $cents): ?string {
            if ($cents === null) {
                return null;
            }

            return '€' . number_format($cents / 100, 2);
        };
        $resolveMonthlyTotalCents = static function (string $slug, ?int $hubCount = null) use ($planConfig, $agencyHubMin): ?int {
            $cfg = $planConfig[$slug] ?? null;
            if (!$cfg) {
                return null;
            }

            $base = (int) ($cfg['price_1m'] ?? 0);
            if ($slug !== 'agency') {
                return $base;
            }

            $includedHubs = max($agencyHubMin, (int) ($cfg['included_hubs'] ?? $agencyHubMin));
            $extraHubPrice = max(0, (int) ($cfg['extra_hub_price_1m'] ?? 0));
            $resolvedHubCount = max($agencyHubMin, (int) ($hubCount ?? $includedHubs));
            $extraHubs = max(0, $resolvedHubCount - $includedHubs);

            return $base + ($extraHubs * $extraHubPrice);
        };
        $statusNotice = '';
        $locale = app()->getLocale();
        if ($cancelAtPeriodEnd && $subscriptionExpiresAt) {
            $formattedDate = $subscriptionExpiresAt->locale($locale)->isoFormat('LL');
            $statusNotice = __('Cancellation is scheduled for :date.', ['date' => $formattedDate]);
        } elseif ($hasPendingChange && $subscriptionExpiresAt) {
            $effectiveOn = $subscriptionExpiresAt->locale($locale)->isoFormat('LL');
            $currentPlanName = $tier?->name ?? $resolver->displayName($currentSlug);
            $currentPlanLabel = $currentSlug === 'agency'
                ? sprintf('%s (%d hubs)', $currentPlanName, $currentHubSlots)
                : $currentPlanName;

            $scheduledSlug = $pendingTierSlug !== '' ? $pendingTierSlug : $currentSlug;
            $scheduledName = $pendingTierName ?: $resolver->displayName($scheduledSlug);
            $scheduledHubCount = null;
            if ($scheduledSlug === 'agency') {
                if ($pendingHubCount !== null) {
                    $scheduledHubCount = max($agencyHubMin, (int) $pendingHubCount);
                } elseif ($currentSlug === 'agency') {
                    $scheduledHubCount = $currentHubSlots;
                } else {
                    $scheduledHubCount = $agencyHubMin;
                }
            }

            $scheduledPlanLabel = $scheduledSlug === 'agency' && $scheduledHubCount !== null
                ? sprintf('%s (%d hubs)', $scheduledName, $scheduledHubCount)
                : $scheduledName;
            $statusNotice = __('Current: :current. Scheduled: :scheduled on :date.', [
                'current' => $currentPlanLabel,
                'scheduled' => $scheduledPlanLabel,
                'date' => $effectiveOn,
            ]);

            $scheduledMonthlyTotal = $resolveMonthlyTotalCents($scheduledSlug, $scheduledHubCount);
            $scheduledMonthlyTotalLabel = $formatCurrencyCents($scheduledMonthlyTotal);
            if ($scheduledMonthlyTotalLabel !== null) {
                $statusNotice .= ' ' . __('Next monthly total: :amount.', ['amount' => $scheduledMonthlyTotalLabel]);
            }
        }

        $payload = [
            'current_tier' => [
                'id' => $tier?->id,
                'name' => $tier?->name ?? $resolver->displayName($currentSlug),
                'slug' => $currentSlug,
                'max_links_per_page' => $currentLimits['max_links_per_page'],
            ],
            'expires_at' => $subscriptionExpiresAt?->toISOString(),
            'expired' => $expired,
            'in_grace_period' => $inGrace,
            'upgrade_options' => $startsNewSubscription ? $upgrades : collect(),
            'plan_cards' => $allPlans,
            'selected_period' => 1,
            'agency_hub_count' => $currentHubSlots,
            'agency_hub_min' => $agencyHubMin,
            'agency_hub_max' => $agencyHubMax,
            'pending_hub_count' => $pendingHubCount,
            'billing_history' => $recorder->getForDashboard($user),
            'checkout_url_endpoint' => url('/checkout/session'),
            'allowed_upgrade_slug' => $startsNewSubscription ? $allowedSlugs : [],
            'downgrade_notice' => $statusNotice,
            'plan_comparison' => $planConfig->values(),
            'starts_new_subscription' => $startsNewSubscription,
            'manage_in_portal' => $manageInPortal,
            'has_paid_entitlement' => !$startsNewSubscription,
            'has_pending_change' => $hasPendingChange,
            'cancel_at_period_end' => $cancelAtPeriodEnd,
            'pending_change_target_slug' => $pendingTierSlug,
            'pending_change_target_name' => $pendingTierName,
            'upgrade_previews' => $upgradePreviews->values(),
        ];

        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        View::addNamespace('modules.Billing.views', base_path('modules/Billing/views'));
        return view('modules.Billing.views.subscription', $payload);
    }
}
