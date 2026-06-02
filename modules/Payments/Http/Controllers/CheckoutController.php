<?php

namespace Modules\Payments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Agency\AgencyHubQuotaManager;
use App\Services\Billing\BillingClient;
use App\Services\Compliance\ComplianceAuditService;
use App\Services\Partners\PartnersClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tiers\Models\Tier;
use Modules\Tiers\Services\SubscriptionManager;
use Modules\Tiers\Services\TierResolver;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly BillingClient $billingClient,
        private readonly PartnersClient $partnersClient,
        private readonly SubscriptionManager $subscriptionManager,
        private readonly TierResolver $tierResolver,
        private readonly ComplianceAuditService $complianceAudit,
    )
    {
    }

    public function createCheckoutSession(Request $request): JsonResponse
    {
        $agencyMin = max(2, AgencyHubQuotaManager::minAgencyHubs());
        $agencyMax = max($agencyMin, min(10, AgencyHubQuotaManager::maxAgencyHubs()));
        $enabledPeriods = array_values(array_filter(
            array_map('intval', (array) config('tiers.billing.enabled_periods', [1])),
            static fn (int $months): bool => in_array($months, [1, 3, 6], true)
        ));
        if ($enabledPeriods === []) {
            $enabledPeriods = [1];
        }

        $validated = $request->validate([
            'tier_id' => 'required|exists:tiers,id',
            'period' => 'nullable|in:' . implode(',', $enabledPeriods),
            'hub_count' => "nullable|integer|min:{$agencyMin}|max:{$agencyMax}",
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'terms_acknowledged' => ['required', 'accepted'],
            'withdrawal_waiver_acknowledged' => ['required', 'accepted'],
        ]);

        $tierId = (int) $validated['tier_id'];
        $period = 1;
        $hubCount = isset($validated['hub_count']) ? (int) $validated['hub_count'] : null;
        $idempotencyKey = trim((string) ($validated['idempotency_key'] ?? ''));
        $tier = Tier::query()->findOrFail($tierId);
        $tierSlug = $this->tierResolver->normalizeSlug($tier->slug);
        $currentTier = $this->subscriptionManager->getUserTier($request->user());
        $currentSlug = $this->tierResolver->normalizeSlug($currentTier?->slug);
        $freeSlug = $this->tierResolver->normalizeSlug(config('tiers.default_free_slug', 'free'));

        if ($tierSlug === $freeSlug) {
            return response()->json([
                'error' => 'Free plan does not require checkout.',
            ], 422);
        }

        if ($currentSlug !== $freeSlug && !$this->subscriptionManager->isExpired($request->user())) {
            return response()->json([
                'error' => 'Use the monthly plan change action for existing subscriptions.',
            ], 422);
        }

        if ($tierSlug === 'agency') {
            $hubCount = $hubCount ?? $agencyMin;
            if ($hubCount < $agencyMin || $hubCount > $agencyMax) {
                return response()->json([
                    'error' => "Agency plan requires {$agencyMin} to {$agencyMax} slots (owner included).",
                ], 422);
            }
        }

        if ($tierSlug !== 'agency') {
            $hubCount = null;
        }

        $tosSnapshot = $this->complianceAudit->currentTosSnapshot();
        $estimatedAmountCents = (int) ($tier->price_1m ?? 0);
        if ($tierSlug === 'agency') {
            $agencyPlanConfig = $this->tierResolver->configForSlug($tierSlug);
            $includedHubs = max($agencyMin, (int) ($agencyPlanConfig['included_hubs'] ?? $agencyMin));
            $extraHubPrice = max(0, (int) ($agencyPlanConfig['extra_hub_price_1m'] ?? 0));
            $extraHubs = max(0, (int) $hubCount - $includedHubs);
            $estimatedAmountCents += ($extraHubs * $extraHubPrice);
        }

        $this->complianceAudit->record(
            'checkout_terms_acknowledged',
            request: $request,
            userId: (int) $request->user()->id,
            actorUserId: (int) $request->user()->id,
            source: 'billing.checkout',
            metadata: [
                'tier_id' => $tierId,
                'tier_slug' => $tierSlug,
                'period_months' => $period,
                'hub_count' => $hubCount,
                'estimated_amount_cents' => $estimatedAmountCents,
                'currency' => 'eur',
                'tos_version' => $tosSnapshot['version'] ?? null,
                'tos_url' => $tosSnapshot['url'] ?? null,
                'tos_content_hash' => $tosSnapshot['content_hash'] ?? null,
                'withdrawal_waiver_acknowledged' => true,
            ]
        );

        $msPayload = [
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
            'tier_id' => $tierId,
            'period' => $period,
            'hub_count' => $hubCount,
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            'success_url' => url('/dashboard/subscription?checkout=1&success=1&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => url('/dashboard/subscription?checkout=1&canceled=1'),
        ];

        if (!$this->billingClient->enabled()) {
            return $this->billingUnavailable('billing_api_disabled');
        }

        $result = $this->billingClient->createCheckoutSession($msPayload, (int) $request->user()->id);
        if (is_array($result) && !empty($result['url'])) {
            $checkoutSessionId = isset($result['id']) ? (string) $result['id'] : null;

            $this->complianceAudit->record(
                'checkout_session_created',
                request: $request,
                userId: (int) $request->user()->id,
                actorUserId: (int) $request->user()->id,
                source: 'billing.checkout',
                metadata: [
                    'tier_id' => $tierId,
                    'tier_slug' => $tierSlug,
                    'period_months' => $period,
                    'hub_count' => $hubCount,
                    'estimated_amount_cents' => $estimatedAmountCents,
                    'currency' => 'eur',
                    'checkout_session_id' => $checkoutSessionId,
                ]
            );

            return response()->json(['url' => $result['url']]);
        }

        return $this->billingUnavailable();
    }

    public function customerPortal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'return_url' => 'required|url',
        ]);

        $msPayload = [
            'user_id' => $request->user()->id,
            'user_email' => $request->user()->email,
            'user_name' => $request->user()->name,
            'return_url' => $validated['return_url'],
        ];

        if (!$this->billingClient->enabled()) {
            return $this->billingUnavailable('billing_api_disabled');
        }

        $result = $this->billingClient->createPortalSession($msPayload, (int) $request->user()->id);
        if (is_array($result) && !empty($result['url'])) {
            return response()->json(['url' => $result['url']]);
        }

        return $this->billingUnavailable();
    }

    public function changeSubscriptionPlan(Request $request): JsonResponse
    {
        $agencyMin = max(2, AgencyHubQuotaManager::minAgencyHubs());
        $agencyMax = max($agencyMin, min(10, AgencyHubQuotaManager::maxAgencyHubs()));

        $validated = $request->validate([
            'tier_id' => 'required|exists:tiers,id',
            'hub_count' => "nullable|integer|min:{$agencyMin}|max:{$agencyMax}",
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ]);

        $user = $request->user();
        $currentTier = $this->subscriptionManager->getUserTier($user);
        $currentSlug = $this->tierResolver->normalizeSlug($currentTier?->slug);
        $freeSlug = $this->tierResolver->normalizeSlug(config('tiers.default_free_slug', 'free'));

        if ($currentSlug === $freeSlug || $this->subscriptionManager->isExpired($user)) {
            return response()->json([
                'error' => 'Start a new monthly subscription via checkout first.',
            ], 422);
        }

        $tier = Tier::query()->findOrFail((int) $validated['tier_id']);
        $tierSlug = $this->tierResolver->normalizeSlug($tier->slug);
        $idempotencyKey = trim((string) ($validated['idempotency_key'] ?? ''));

        if (
            $tierSlug === $currentSlug
            && $tierSlug !== 'agency'
            && !$this->subscriptionManager->hasPendingChange($user)
        ) {
            return response()->json([
                'error' => 'You are already on this plan.',
            ], 422);
        }

        $hubCount = isset($validated['hub_count']) ? (int) $validated['hub_count'] : null;
        if ($tierSlug === 'agency') {
            $hubCount = $hubCount ?? max($agencyMin, 2);
        } else {
            $hubCount = null;
        }

        $subscription = $this->subscriptionManager->getUserSubscriptionRecord($user);
        $pendingTierId = (int) ($subscription?->pending_tier_id ?? 0);
        $pendingHubCount = $subscription?->pending_hub_slots_included;
        $alreadyScheduledCancellationWithoutPendingTier = $tierSlug === $freeSlug
            && (bool) ($subscription?->cancel_at_period_end ?? false)
            && $pendingTierId === 0;

        if ($pendingTierId > 0 && $pendingTierId === (int) $validated['tier_id']) {
            $samePendingAgencyHubCount = true;
            if ($tierSlug === 'agency') {
                $currentHubCount = (int) ($subscription?->hub_slots_included ?? $agencyMin);
                $effectivePendingHubCount = $pendingHubCount !== null
                    ? (int) $pendingHubCount
                    : max($agencyMin, $currentHubCount);
                $samePendingAgencyHubCount = $hubCount === $effectivePendingHubCount;
            }

            if ($samePendingAgencyHubCount) {
                return response()->json([
                    'error' => 'This plan change is already scheduled for the next renewal.',
                ], 422);
            }
        }

        if ($alreadyScheduledCancellationWithoutPendingTier) {
            return response()->json([
                'error' => 'This plan change is already scheduled for the next renewal.',
            ], 422);
        }

        if (!$this->billingClient->enabled()) {
            return $this->billingUnavailable('billing_api_disabled');
        }

        $result = $this->billingClient->changeSubscription([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'tier_id' => (int) $validated['tier_id'],
            'hub_count' => $hubCount,
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
        ], (int) $user->id);

        if (is_array($result) && isset($result['_status'])) {
            return response()->json([
                'error' => $this->billingErrorMessage($result),
            ], (int) $result['_status']);
        }

        if (is_array($result) && !empty($result['status'])) {
            $this->complianceAudit->record(
                'subscription_change_requested',
                request: $request,
                userId: (int) $request->user()->id,
                actorUserId: (int) $request->user()->id,
                source: 'billing.change',
                metadata: [
                    'target_tier_id' => (int) $validated['tier_id'],
                    'target_tier_slug' => $tierSlug,
                    'hub_count' => $hubCount,
                    'result_status' => (string) ($result['status'] ?? ''),
                    'result_message' => (string) ($result['message'] ?? ''),
                ]
            );

            return response()->json([
                'status' => $result['status'],
                'message' => $result['message'] ?? 'Subscription updated.',
            ]);
        }

        return $this->billingUnavailable();
    }

    public function previewSubscriptionPlanChange(Request $request): JsonResponse
    {
        $agencyMin = max(2, AgencyHubQuotaManager::minAgencyHubs());
        $agencyMax = max($agencyMin, min(10, AgencyHubQuotaManager::maxAgencyHubs()));

        $validated = $request->validate([
            'tier_id' => 'required|exists:tiers,id',
            'hub_count' => "nullable|integer|min:{$agencyMin}|max:{$agencyMax}",
        ]);

        $user = $request->user();
        $currentTier = $this->subscriptionManager->getUserTier($user);
        $currentSlug = $this->tierResolver->normalizeSlug($currentTier?->slug);
        $freeSlug = $this->tierResolver->normalizeSlug(config('tiers.default_free_slug', 'free'));

        if ($currentSlug === $freeSlug || $this->subscriptionManager->isExpired($user)) {
            return response()->json([
                'error' => 'Start a new monthly subscription via checkout first.',
            ], 422);
        }

        $tier = Tier::query()->findOrFail((int) $validated['tier_id']);
        $tierSlug = $this->tierResolver->normalizeSlug($tier->slug);
        $hubCount = isset($validated['hub_count']) ? (int) $validated['hub_count'] : null;
        if ($tierSlug === 'agency') {
            $hubCount = $hubCount ?? max($agencyMin, 2);
        } else {
            $hubCount = null;
        }

        if (!$this->billingClient->enabled()) {
            return $this->billingUnavailable('billing_api_disabled');
        }

        $result = $this->billingClient->previewSubscriptionChange([
            'user_id' => $user->id,
            'user_email' => $user->email,
            'tier_id' => (int) $validated['tier_id'],
            'hub_count' => $hubCount,
        ], (int) $user->id);

        if (is_array($result) && isset($result['_status'])) {
            return response()->json([
                'error' => $this->billingErrorMessage($result),
            ], (int) $result['_status']);
        }

        if (is_array($result)) {
            return response()->json($result);
        }

        return $this->billingUnavailable();
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        $signature = $request->header('Stripe-Signature');
        $payload = $request->getContent();
        $eventType = $this->parseStripeEventType($payload);

        $billingEnabled = $this->billingClient->enabled();
        $partnersEnabled = $this->partnersClient->enabled();
        $billingRelevant = $this->isBillingWebhookEvent($eventType);
        $partnersRelevant = $this->isPartnerWebhookEvent($eventType);

        if (!$billingEnabled && !$partnersEnabled) {
            return $this->billingUnavailable('billing_api_disabled');
        }

        $billingAttempted = $billingEnabled && ($eventType === null || $billingRelevant);
        $partnersAttempted = $partnersEnabled && ($eventType === null || $partnersRelevant);

        $billingResult = $billingAttempted
            ? $this->billingClient->processWebhook($payload, $signature)
            : null;
        $partnersResult = $partnersAttempted
            ? $this->partnersClient->processWebhook($payload, $signature)
            : null;

        $billingFailure = $this->internalWebhookFailure(
            $billingResult,
            $billingAttempted,
            'billing_api_unavailable',
            'Billing API temporarily unavailable. Please retry in a few seconds.',
        );
        $partnersFailure = $this->internalWebhookFailure(
            $partnersResult,
            $partnersAttempted,
            'partners_api_unavailable',
            'Partner API temporarily unavailable. Please retry in a few seconds.',
        );

        $billingSuccess = $billingAttempted && $billingFailure === null;
        $partnersSuccess = $partnersAttempted && $partnersFailure === null;
        $allSkippedAsIrrelevant = !$billingAttempted && !$partnersAttempted;

        if (!$billingSuccess && !$partnersSuccess && !$allSkippedAsIrrelevant) {
            return response()->json([
                'status' => 'error',
                'event_type' => $eventType,
                'billing_error' => $billingFailure,
                'partners_error' => $partnersFailure,
            ], 503);
        }

        if ($billingFailure !== null || $partnersFailure !== null) {
            logger()->warning('Stripe webhook partially processed', [
                'event_type' => $eventType,
                'billing_failure' => $billingFailure,
                'partners_failure' => $partnersFailure,
            ]);

            return response()->json([
                'status' => 'partial',
                'event_type' => $eventType,
                'billing_status' => $billingSuccess ? (is_array($billingResult) ? ($billingResult['status'] ?? 'ok') : 'ok') : 'failed',
                'partners_status' => $partnersSuccess ? (is_array($partnersResult) ? ($partnersResult['status'] ?? 'ok') : 'ok') : 'failed',
                'billing_error' => $billingFailure,
                'partners_error' => $partnersFailure,
            ], 202);
        }

        return response()->json([
            'status' => 'ok',
            'event_type' => $eventType,
            'billing_status' => $billingAttempted
                ? (is_array($billingResult) ? ($billingResult['status'] ?? 'ok') : 'ok')
                : 'skipped_irrelevant',
            'partners_status' => $partnersAttempted
                ? (is_array($partnersResult) ? ($partnersResult['status'] ?? 'ok') : 'ok')
                : 'skipped_irrelevant',
        ]);
    }

    public function confirmCheckout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string|max:191',
        ]);

        if (!$this->billingClient->enabled()) {
            return $this->billingUnavailable('billing_api_disabled');
        }

        $result = $this->billingClient->confirmCheckout([
            'user_id' => $request->user()->id,
            'session_id' => $validated['session_id'],
        ], (int) $request->user()->id);

        if (is_array($result) && isset($result['_status'])) {
            $status = (int) $result['_status'];
            $responsePayload = $result;
            unset($responsePayload['_status']);
            $responsePayload['error'] = $this->billingErrorMessage($result);
            if (empty($responsePayload['message']) || !is_string($responsePayload['message'])) {
                $responsePayload['message'] = $responsePayload['error'];
            }

            return response()->json($responsePayload, $status);
        }

        if (is_array($result) && !empty($result['status'])) {
            return response()->json($result);
        }

        return $this->billingUnavailable();
    }

    private function billingUnavailable(string $error = 'billing_api_unavailable'): JsonResponse
    {
        $message = $error === 'billing_api_disabled'
            ? 'Billing API is disabled.'
            : 'Billing API temporarily unavailable. Please retry in a few seconds.';

        return response()->json([
            'error' => $error,
            'message' => $message,
        ], 503);
    }

    /**
     * @param array<string,mixed> $result
     */
    private function billingErrorMessage(array $result): string
    {
        $error = $result['error'] ?? null;
        if (is_array($error) && !empty($error['message']) && is_string($error['message'])) {
            return $error['message'];
        }

        if (is_string($error) && $error !== '') {
            return $error;
        }

        if (!empty($result['message']) && is_string($result['message'])) {
            return $result['message'];
        }

        return 'Unable to update subscription.';
    }

    private function parseStripeEventType(string $payload): ?string
    {
        if ($payload === '') {
            return null;
        }

        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        $eventType = is_array($decoded) ? data_get($decoded, 'type') : null;
        return is_string($eventType) && $eventType !== '' ? $eventType : null;
    }

    private function isBillingWebhookEvent(?string $eventType): bool
    {
        if (!$eventType) {
            return true;
        }

        if (
            str_starts_with($eventType, 'checkout.session.')
            || str_starts_with($eventType, 'invoice.')
            || str_starts_with($eventType, 'customer.subscription.')
            || str_starts_with($eventType, 'charge.')
        ) {
            return true;
        }

        return false;
    }

    private function isPartnerWebhookEvent(?string $eventType): bool
    {
        if (!$eventType) {
            return true;
        }

        return in_array($eventType, [
            'account.updated',
            'transfer.created',
            'transfer.reversed',
        ], true);
    }

    /**
     * @param array<string,mixed>|null $result
     * @return array<string,mixed>|null
     */
    private function internalWebhookFailure(?array $result, bool $attempted, string $defaultCode, string $defaultMessage): ?array
    {
        if (!$attempted) {
            return null;
        }

        if ($result === null) {
            return [
                'code' => $defaultCode,
                'message' => $defaultMessage,
                'status' => 503,
            ];
        }

        if (!isset($result['_status'])) {
            return null;
        }

        $status = (int) $result['_status'];
        $errorMessage = data_get($result, 'error.message')
            ?? data_get($result, 'error')
            ?? data_get($result, 'message')
            ?? $defaultMessage;

        return [
            'code' => is_string(data_get($result, 'error.code')) ? data_get($result, 'error.code') : $defaultCode,
            'message' => is_string($errorMessage) ? $errorMessage : $defaultMessage,
            'status' => $status > 0 ? $status : 500,
        ];
    }
}
