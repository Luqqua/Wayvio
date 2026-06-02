@extends('layouts.sidebar')

@php
  $plans = collect($plan_cards ?? []);
  $agencyMinHubCount = max(2, (int) ($agency_hub_min ?? 2));
  $agencyMaxHubCount = max($agencyMinHubCount, (int) ($agency_hub_max ?? 10));
  $agencyDefaultHubCount = (int) ($agency_hub_count ?? $agencyMinHubCount);
  $agencyDefaultHubCount = max($agencyMinHubCount, min($agencyMaxHubCount, $agencyDefaultHubCount));
  $freePlanSlug = config('tiers.default_free_slug', 'free');
  $featuredPlanSlug = config('tiers.admin_tier_slug', 'business');
  $currentPlanSlug = $current_tier['slug'] ?? $freePlanSlug;
  $startsNewSubscription = (bool) ($starts_new_subscription ?? ($currentPlanSlug === $freePlanSlug));
  $manageInPortal = (bool) ($manage_in_portal ?? false);
  $hasActivePaidSubscription = (bool) ($has_paid_entitlement ?? !$startsNewSubscription);
  $hasPendingChange = (bool) ($has_pending_change ?? false);
  $currentPlanCard = $plans->first(function ($plan) use ($currentPlanSlug) {
    return (string) ($plan->slug ?? '') === (string) $currentPlanSlug;
  });
  $currentBlocksPerSite = (int) ($current_tier['max_links_per_page'] ?? ($currentPlanCard->max_links_per_page ?? 10));
  $freePlanCard = $plans->first(function ($plan) use ($freePlanSlug) {
    return (string) ($plan->slug ?? '') === (string) $freePlanSlug;
  });
  $canShowCancelButton = $hasActivePaidSubscription && !$hasPendingChange;
@endphp

@section('content')
<div class="container-fluid content-inner mt-n5 pt-0 pb-4 subscription-content-wrap ls-consistent-spacing">
  <div class="row">
    <div class="col-lg-12">
      <div class="card rounded border-0 shadow-sm">
        <div class="card-body">
          <div>
            <h3 class="mb-1">{{ __('Subscription') }}</h3>
            <p class="text-muted mb-0">{{ __('Manage your monthly Stripe subscription and agency capacity from one place.') }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-4">
    <div class="col-12">
      <div class="card rounded border-0 shadow-sm">
        <div class="card-body d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
          <div class="me-xl-3">
            <p class="text-uppercase text-muted small mb-1">{{ __('Current plan') }}</p>
            <h4 class="mb-0">{{ strtoupper($current_tier['name'] ?? 'Free') }}</h4>
          </div>

          <div class="d-flex flex-column flex-sm-row flex-wrap gap-3 gap-sm-4 align-items-start">
            <div>
              <p class="text-uppercase text-muted small mb-1">{{ __('Blocks/site') }}</p>
              <p class="mb-0">{{ $currentBlocksPerSite }}</p>
            </div>
            <div>
              <p class="text-uppercase text-muted small mb-1">{{ $hasActivePaidSubscription ? __('Current period ends') : __('Billing') }}</p>
              <p class="mb-0">
                @if($expires_at)
                  {{ \Illuminate\Support\Carbon::parse($expires_at)->toFormattedDateString() }}
                @else
                  {{ __('Free plan') }}
                @endif
              </p>
            </div>
            @if($expired)
              <div>
                <p class="text-uppercase text-muted small mb-1">{{ __('Status') }}</p>
                <p class="mb-0 text-danger">{{ __('Expired') }}</p>
              </div>
            @elseif($in_grace_period)
              <div>
                <p class="text-uppercase text-muted small mb-1">{{ __('Status') }}</p>
                <p class="mb-0 text-warning">{{ __('In grace period') }}</p>
              </div>
            @endif
          </div>

          @if(!empty($downgrade_notice))
            <p class="text-warning mb-0">{{ $downgrade_notice }}</p>
          @endif
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-4">
    <div class="col-12">
      <div class="card rounded border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
              <p class="text-uppercase text-muted small mb-1">{{ __('Billing model') }}</p>
              <h6 class="mb-1">{{ __('Monthly Stripe subscription') }}</h6>
              <p class="text-muted small mb-0">{{ __('New subscriptions start in Stripe Checkout. Only monthly billing is enabled right now; longer billing cycles stay disabled until a later release.') }}</p>
            </div>
            @if($manageInPortal)
              <button type="button" class="btn btn-outline-primary btn-sm billing-portal-btn">{{ __('Payment & invoices') }}</button>
            @endif
          </div>

          @if($hasActivePaidSubscription)
            <div class="alert alert-info py-2 px-3 small mb-3">
              {{ __('Upgrades apply immediately and show an estimated prorated Stripe charge. Downgrades and cancellations take effect at the end of the current billing period.') }}
            </div>
          @endif

        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-4 subscription-pricing" id="plan-carousel">
    @foreach($plans as $plan)
      @php
        $isAgency = ($plan->slug ?? '') === 'agency';
        $isCurrent = (bool) ($plan->is_current ?? false);
        $isUpgrade = (bool) ($plan->is_upgrade ?? false);
        $isPaidUpgrade = (bool) ($plan->is_paid_upgrade ?? ($hasActivePaidSubscription && $isUpgrade && !$startsNewSubscription));
        $isDowngrade = (bool) ($plan->is_downgrade ?? false);
        $isPendingTarget = (bool) ($plan->is_pending_target ?? false);
        $agencyIncludedHubs = $isAgency ? max(2, (int) ($plan->included_hubs ?? $agencyMinHubCount)) : null;
        $agencyExtraHubPrice = $isAgency ? max(0, (int) ($plan->extra_hub_price_1m ?? 900)) : null;
        $upgradePreviewAvailable = $isPaidUpgrade && (bool) ($plan->upgrade_preview_available ?? false);
        $upgradePreviewAmountDueNow = isset($plan->upgrade_preview_amount_due_now) && is_numeric($plan->upgrade_preview_amount_due_now)
          ? (int) $plan->upgrade_preview_amount_due_now
          : null;
        $upgradePreviewCurrency = strtoupper((string) ($plan->upgrade_preview_currency ?? 'usd'));
        $upgradePreviewAbs = $upgradePreviewAmountDueNow !== null
          ? number_format(abs($upgradePreviewAmountDueNow) / 100, 2)
          : null;
        $upgradePreviewSign = $upgradePreviewAmountDueNow !== null
          ? ($upgradePreviewAmountDueNow > 0 ? '+' : ($upgradePreviewAmountDueNow < 0 ? '-' : ''))
          : '';
        $upgradePreviewLabel = $upgradePreviewAmountDueNow !== null
          ? trim($upgradePreviewSign . $upgradePreviewCurrency . ' ' . $upgradePreviewAbs)
          : null;
        $canCheckout = $startsNewSubscription
          && !$isCurrent
          && !empty($plan->id)
          && ($plan->slug ?? '') !== $freePlanSlug;
        $canChange = $hasActivePaidSubscription
          && !empty($plan->id)
          && (!$isCurrent || $isAgency || $hasPendingChange);
        $isCurrentAgency = $isCurrent && $isAgency && $hasActivePaidSubscription && !$hasPendingChange;
        $isScheduledTargetLocked = $hasPendingChange && $isPendingTarget;
        $actionMode = $canCheckout ? 'checkout' : ($canChange ? 'change' : 'disabled');
        $canChoose = $actionMode !== 'disabled';
        $isFeaturedCard = !$isCurrent && !$hasActivePaidSubscription && ($plan->slug ?? '') === $featuredPlanSlug;
        $hasActivePriceBox = $isCurrent;
        $buttonClass = !$canChoose
          ? 'btn-outline-secondary'
          : (($isCurrent || $isFeaturedCard) ? 'btn-primary' : 'btn-outline-primary');
        $columnClasses = 'col-12 col-md-6';
        $planSlug = (string) ($plan->slug ?? '');
        $blockCount = max(1, (int) ($plan->max_links_per_page ?? 10));
        $agencyExtraHubPriceLabel = $isAgency ? '€' . number_format($agencyExtraHubPrice / 100, 2) : '€9.00';
        $planFocus = '';
        $planSummary = '';
        $featureRows = [];
        $planCheckoutCta = null;

        if ($planSlug === 'free') {
          $planFocus = __('Entry point for hobby projects.');
          $planSummary = __('Ideal to get to know Wayvio.');
          $featureRows = [
            __('1 hub included'),
            __('Up to :count blocks per hub', ['count' => $blockCount]),
            __('Intuitive editor'),
            __('Header and hero blocks'),
            __('Basic analytics'),
          ];
          $planCheckoutCta = __('Start free');
        } elseif ($planSlug === 'basic') {
          $planFocus = __('Personalization and professional look.');
          $planSummary = __('For creators who take their branding seriously.');
          $featureRows = [
            __('1 hub included'),
            __('Up to :count blocks per hub', ['count' => $blockCount]),
            __('Remove Wayvio branding'),
            __('Contact forms (90-day submission history)'),
            __('Advanced analytics (90-day history)'),
          ];
          $planCheckoutCta = __('Upgrade now');
        } elseif ($planSlug === 'pro') {
          $planFocus = __('Full control and SEO.');
          $planSummary = __('Perfect for professionals and growing brands.');
          $featureRows = [
            __('Everything in Basic, plus:'),
            __('Custom domain'),
            __('Full SEO control (meta tags)'),
            __('UTM parameter analysis'),
            __('Analytics history (1 year)'),
            __('Form submissions (365-day history)'),
          ];
          $planCheckoutCta = __('Choose Pro');
        } elseif ($planSlug === 'agency') {
          $planFocus = __('Scaling and client management.');
          $planSummary = __('The complete solution for teams and agencies.');
          $featureRows = [
            __('Everything in Pro, plus:'),
            __(':count hubs included, up to :max total', ['count' => $agencyIncludedHubs, 'max' => $agencyMaxHubCount]),
            __('Full white label (custom domain and logo)'),
            __('Additional hubs for :price each', ['price' => $agencyExtraHubPriceLabel]),
            __('Analytics history (1 year)'),
            __('Up to :count blocks per hub', ['count' => $blockCount]),
          ];
          $planCheckoutCta = __('Create agency account');
        }
      @endphp
      <div class="{{ $columnClasses }}">
        <article class="card h-100 border-0 shadow-sm plan-card {{ $isCurrent ? 'plan-current' : '' }} {{ $isFeaturedCard ? 'plan-featured' : '' }} {{ ($isDowngrade && !$hasActivePaidSubscription) ? 'plan-downgrade' : '' }}">
          <div class="card-body p-4 plan-card-body">
            <div class="plan-card-top">
              <div class="prc-box subscription-price-box {{ $hasActivePriceBox ? 'active' : '' }} mb-4">
                <span class="type">{{ strtoupper($plan->name) }}</span>
                <div class="plan-price-row">
                  <h3
                    class="h3 mb-0 plan-price-value {{ $isAgency ? 'agency-total-price' : '' }}"
                    @if($isAgency)
                      data-base-price="{{ (int) ($plan->price_1m ?? 0) }}"
                      data-extra-price="{{ $agencyExtraHubPrice }}"
                      data-included-hubs="{{ $agencyIncludedHubs }}"
                    @endif
                  >
                    €{{ number_format(((int) ($plan->price_1m ?? 0)) / 100, 2) }}
                  </h3>
                  <span class="plan-price-period">{{ __('/mo') }}</span>
                </div>
              </div>

              <h5 class="mb-1">{{ $plan->name }}</h5>
              @if($planFocus !== '')
                <p class="small text-muted mb-1">{{ __('Focus:') }} {{ $planFocus }}</p>
              @endif
              @if($planSummary !== '')
                <p class="text-muted small mb-3 plan-description">{{ $planSummary }}</p>
              @endif
              @if($isPaidUpgrade)
                <p class="small text-primary fw-semibold mb-2">{{ __('Upgrade') }}</p>
                @if($upgradePreviewAvailable && $upgradePreviewLabel)
                  <p class="small text-primary mb-3">{{ __('Estimated prorated charge now:') }} {{ $upgradePreviewLabel }}</p>
                @else
                  <p class="small text-muted mb-3">{{ __('Proration preview unavailable right now. Stripe will calculate it when you apply the upgrade.') }}</p>
                @endif
              @endif
            </div>

            <div class="plan-card-details">
              <p class="small fw-semibold text-uppercase text-muted mb-2">{{ __('Features:') }}</p>
              <ul class="list-unstyled small text-muted mb-4 plan-feature-list">
                @foreach($featureRows as $featureRow)
                  <li class="plan-feature-item">{{ $featureRow }}</li>
                @endforeach
              </ul>

              @if($isAgency)
                <div class="text-start mb-4 plan-agency-controls">
                  <label class="form-label small mb-1">{{ __('Agency slots incl. owner (:min-:max)', ['min' => $agencyMinHubCount, 'max' => $agencyMaxHubCount]) }}</label>
                  <input
                    type="number"
                    min="{{ $agencyMinHubCount }}"
                    max="{{ $agencyMaxHubCount }}"
                    step="1"
                    class="form-control form-control-sm agency-hub-count"
                    value="{{ $agencyDefaultHubCount }}"
                  >
                  <small class="text-muted d-block mt-2">{{ __('Owner page counts as one slot; remaining slots can be used for managed hubs.') }}</small>
                  <small class="text-warning d-block mt-1">{{ __('Need more than :total total hubs (:extra extra)? Contact support.', ['total' => $agencyMaxHubCount, 'extra' => $agencyMaxHubCount - $agencyIncludedHubs]) }}</small>
                </div>
              @endif
            </div>

            <div class="plan-card-actions">
              <button
                type="button"
                class="btn btn-sm w-100 {{ $buttonClass }} plan-action-btn"
                data-action="{{ $actionMode }}"
                data-tier="{{ $plan->id }}"
                data-slug="{{ $plan->slug }}"
                data-plan-name="{{ $plan->name }}"
                data-monthly-price-cents="{{ (int) ($plan->price_1m ?? 0) }}"
                data-is-paid-upgrade="{{ $isPaidUpgrade ? 1 : 0 }}"
                data-is-downgrade="{{ $isDowngrade ? 1 : 0 }}"
                data-current-plan="{{ $isCurrent ? 1 : 0 }}"
                data-has-pending-change="{{ $hasPendingChange ? 1 : 0 }}"
                data-current-hub-count="{{ ($isCurrent && $isAgency) ? $agencyDefaultHubCount : 0 }}"
                data-current-label="{{ __('Current plan') }}"
                data-update-label="{{ $isAgency && $hasActivePaidSubscription ? __('Update agency hubs') : __('Current plan') }}"
                @disabled(!$canChoose || $isCurrentAgency || $isScheduledTargetLocked)
              >
                @if($startsNewSubscription && $planCheckoutCta && !$isCurrent)
                  {{ $planCheckoutCta }}
                @elseif($isCurrent && $hasPendingChange)
                  {{ __('Keep :plan', ['plan' => $plan->name]) }}
                @elseif($isCurrent)
                  {{ __('Current plan') }}
                @elseif($isScheduledTargetLocked)
                  {{ __('Already scheduled') }}
                @elseif($actionMode === 'change' && ($plan->slug ?? '') === $freePlanSlug)
                  {{ __('Cancel at period end') }}
                @elseif($actionMode === 'change' && $isDowngrade)
                  {{ __('Switch to :plan on renewal', ['plan' => $plan->name]) }}
                @elseif($actionMode === 'change' && $isPaidUpgrade)
                  {{ __('Upgrade to :plan now', ['plan' => $plan->name]) }}
                @elseif($actionMode === 'change')
                  {{ __('Switch to :plan now', ['plan' => $plan->name]) }}
                @elseif($actionMode === 'checkout' && $planCheckoutCta)
                  {{ $planCheckoutCta }}
                @else
                  {{ __('Start :plan monthly', ['plan' => $plan->name]) }}
                @endif
              </button>
            </div>
          </div>
        </article>
      </div>
    @endforeach
  </div>

  @if($canShowCancelButton && $freePlanCard && !empty($freePlanCard->id))
    <div class="row g-3 mt-4">
      <div class="col-12">
        <div class="card rounded border-0 shadow-sm">
          <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
              <p class="fw-semibold mb-1">{{ __('Cancel subscription') }}</p>
              <p class="text-muted small mb-0">{{ __('Cancellation takes effect at the end of the current billing period. Your access remains active until then.') }}</p>
            </div>
            <button
              type="button"
              class="btn btn-sm btn-outline-danger plan-action-btn"
              data-action="change"
              data-tier="{{ $freePlanCard->id }}"
              data-slug="{{ $freePlanSlug }}"
              data-plan-name="{{ $freePlanCard->name ?? 'Free' }}"
              data-monthly-price-cents="0"
              data-is-paid-upgrade="0"
              data-is-downgrade="1"
              data-current-plan="0"
              data-has-pending-change="0"
              data-current-hub-count="0"
              data-current-label="{{ __('Current plan') }}"
              data-update-label="{{ __('Current plan') }}"
            >
              {{ __('Cancel now') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  @endif

  <div class="alert alert-success mt-3 d-none" id="sub-success"></div>
  <div class="alert alert-danger mt-3 d-none" id="sub-errors"></div>
  <div class="alert mt-3 d-none" id="checkout-status-box" role="status" aria-live="polite">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <strong id="checkout-status-title">{{ __('Checkout verification') }}</strong>
      <code class="small d-none" id="checkout-status-session"></code>
    </div>
    <p class="small mb-2" id="checkout-status-message"></p>
    <div class="progress mb-2" style="height: 0.5rem;">
      <div class="progress-bar progress-bar-striped progress-bar-animated" id="checkout-status-progress" style="width: 8%;"></div>
    </div>
    <p class="small text-muted mb-0" id="checkout-status-help"></p>
  </div>

  <div class="modal fade" id="billing-action-confirm-modal" tabindex="-1" aria-labelledby="billing-action-confirm-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="billing-action-confirm-title">{{ __('Confirm action') }}</h5>
          <button type="button" class="btn-close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0" id="billing-action-confirm-message">
            {{ __('Please confirm this action.') }}
          </p>
          <div class="form-check mt-3 d-none" id="billing-withdrawal-waiver-wrap">
            <input class="form-check-input" type="checkbox" id="billing-withdrawal-waiver-check">
            <label class="form-check-label small" for="billing-withdrawal-waiver-check">
              {{ __('I explicitly agree that access provisioning starts immediately. I acknowledge that, in the event of withdrawal within the 14-day withdrawal period, I owe proportionate compensation for the services already provided up to the time of withdrawal.') }}
            </label>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" id="billing-action-confirm-cancel" data-dismiss="modal" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
          <button type="button" class="btn btn-primary" id="billing-action-confirm-accept">{{ __('Confirm now') }}</button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .plan-card {
    border: 0;
    border-radius: 1rem;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
  }
  .plan-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 1.25rem 2rem rgba(15, 23, 42, 0.09);
  }
  .plan-card-body {
    height: 100%;
    display: flex;
    flex-direction: column;
    text-align: center;
  }
  .plan-card-actions {
    margin-top: auto;
  }
  .plan-card.plan-current {
    box-shadow: 0 0 0 2px rgba(var(--bs-success-rgb), 0.16);
  }
  .plan-card.plan-featured:not(.plan-current) {
    box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), 0.14);
  }
  .plan-card.plan-downgrade {
    opacity: 0.92;
  }
  .subscription-price-box {
    background: rgba(var(--bs-primary-rgb), 0.12);
    cursor: default;
    min-height: 108px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.35rem;
    position: relative;
    overflow: visible;
    padding: 1rem 1rem 0.95rem;
    border-radius: 1rem;
  }
  .subscription-price-box.active {
    background: var(--bs-primary);
    box-shadow: 0 1rem 1.5rem -1rem rgba(var(--bs-primary-rgb), 0.9);
  }
  .subscription-price-box .type {
    background: var(--bs-primary);
    color: #fff;
    font-size: 0.72rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    white-space: nowrap;
    display: inline-flex;
    justify-content: center;
    align-self: center;
    margin: 0 auto;
    position: static !important;
    inset: auto !important;
    top: auto !important;
    left: auto !important;
    transform: none !important;
    padding: 0.35rem 0.8rem;
    border-radius: 999px;
    line-height: 1;
    box-shadow: none;
  }
  .subscription-price-box .type:before,
  .subscription-price-box .type:after {
    content: none !important;
    display: none !important;
    border: 0 !important;
  }
  .subscription-price-box.active .type {
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
  }
  .plan-price-row {
    display: flex;
    align-items: flex-end;
    justify-content: center;
    gap: 0.2rem;
    line-height: 1;
  }
  .plan-price-value {
    color: var(--bs-primary-shade-80, #232d42);
    line-height: 1;
  }
  .plan-price-period {
    color: var(--bs-body-color);
    font-size: 1rem;
    font-weight: 500;
    opacity: 0.7;
    margin-bottom: 0.25rem;
  }
  .subscription-price-box.active .plan-price-period,
  .subscription-price-box.active .plan-price-value {
    color: #fff;
  }
  .plan-description {
    min-height: 2.6rem;
  }
  .plan-feature-list {
    display: flex;
    flex-direction: column;
    gap: 0.7rem;
  }
  .plan-feature-item {
    line-height: 1.45;
  }
  .plan-agency-controls {
    padding: 0.9rem;
    border-radius: 0.85rem;
    background: rgba(var(--bs-primary-rgb), 0.06);
  }
  body.dark .plan-card:hover {
    box-shadow: 0 1.25rem 2rem rgba(0, 0, 0, 0.28);
  }
  body.dark .plan-card.plan-current {
    box-shadow: 0 0 0 2px rgba(var(--bs-success-rgb), 0.25);
  }
  body.dark .plan-card.plan-featured:not(.plan-current) {
    box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), 0.22);
  }
  body.dark .subscription-price-box {
    background: rgba(var(--bs-primary-rgb), 0.24);
  }
  body.dark .plan-description,
  body.dark .plan-feature-item {
    color: #adb3c1 !important;
  }
  body.dark .plan-price-period,
  body.dark .plan-price-value {
    color: #fff;
  }
  body.dark .plan-agency-controls {
    background: rgba(var(--bs-primary-rgb), 0.12);
  }
  .subscription-content-wrap {
    padding-bottom: 5rem !important;
    margin-bottom: 1rem;
  }
  #billing-action-confirm-modal {
    z-index: 1300;
  }
  .modal-backdrop.billing-action-confirm-backdrop {
    z-index: 1290;
  }
  #billing-action-confirm-modal .modal-dialog {
    max-width: 34rem;
  }
  #billing-action-confirm-modal .modal-body p {
    line-height: 1.45;
  }
  body.dark #billing-action-confirm-modal .modal-content {
    background-color: #1f2430;
    color: #e6e8ee;
    border: 1px solid rgba(255, 255, 255, 0.14);
  }
  body.dark #billing-action-confirm-modal .modal-header,
  body.dark #billing-action-confirm-modal .modal-footer {
    border-color: rgba(255, 255, 255, 0.14);
  }
  body.dark #billing-action-confirm-modal .modal-title,
  body.dark #billing-action-confirm-modal #billing-action-confirm-message,
  body.dark #billing-action-confirm-modal .form-check-label {
    color: #f2f4f8;
  }
  body.dark #billing-action-confirm-modal .form-check-input {
    background-color: #131925;
    border-color: #6f7f98;
  }
  body.dark #billing-action-confirm-modal .form-check-input:checked {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
  }
  body.dark #billing-action-confirm-modal .btn-outline-secondary {
    color: #d7deea;
    border-color: #6f7f98;
  }
  body.dark #billing-action-confirm-modal .btn-outline-secondary:hover,
  body.dark #billing-action-confirm-modal .btn-outline-secondary:focus {
    color: #ffffff;
    background-color: rgba(111, 127, 152, 0.22);
    border-color: #8ea0bc;
  }
  body.dark #billing-action-confirm-modal .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
    opacity: 0.85;
  }
  body.dark #billing-action-confirm-modal .btn-close:hover {
    opacity: 1;
  }
  @media (max-width: 767.98px) {
    .subscription-price-box {
      min-height: 96px;
      padding: 0.9rem 0.9rem 0.85rem;
    }
    .plan-description {
      min-height: 0;
    }
    .plan-feature-list {
      gap: 0.55rem;
    }
  }
  @media (max-width: 575.98px) {
    #billing-action-confirm-modal .modal-dialog {
      margin: 0.75rem;
      max-width: none;
    }
    #billing-action-confirm-modal .modal-content {
      border-radius: 0.9rem;
      max-height: calc(100vh - 1.5rem);
      max-height: calc(100dvh - 1.5rem);
    }
    #billing-action-confirm-modal .modal-body {
      overflow-y: auto;
    }
    #billing-action-confirm-modal .modal-header,
    #billing-action-confirm-modal .modal-body,
    #billing-action-confirm-modal .modal-footer {
      padding-left: 0.95rem;
      padding-right: 0.95rem;
    }
    #billing-action-confirm-modal .modal-footer {
      display: grid;
      grid-template-columns: 1fr;
      gap: 0.55rem;
    }
    #billing-action-confirm-modal .modal-footer .btn {
      width: 100%;
      margin: 0;
    }
  }
</style>

<script>
(() => {
  const successBox = document.getElementById('sub-success');
  const errorBox = document.getElementById('sub-errors');
  const checkoutStatusBox = document.getElementById('checkout-status-box');
  const checkoutStatusTitle = document.getElementById('checkout-status-title');
  const checkoutStatusMessage = document.getElementById('checkout-status-message');
  const checkoutStatusProgress = document.getElementById('checkout-status-progress');
  const checkoutStatusHelp = document.getElementById('checkout-status-help');
  const checkoutStatusSession = document.getElementById('checkout-status-session');
  const agencyHubMin = Number(@json($agencyMinHubCount));
  const agencyHubMax = Number(@json($agencyMaxHubCount));
  const checkoutConfirmEndpoint = @json(route('billing.checkout.confirm'));
  const checkoutDashboardEndpoint = @json(route('subscription.dashboard'));
  const previewChangeEndpoint = @json(route('billing.change-plan.preview'));
  const csrfToken = @json(csrf_token());
  const checkoutPollIntervalMs = 2000;
  const checkoutPollTimeoutMs = 90000;
  const urlParams = new URLSearchParams(window.location.search);
  const planActionButtons = Array.from(document.querySelectorAll('.plan-action-btn'));
  const actionConfirmModalEl = document.getElementById('billing-action-confirm-modal');
  const actionConfirmTitleEl = document.getElementById('billing-action-confirm-title');
  const actionConfirmMessageEl = document.getElementById('billing-action-confirm-message');
  const actionConfirmAcceptEl = document.getElementById('billing-action-confirm-accept');
  const withdrawalWaiverWrapEl = document.getElementById('billing-withdrawal-waiver-wrap');
  const withdrawalWaiverCheckEl = document.getElementById('billing-withdrawal-waiver-check');
  let checkoutVerificationRunning = false;
  let planActionInFlight = false;
  let actionConfirmModalInstance = null;
  let actionConfirmResolve = null;
  let checkoutWithdrawalWaiverAccepted = false;
  const pageActionIdempotencyScope = (() => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }

    return `${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 12)}`;
  })();
  const actionIdempotencyStorageKey = 'wayvio.billing.action_idempotency.v1';
  const actionIdempotencyTtlMs = 15 * 60 * 1000;
  const i18n = {
    subscriptionUpdated: @json(__('Subscription updated.')),
    genericError: @json(__('Something went wrong.')),
    checkoutVerification: @json(__('Checkout verification')),
    processing: @json(__('Processing')),
    checkoutOnlyWithWaiver: @json(__('Checkout is only possible with an acknowledged withdrawal waiver.')),
    checkoutStartPaid: @json(__('Start paid subscription')),
    purchaseNow: @json(__('Complete paid purchase now')),
    confirmAction: @json(__('Confirm action')),
    confirmNow: @json(__('Confirm now')),
    pleaseConfirmAction: @json(__('Please confirm this action.')),
    stayOnCurrentPlan: @json(__('Stay on current plan?')),
    removePlannedChange: @json(__('Remove planned change')),
    removePlannedChangeText: @json(__('The scheduled cancellation or downgrade will be removed. Your current plan will then renew normally.')),
    confirmDowngrade: @json(__('Confirm downgrade?')),
    confirmDowngradeAction: @json(__('Confirm downgrade')),
    downgradeAtPeriodEnd: @json(__('The change takes effect at the end of the current billing period.')),
    paidUpgradeConfirm: @json(__('Confirm paid upgrade')),
    paidUpgradeAction: @json(__('Confirm paid upgrade')),
    proratedChargeText: @json(__('Stripe may apply an immediate prorated charge to your saved payment method.')),
    estimatedExtraNow: @json(__('Estimated additional charge now: :amount')),
    prorationCalculatedAtConfirm: @json(__('Stripe will calculate prorated costs when you confirm.')),
    newMonthlyPrice: @json(__('New monthly total price: :amount')),
    chooseDifferentHubCount: @json(__('Choose a different agency hub count to update the current plan.')),
    agencySlotsRange: @json(__('Agency slot count (incl. owner) must be between :min and :max.')),
    confirmWithdrawalWaiver: @json(__('Please confirm the withdrawal waiver to start checkout.')),
    unableStartCheckout: @json(__('Unable to start checkout.')),
    unableChangeSubscription: @json(__('Unable to change subscription.')),
    unableOpenPortal: @json(__('Unable to open Stripe billing portal.')),
    unableVerifyCheckout: @json(__('Unable to verify checkout.')),
    supportHint: @json(__('If this takes too long or fails repeatedly, contact support and include the session id.')),
    checkoutVerificationInProgress: @json(__('Checkout verification in progress')),
    waitingForWebhook: @json(__('Waiting for webhook confirmation from Stripe.')),
    checkoutBeingVerified: @json(__('Checkout is being verified.')),
    paymentConfirmed: @json(__('Payment confirmed')),
    pageWillRefresh: @json(__('Your subscription page will refresh automatically.')),
    paymentVerificationFailed: @json(__('Payment verification failed')),
    verificationRetry: @json(__('Verification retry')),
    temporaryIssueCheckingStatus: @json(__('Temporary issue while checking checkout status.')),
    paymentConfirmationTimedOut: @json(__('Payment confirmation timed out. Please reload this page or contact support.')),
    verificationTimedOut: @json(__('Verification timed out')),
    checkoutCanceled: @json(__('Checkout canceled')),
    checkoutCanceledText: @json(__('Checkout was canceled. No payment was recorded.')),
    verificationFailed: @json(__('Verification failed')),
    missingCheckoutSession: @json(__('Stripe returned without a checkout session id. Please contact support.')),
    processingLabel: @json(__('Processing...')),
    updateAgencyHubs: @json(__('Update agency hubs')),
    currentPlan: @json(__('Current plan')),
    thisPlanLabel: @json(__('this plan')),
    plannedPrice: @json(__('Planned price: :amount')),
    checkoutStartMessage: @json(__('You are about to start a paid :plan subscription through Stripe. The amount will be charged to your saved Stripe payment method.')),
    agencySlotUpgradeMessage: @json(__('You are increasing agency slots. Stripe may apply an immediate prorated charge to your saved payment method.')),
    paidPlanUpgradeMessage: @json(__('You are about to upgrade to :plan. Stripe may apply an immediate prorated charge to your saved payment method.')),
    newMonthlyPriceNextRenewal: @json(__('New monthly price from next renewal: :amount')),
  };
  const t = (template, replacements = {}) => {
    return String(template || '').replace(/:([a-zA-Z_]+)/g, (match, token) => {
      if (Object.prototype.hasOwnProperty.call(replacements, token)) {
        return String(replacements[token]);
      }
      return match;
    });
  };

  const showSuccess = (msg) => {
    successBox.textContent = msg || i18n.subscriptionUpdated;
    successBox.classList.remove('d-none');
  };

  const clearSuccess = () => {
    successBox.textContent = '';
    successBox.classList.add('d-none');
  };

  const showError = (msg) => {
    clearSuccess();
    errorBox.textContent = msg || i18n.genericError;
    errorBox.classList.remove('d-none');
  };

  const clearError = () => {
    errorBox.textContent = '';
    errorBox.classList.add('d-none');
  };

  const resetCheckoutStatusClass = () => {
    checkoutStatusBox.classList.remove('alert-info', 'alert-success', 'alert-warning', 'alert-danger');
  };

  const showCheckoutStatus = ({
    tone = 'info',
    title = i18n.checkoutVerification,
    message = i18n.processing,
    progress = 10,
    sessionId = '',
    help = '',
    animated = true,
  }) => {
    resetCheckoutStatusClass();
    const classByTone = {
      info: 'alert-info',
      success: 'alert-success',
      warning: 'alert-warning',
      danger: 'alert-danger',
    };
    checkoutStatusBox.classList.add(classByTone[tone] || 'alert-info');
    checkoutStatusBox.classList.remove('d-none');
    checkoutStatusTitle.textContent = title;
    checkoutStatusMessage.textContent = message;
    checkoutStatusHelp.textContent = help;
    const normalizedProgress = Math.max(2, Math.min(100, Number(progress) || 2));
    checkoutStatusProgress.style.width = `${normalizedProgress}%`;
    checkoutStatusProgress.classList.toggle('progress-bar-animated', Boolean(animated));
    if (sessionId) {
      checkoutStatusSession.textContent = sessionId;
      checkoutStatusSession.classList.remove('d-none');
    } else {
      checkoutStatusSession.textContent = '';
      checkoutStatusSession.classList.add('d-none');
    }
  };

  const hideCheckoutStatus = () => {
    checkoutStatusBox.classList.add('d-none');
    resetCheckoutStatusClass();
    checkoutStatusTitle.textContent = i18n.checkoutVerification;
    checkoutStatusMessage.textContent = '';
    checkoutStatusHelp.textContent = '';
    checkoutStatusSession.textContent = '';
    checkoutStatusSession.classList.add('d-none');
    checkoutStatusProgress.style.width = '8%';
    checkoutStatusProgress.classList.add('progress-bar-animated');
  };

  const cleanupCheckoutQuery = () => {
    const cleanUrl = new URL(window.location.href);
    ['checkout', 'success', 'canceled', 'session_id'].forEach((key) => {
      cleanUrl.searchParams.delete(key);
    });
    const nextUrl = `${cleanUrl.pathname}${cleanUrl.search}${cleanUrl.hash}`;
    window.history.replaceState({}, '', nextUrl);
  };

  const euro = (cents) => `€${(Number(cents || 0) / 100).toFixed(2)}`;
  const normalizeCurrencyCode = (value) => {
    const code = String(value || '').trim().slice(0, 3);
    return code ? code.toUpperCase() : 'EUR';
  };
  const formatMoney = (cents, currency = 'eur') => {
    const amount = Number(cents || 0) / 100;
    const code = normalizeCurrencyCode(currency);
    try {
      return new Intl.NumberFormat('de-DE', {
        style: 'currency',
        currency: code,
      }).format(amount);
    } catch (_err) {
      return euro(cents);
    }
  };
  const parseCents = (value) => {
    const parsed = Number(value);
    if (!Number.isFinite(parsed)) {
      return null;
    }

    return Math.round(parsed);
  };
  const readActionIdempotencyMap = () => {
    if (!window.sessionStorage) {
      return {};
    }

    try {
      const raw = window.sessionStorage.getItem(actionIdempotencyStorageKey);
      if (!raw) {
        return {};
      }

      const decoded = JSON.parse(raw);
      return decoded && typeof decoded === 'object' ? decoded : {};
    } catch (_err) {
      return {};
    }
  };
  const writeActionIdempotencyMap = (map) => {
    if (!window.sessionStorage) {
      return;
    }

    try {
      window.sessionStorage.setItem(actionIdempotencyStorageKey, JSON.stringify(map));
    } catch (_err) {
      // ignore storage failures; request will still be processed without persisted key reuse
    }
  };
  const pruneActionIdempotencyMap = (map, nowMs) => {
    const result = {};
    Object.entries(map || {}).forEach(([fingerprint, payload]) => {
      if (!payload || typeof payload !== 'object') {
        return;
      }

      const key = String(payload.key || '').trim();
      const createdAt = Number(payload.created_at || 0);
      if (!key || !Number.isFinite(createdAt)) {
        return;
      }

      if ((nowMs - createdAt) > actionIdempotencyTtlMs) {
        return;
      }

      result[fingerprint] = {
        key,
        created_at: createdAt,
      };
    });

    return result;
  };
  const buildClientActionIdempotencyKey = () => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }

    return `${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 12)}`;
  };
  const actionIdempotencyFingerprint = ({
    action,
    tierId,
    tierSlug,
    hubCount,
  }) => {
    const normalizedAction = String(action || '').trim().toLowerCase() || 'unknown';
    const normalizedTierId = Number(tierId || 0);
    const normalizedSlug = String(tierSlug || '').trim().toLowerCase() || 'unknown';
    const normalizedHubCount = normalizedSlug === 'agency'
      ? Number(hubCount || 0)
      : 0;

    return `${pageActionIdempotencyScope}|${normalizedAction}|${normalizedTierId}|${normalizedSlug}|${normalizedHubCount}`;
  };
  const getActionIdempotencyKey = ({
    action,
    tierId,
    tierSlug,
    hubCount,
  }) => {
    const nowMs = Date.now();
    const fingerprint = actionIdempotencyFingerprint({
      action,
      tierId,
      tierSlug,
      hubCount,
    });
    const activeMap = pruneActionIdempotencyMap(readActionIdempotencyMap(), nowMs);
    const existingEntry = activeMap[fingerprint];
    const existingKey = String(existingEntry?.key || '').trim();
    if (existingKey) {
      writeActionIdempotencyMap(activeMap);
      return existingKey;
    }

    const key = buildClientActionIdempotencyKey();
    activeMap[fingerprint] = {
      key,
      created_at: nowMs,
    };
    writeActionIdempotencyMap(activeMap);

    return key;
  };
  const clearActionIdempotencyKey = ({
    action,
    tierId,
    tierSlug,
    hubCount,
  }) => {
    const activeMap = pruneActionIdempotencyMap(readActionIdempotencyMap(), Date.now());
    const fingerprint = actionIdempotencyFingerprint({
      action,
      tierId,
      tierSlug,
      hubCount,
    });

    if (!Object.prototype.hasOwnProperty.call(activeMap, fingerprint)) {
      return;
    }

    delete activeMap[fingerprint];
    writeActionIdempotencyMap(activeMap);
  };

  const estimatePlanMonthlyAmountCents = (btn, tierSlug, hubCount = null) => {
    if (!btn) return null;

    if (tierSlug === 'agency') {
      const card = btn.closest('.plan-card');
      const priceEl = card ? card.querySelector('.agency-total-price') : null;
      if (!priceEl) return null;

      const basePrice = Number(priceEl.dataset.basePrice || 0);
      const extraPrice = Number(priceEl.dataset.extraPrice || 0);
      const includedHubs = Number(priceEl.dataset.includedHubs || agencyHubMin);
      const selectedHubs = Number.isInteger(hubCount) ? hubCount : includedHubs;
      const extraHubs = Math.max(0, selectedHubs - includedHubs);

      return Math.max(0, Math.round(basePrice + (extraHubs * extraPrice)));
    }

    const cents = Number(btn.dataset.monthlyPriceCents || 0);
    return Number.isFinite(cents) ? Math.max(0, Math.round(cents)) : null;
  };

  const fetchChangePreview = async ({
    tierId,
    tierSlug,
    hubCount,
  }) => {
    if (!Number.isInteger(tierId) || tierId <= 0 || !previewChangeEndpoint) {
      return null;
    }

    const payload = {
      tier_id: tierId,
    };

    if (tierSlug === 'agency') {
      payload.hub_count = Number(hubCount || 0);
    }

    try {
      const response = await fetch(previewChangeEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
        body: JSON.stringify(payload),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data || typeof data !== 'object') {
        return null;
      }

      return data;
    } catch (_err) {
      return null;
    }
  };

  const ensureActionConfirmModal = () => {
    if (!actionConfirmModalEl || !window.bootstrap || !window.bootstrap.Modal) {
      return null;
    }

    if (actionConfirmModalEl.parentElement !== document.body) {
      document.body.appendChild(actionConfirmModalEl);
    }

    if (actionConfirmModalInstance) {
      return actionConfirmModalInstance;
    }

    actionConfirmModalInstance = new window.bootstrap.Modal(actionConfirmModalEl, {
      backdrop: 'static',
      keyboard: true,
    });

    actionConfirmModalEl.addEventListener('shown.bs.modal', () => {
      const backdrops = document.querySelectorAll('.modal-backdrop');
      const backdrop = backdrops.length ? backdrops[backdrops.length - 1] : null;
      if (backdrop) {
        backdrop.classList.add('billing-action-confirm-backdrop');
      }
    });

    actionConfirmModalEl.addEventListener('hidden.bs.modal', () => {
      checkoutWithdrawalWaiverAccepted = false;
      if (withdrawalWaiverCheckEl) {
        withdrawalWaiverCheckEl.checked = false;
        withdrawalWaiverCheckEl.required = false;
      }
      if (withdrawalWaiverWrapEl) {
        withdrawalWaiverWrapEl.classList.add('d-none');
      }
      if (actionConfirmAcceptEl) {
        actionConfirmAcceptEl.disabled = false;
        actionConfirmAcceptEl.dataset.requiresWithdrawalWaiver = '0';
      }

      if (actionConfirmResolve) {
        const resolve = actionConfirmResolve;
        actionConfirmResolve = null;
        resolve(false);
      }
    });

    return actionConfirmModalInstance;
  };

  const toggleWithdrawalWaiverRequirement = (required) => {
    if (!withdrawalWaiverWrapEl || !withdrawalWaiverCheckEl || !actionConfirmAcceptEl) {
      return;
    }

    withdrawalWaiverWrapEl.classList.toggle('d-none', !required);
    withdrawalWaiverCheckEl.required = required;
    if (!required) {
      withdrawalWaiverCheckEl.checked = false;
      actionConfirmAcceptEl.disabled = false;
      return;
    }

    actionConfirmAcceptEl.disabled = !withdrawalWaiverCheckEl.checked;
  };

  const confirmPlanAction = async ({
    btn,
    action,
    tierSlug,
    hubCount,
  }) => {
    checkoutWithdrawalWaiverAccepted = false;
    const planName = String(btn?.dataset.planName || tierSlug || i18n.thisPlanLabel);
    const tierId = Number(btn?.dataset.tier || 0);
    const isCurrentPlan = Number(btn?.dataset.currentPlan || 0) === 1;
    const hasPendingChange = Number(btn?.dataset.hasPendingChange || 0) === 1;
    const currentHubCount = Number(btn?.dataset.currentHubCount || 0);
    const isPaidUpgrade = Number(btn?.dataset.isPaidUpgrade || 0) === 1;
    const isDowngrade = Number(btn?.dataset.isDowngrade || 0) === 1;
    const isHubUpgrade = tierSlug === 'agency'
      && isCurrentPlan
      && Number.isInteger(hubCount)
      && Number.isInteger(currentHubCount)
      && hubCount > currentHubCount;
    const isHubDowngrade = tierSlug === 'agency'
      && isCurrentPlan
      && Number.isInteger(hubCount)
      && Number.isInteger(currentHubCount)
      && hubCount < currentHubCount;
    const shouldFetchPreview = action === 'change' && (isPaidUpgrade || isHubUpgrade);
    const preview = shouldFetchPreview
      ? await fetchChangePreview({
        tierId,
        tierSlug,
        hubCount,
      })
      : null;
    const estimatedCents = estimatePlanMonthlyAmountCents(btn, tierSlug, hubCount);
    const estimatedPriceLabel = estimatedCents !== null ? `${formatMoney(estimatedCents, 'eur')} ${@json(__('/month'))}` : null;
    const previewAmountDueNow = parseCents(preview?.amount_due_now);
    const previewCurrency = String(preview?.currency || 'eur').toLowerCase();
    const previewNowLabel = previewAmountDueNow !== null
      ? formatMoney(previewAmountDueNow, previewCurrency)
      : null;
    const previewSuggestsDowngrade = Boolean(preview?.is_downgrade || preview?.is_cancellation);

    let title = i18n.confirmAction;
    let confirmText = i18n.confirmNow;
    let message = i18n.pleaseConfirmAction;
    const requiresWithdrawalWaiver = action === 'checkout';

    if (action === 'checkout') {
      title = i18n.checkoutStartPaid;
      confirmText = i18n.purchaseNow;
      message = t(i18n.checkoutStartMessage, { plan: planName });
      if (estimatedPriceLabel) {
        message += ' ' + t(i18n.plannedPrice, { amount: estimatedPriceLabel });
      }
    } else if (isHubUpgrade) {
      title = i18n.paidUpgradeConfirm;
      confirmText = i18n.paidUpgradeAction;
      message = i18n.agencySlotUpgradeMessage;
      if (previewNowLabel) {
        message += ' ' + t(i18n.estimatedExtraNow, { amount: previewNowLabel });
      } else {
        message += ' ' + i18n.prorationCalculatedAtConfirm;
      }
      if (estimatedPriceLabel) {
        message += ' ' + t(i18n.newMonthlyPrice, { amount: estimatedPriceLabel });
      }
    } else if (isPaidUpgrade) {
      title = i18n.paidUpgradeConfirm;
      confirmText = i18n.paidUpgradeAction;
      message = t(i18n.paidPlanUpgradeMessage, { plan: planName });
      if (previewNowLabel) {
        message += ' ' + t(i18n.estimatedExtraNow, { amount: previewNowLabel });
      } else {
        message += ' ' + i18n.prorationCalculatedAtConfirm;
      }
      if (estimatedPriceLabel) {
        message += ' ' + t(i18n.newMonthlyPrice, { amount: estimatedPriceLabel });
      }
    } else if (action === 'change' && isCurrentPlan && hasPendingChange) {
      title = i18n.stayOnCurrentPlan;
      confirmText = i18n.removePlannedChange;
      message = i18n.removePlannedChangeText;
    } else if (isHubDowngrade || isDowngrade || tierSlug === 'free' || previewSuggestsDowngrade) {
      title = i18n.confirmDowngrade;
      confirmText = i18n.confirmDowngradeAction;
      message = i18n.downgradeAtPeriodEnd;
      if (tierSlug !== 'free' && estimatedPriceLabel) {
        message += ' ' + t(i18n.newMonthlyPriceNextRenewal, { amount: estimatedPriceLabel });
      }
    }

    const modal = ensureActionConfirmModal();
    if (!modal || !actionConfirmTitleEl || !actionConfirmMessageEl || !actionConfirmAcceptEl) {
      if (requiresWithdrawalWaiver) {
        showError(i18n.checkoutOnlyWithWaiver);
        return Promise.resolve(false);
      }

      return Promise.resolve(window.confirm(message));
    }

    actionConfirmTitleEl.textContent = title;
    actionConfirmMessageEl.textContent = message;
    actionConfirmAcceptEl.textContent = confirmText;
    actionConfirmAcceptEl.dataset.requiresWithdrawalWaiver = requiresWithdrawalWaiver ? '1' : '0';
    toggleWithdrawalWaiverRequirement(requiresWithdrawalWaiver);
    if (withdrawalWaiverCheckEl) {
      withdrawalWaiverCheckEl.checked = false;
    }

    return new Promise((resolve) => {
      actionConfirmResolve = resolve;
      modal.show();
    });
  };

  const updateAgencyCurrentPlanButtonState = (card) => {
    if (!card) return;

    const btn = card.querySelector('.plan-action-btn[data-current-plan="1"][data-slug="agency"]');
    const hubInput = card.querySelector('.agency-hub-count');
    if (!btn || !hubInput) return;
    if ((btn.dataset.action || 'disabled') !== 'change') return;

    const currentHubCount = Number(btn.dataset.currentHubCount || 0);
    const selectedHubCount = Number(hubInput.value || 0);
    const hasExactCurrentSelection = Number.isInteger(currentHubCount)
      && Number.isInteger(selectedHubCount)
      && currentHubCount === selectedHubCount;
    const currentLabel = String(btn.dataset.currentLabel || i18n.currentPlan);
    const updateLabel = String(btn.dataset.updateLabel || i18n.updateAgencyHubs);

    btn.disabled = hasExactCurrentSelection;
    btn.textContent = hasExactCurrentSelection ? currentLabel : updateLabel;
  };

  const refreshAgencyPrice = (card) => {
    if (!card) return;
    const priceEl = card.querySelector('.agency-total-price');
    const hubInput = card.querySelector('.agency-hub-count');
    if (!priceEl || !hubInput) return;

    const basePrice = Number(priceEl.dataset.basePrice || 0);
    const extraPrice = Number(priceEl.dataset.extraPrice || 0);
    const includedHubs = Number(priceEl.dataset.includedHubs || agencyHubMin);
    const hubCount = Number(hubInput.value || includedHubs);
    const extraHubs = Math.max(0, hubCount - includedHubs);

    priceEl.textContent = euro(basePrice + (extraHubs * extraPrice));
    updateAgencyCurrentPlanButtonState(card);
  };

  const setPlanActionLoading = (loading, activeButton = null) => {
    planActionButtons.forEach((btn) => {
      if (!btn.dataset.initialDisabled) {
        btn.dataset.initialDisabled = btn.disabled ? '1' : '0';
      }

      if (loading) {
        if (btn.dataset.initialText === undefined) {
          btn.dataset.initialText = btn.textContent || '';
        }

        btn.disabled = true;
        if (btn === activeButton) {
          btn.textContent = i18n.processingLabel;
        }

        return;
      }

      if (btn.dataset.initialText !== undefined) {
        btn.textContent = btn.dataset.initialText;
      }
      btn.disabled = btn.dataset.initialDisabled === '1';
    });

    if (!loading) {
      document.querySelectorAll('.plan-card').forEach((card) => {
        updateAgencyCurrentPlanButtonState(card);
      });
    }
  };

  if (actionConfirmAcceptEl) {
    actionConfirmAcceptEl.addEventListener('click', () => {
      const requiresWithdrawalWaiver = actionConfirmAcceptEl.dataset.requiresWithdrawalWaiver === '1';
      if (requiresWithdrawalWaiver) {
        if (!withdrawalWaiverCheckEl || !withdrawalWaiverCheckEl.checked) {
          if (withdrawalWaiverCheckEl) {
            withdrawalWaiverCheckEl.reportValidity?.();
          }
          return;
        }
        checkoutWithdrawalWaiverAccepted = true;
      } else {
        checkoutWithdrawalWaiverAccepted = false;
      }

      const modal = ensureActionConfirmModal();
      if (!modal) {
        if (actionConfirmResolve) {
          const resolve = actionConfirmResolve;
          actionConfirmResolve = null;
          resolve(true);
        }
        return;
      }

      const resolve = actionConfirmResolve;
      actionConfirmResolve = null;
      modal.hide();
      if (resolve) {
        resolve(true);
      }
    });
  }

  if (withdrawalWaiverCheckEl) {
    withdrawalWaiverCheckEl.addEventListener('change', () => {
      if (!actionConfirmAcceptEl) {
        return;
      }

      const requiresWithdrawalWaiver = actionConfirmAcceptEl.dataset.requiresWithdrawalWaiver === '1';
      if (!requiresWithdrawalWaiver) {
        actionConfirmAcceptEl.disabled = false;
        return;
      }

      actionConfirmAcceptEl.disabled = !withdrawalWaiverCheckEl.checked;
    });
  }

  const startCheckout = async (tierId, tierSlug, hubCount = null, withdrawalWaiverAcknowledged = false) => {
    clearSuccess();
    clearError();
    hideCheckoutStatus();

    const idempotencyKey = getActionIdempotencyKey({
      action: 'checkout',
      tierId,
      tierSlug,
      hubCount,
    });
    const payload = {
      tier_id: Number(tierId),
      idempotency_key: idempotencyKey,
      terms_acknowledged: true,
      withdrawal_waiver_acknowledged: Boolean(withdrawalWaiverAcknowledged),
    };

    if (tierSlug === 'agency') {
      payload.hub_count = Number(hubCount || 0);
    }

    try {
      const res = await fetch("{{ route('checkout.session') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok || !data.url) {
        throw new Error(data.message || data.error || i18n.unableStartCheckout);
      }
      window.location.href = data.url;
      return true;
    } catch (e) {
      showError(e.message);
      return false;
    }
  };

  const changePlan = async (tierId, tierSlug, hubCount = null) => {
    clearSuccess();
    clearError();
    hideCheckoutStatus();

    const idempotencyKey = getActionIdempotencyKey({
      action: 'change',
      tierId,
      tierSlug,
      hubCount,
    });
    const payload = {
      tier_id: Number(tierId),
      idempotency_key: idempotencyKey,
    };

    if (tierSlug === 'agency') {
      payload.hub_count = Number(hubCount || 0);
    }

    try {
      const res = await fetch("{{ route('billing.change-plan') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (!res.ok) {
        throw new Error(data.message || data.error || i18n.unableChangeSubscription);
      }

      // Successful subscription changes should not pin a stale client idempotency key
      // for later, semantically new user actions in the same tab.
      clearActionIdempotencyKey({
        action: 'change',
        tierId,
        tierSlug,
        hubCount,
      });
      showSuccess(data.message || i18n.subscriptionUpdated);
      window.setTimeout(() => window.location.reload(), 900);
      return true;
    } catch (e) {
      showError(e.message);
      return false;
    }
  };

  const startPortal = async () => {
    clearSuccess();
    clearError();
    hideCheckoutStatus();

    try {
      const res = await fetch("{{ route('billing.portal') }}", {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          return_url: window.location.href,
        })
      });
      const data = await res.json();
      if (!res.ok || !data.url) {
        throw new Error(data.message || data.error || i18n.unableOpenPortal);
      }
      window.location.href = data.url;
    } catch (e) {
      showError(e.message);
    }
  };

  const fetchCheckoutConfirmation = async (sessionId) => {
    const response = await fetch(checkoutConfirmEndpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        session_id: sessionId,
      }),
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(payload.error || payload.message || i18n.unableVerifyCheckout);
    }

    return payload;
  };

  const verifyCheckoutAfterReturn = async (sessionId) => {
    if (checkoutVerificationRunning) {
      return;
    }

    checkoutVerificationRunning = true;
    clearSuccess();
    clearError();

    const supportHint = i18n.supportHint;
    showCheckoutStatus({
      tone: 'info',
      title: i18n.checkoutVerificationInProgress,
      message: i18n.waitingForWebhook,
      progress: 12,
      sessionId,
      help: supportHint,
      animated: true,
    });

    let elapsedMs = 0;
    while (elapsedMs <= checkoutPollTimeoutMs) {
      try {
        const result = await fetchCheckoutConfirmation(sessionId);
        const status = String(result.status || '').toLowerCase();
        const resultMessage = result.message || i18n.checkoutBeingVerified;

        if (status === 'confirmed') {
          showCheckoutStatus({
            tone: 'success',
            title: i18n.paymentConfirmed,
            message: resultMessage,
            progress: 100,
            sessionId,
            help: i18n.pageWillRefresh,
            animated: false,
          });
          showSuccess(resultMessage);
          cleanupCheckoutQuery();
          window.setTimeout(() => {
            window.location.href = checkoutDashboardEndpoint;
          }, 1200);
          checkoutVerificationRunning = false;
          return;
        }

        if (status === 'failed') {
          showCheckoutStatus({
            tone: 'danger',
            title: i18n.paymentVerificationFailed,
            message: resultMessage,
            progress: 100,
            sessionId,
            help: supportHint,
            animated: false,
          });
          showError(resultMessage);
          cleanupCheckoutQuery();
          checkoutVerificationRunning = false;
          return;
        }

        const progress = Math.min(92, 15 + Math.floor((elapsedMs / checkoutPollTimeoutMs) * 70));
        showCheckoutStatus({
          tone: 'info',
          title: i18n.checkoutVerificationInProgress,
          message: resultMessage,
          progress,
          sessionId,
          help: supportHint,
          animated: true,
        });
      } catch (error) {
        const progress = Math.min(90, 15 + Math.floor((elapsedMs / checkoutPollTimeoutMs) * 60));
        showCheckoutStatus({
          tone: 'warning',
          title: i18n.verificationRetry,
          message: error.message || i18n.temporaryIssueCheckingStatus,
          progress,
          sessionId,
          help: supportHint,
          animated: true,
        });
      }

      await new Promise((resolve) => {
        window.setTimeout(resolve, checkoutPollIntervalMs);
      });
      elapsedMs += checkoutPollIntervalMs;
    }

    const timeoutMessage = i18n.paymentConfirmationTimedOut;
    showCheckoutStatus({
      tone: 'danger',
      title: i18n.verificationTimedOut,
      message: timeoutMessage,
      progress: 100,
      sessionId,
      help: supportHint,
      animated: false,
    });
    showError(timeoutMessage);
    cleanupCheckoutQuery();
    checkoutVerificationRunning = false;
  };

  const handleCheckoutReturn = () => {
    const wasCheckoutFlow = urlParams.get('checkout') === '1'
      || urlParams.get('success') === '1'
      || urlParams.get('canceled') === '1'
      || urlParams.has('session_id');
    if (!wasCheckoutFlow) {
      return;
    }

    const canceled = urlParams.get('canceled') === '1';
    const sessionId = String(urlParams.get('session_id') || '').trim();

    if (canceled) {
      const canceledMessage = i18n.checkoutCanceledText;
      showCheckoutStatus({
        tone: 'warning',
        title: i18n.checkoutCanceled,
        message: canceledMessage,
        progress: 100,
        sessionId,
        help: '',
        animated: false,
      });
      showError(canceledMessage);
      cleanupCheckoutQuery();
      return;
    }

    if (!sessionId) {
      const missingSessionMessage = i18n.missingCheckoutSession;
      showCheckoutStatus({
        tone: 'danger',
        title: i18n.verificationFailed,
        message: missingSessionMessage,
        progress: 100,
        sessionId: '',
        help: '',
        animated: false,
      });
      showError(missingSessionMessage);
      cleanupCheckoutQuery();
      return;
    }

    void verifyCheckoutAfterReturn(sessionId);
  };

  document.querySelectorAll('.billing-portal-btn').forEach((btn) => {
    btn.addEventListener('click', startPortal);
  });

  document.querySelectorAll('.agency-hub-count').forEach((input) => {
    const card = input.closest('.plan-card');
    refreshAgencyPrice(card);
    input.addEventListener('input', () => refreshAgencyPrice(card));
    input.addEventListener('change', () => refreshAgencyPrice(card));
  });

  planActionButtons.forEach((btn) => {
    if (!btn.dataset.initialDisabled) {
      btn.dataset.initialDisabled = btn.disabled ? '1' : '0';
    }
    if (btn.dataset.initialText === undefined) {
      btn.dataset.initialText = btn.textContent || '';
    }

    btn.addEventListener('click', async () => {
      clearSuccess();
      clearError();
      hideCheckoutStatus();

      if (planActionInFlight) {
        return;
      }

      const action = btn.dataset.action || 'disabled';
      const tierSlug = btn.dataset.slug || '';
      const tierId = btn.dataset.tier;
      if (!tierId || action === 'disabled') return;

      let hubCount = null;

      if (tierSlug === 'agency') {
        const card = btn.closest('.plan-card');
        const hubInput = card ? card.querySelector('.agency-hub-count') : null;
        hubCount = Number(hubInput?.value || 0);
        if (!Number.isInteger(hubCount) || hubCount < agencyHubMin || hubCount > agencyHubMax) {
          showError(t(i18n.agencySlotsRange, { min: agencyHubMin, max: agencyHubMax }));
          return;
        }
      }

      const confirmed = await confirmPlanAction({
        btn,
        action,
        tierSlug,
        hubCount,
      });
      if (!confirmed) {
        return;
      }

      planActionInFlight = true;
      setPlanActionLoading(true, btn);

      if (action === 'change') {
        const isCurrentPlan = Number(btn.dataset.currentPlan || 0) === 1;
        const hasPendingChange = Number(btn.dataset.hasPendingChange || 0) === 1;
        if (isCurrentPlan && tierSlug === 'agency') {
          const currentHubCount = Number(btn.dataset.currentHubCount || 0);
          if (!hasPendingChange && Number.isInteger(currentHubCount) && currentHubCount === hubCount) {
            showError(i18n.chooseDifferentHubCount);
            planActionInFlight = false;
            setPlanActionLoading(false);
            return;
          }
        }

        const changed = await changePlan(tierId, tierSlug, hubCount);
        if (!changed) {
          planActionInFlight = false;
          setPlanActionLoading(false);
        }
        return;
      }

      if (!checkoutWithdrawalWaiverAccepted) {
        showError(i18n.confirmWithdrawalWaiver);
        planActionInFlight = false;
        setPlanActionLoading(false);
        return;
      }

      const checkoutStarted = await startCheckout(
        tierId,
        tierSlug,
        hubCount,
        checkoutWithdrawalWaiverAccepted
      );
      checkoutWithdrawalWaiverAccepted = false;
      if (!checkoutStarted) {
        planActionInFlight = false;
        setPlanActionLoading(false);
      }
    });
  });

  handleCheckoutReturn();
})();
</script>
@endsection
