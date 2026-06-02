<?php use App\Models\UserData; ?>
@extends('layouts.sidebar')

@section('content')
@php
    $settingsUserId = app(\App\Services\Agency\AgencyHubContext::class)->editingUserId(auth()->user(), request());
    $subscriptionManager = class_exists(\Modules\Tiers\Services\SubscriptionManager::class) ? app(\Modules\Tiers\Services\SubscriptionManager::class) : null;
    $tierResolver = class_exists(\Modules\Tiers\Services\TierResolver::class) ? app(\Modules\Tiers\Services\TierResolver::class) : null;
    $userTier = $subscriptionManager && auth()->check() ? $subscriptionManager->getUserTier(auth()->user()) : null;
    $roleBypass = auth()->check() && in_array(auth()->user()->role, ['vip', 'admin'], true);
    $canUseCheckmark = ($tierResolver && $userTier && $tierResolver->featureEnabled($userTier, 'profile.checkmark')) || $roleBypass;
    $canRemoveBranding = ($tierResolver && $userTier && $tierResolver->featureEnabled($userTier, 'branding.remove_branding')) || $roleBypass;
    $subscriptionDashboardUrl = \Illuminate\Support\Facades\Route::has('subscription.dashboard')
        ? route('subscription.dashboard')
        : url('/dashboard/subscription');

    $rawShowProfileImage = UserData::getData($settingsUserId, 'show_profile_image');
    $showProfileImage = !in_array($rawShowProfileImage, [false, 'false', 0, '0'], true);
    $rawProfileHeaderLayout = UserData::getData($settingsUserId, 'profile_header_layout');
    $profileHeaderLayout = is_string($rawProfileHeaderLayout)
        ? strtolower(trim($rawProfileHeaderLayout))
        : 'standard';
    if (!in_array($profileHeaderLayout, ['standard', 'business', 'business_header_focus_description'], true)) {
        $profileHeaderLayout = 'standard';
    }
    $checkmarkEnabled = UserData::getData($settingsUserId, 'checkmark') == true;
    $shareButtonEnabled = UserData::getData($settingsUserId, 'disable-sharebtn') != 'true';
    $openInNewTabEnabled = UserData::getData($settingsUserId, 'links-new-tab') != false;
    $rawHideBranding = UserData::getData($settingsUserId, 'hide_credit');
    $hideBrandingEnabled = in_array($rawHideBranding, [true, 1, '1', 'true', 'on', 'yes'], true);
@endphp

<style>
    .ls-settings-list {
        display: grid;
        gap: 0;
        border-top: 1px solid #eef1f4;
        border-bottom: 1px solid #eef1f4;
    }

    .ls-setting-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 28px;
        padding: 18px 0;
    }

    .ls-setting-row + .ls-setting-row {
        border-top: 1px solid #eef1f4;
    }

    .ls-setting-copy {
        min-width: 0;
    }

    .ls-setting-copy h6,
    .ls-setting-copy p {
        margin-bottom: 0;
    }

    .ls-setting-copy p {
        margin-top: 3px;
        line-height: 1.45;
    }

    .ls-setting-control {
        flex: 0 0 auto;
        min-width: 108px;
        justify-content: flex-end;
    }

    .ls-setting-control.form-check {
        display: inline-flex;
        align-items: center;
        gap: 12px;
        padding-left: 0;
    }

    .ls-setting-control .form-check-input {
        width: 2.6rem;
        height: 1.35rem;
        margin-left: 0;
        margin-top: 0;
        flex: 0 0 auto;
    }

    .ls-setting-control .form-check-label {
        margin-bottom: 0;
        white-space: nowrap;
    }

    @media (max-width: 575.98px) {
        .ls-setting-row {
            align-items: flex-start;
            flex-direction: column;
            gap: 12px;
        }

        .ls-setting-control {
            justify-content: flex-start;
            min-width: 0;
        }
    }
</style>

<div class="conatiner-fluid content-inner mt-n5 py-0 ls-consistent-spacing">
    <div class="row">
        <div class="col-lg-12">
            <div class="card rounded mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <i class="bi bi-sliders"></i>
                        <h4 class="mb-0">Seiteneinstellungen</h4>
                    </div>
                    <p class="text-muted mb-0">Seitensichtbarkeit, Seiten-URL und Spracheinstellungen.</p>
                </div>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="row">
            <div class="col-12">
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <svg class="bi flex-shrink-0 me-2" width="24" height="24">
                        <use xlink:href="#exclamation-triangle-fill"></use>
                    </svg>
                    <div>
                        @foreach ($errors->all() as $error)
                            {{ $error }}
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    @foreach($pages as $page)
        @php
            $publicationColumnsReady = \Illuminate\Support\Facades\Schema::hasColumn('users', 'is_published');
            $isPublished = (bool) ($page->is_published ?? false);
            $publicationConfirmTitle = $isPublished
                ? __('messages.hub.publish.confirm_unpublish_title')
                : __('messages.hub.publish.confirm_publish_title');
            $publicationConfirmBody = $isPublished
                ? __('messages.hub.publish.confirm_unpublish_body')
                : __('messages.hub.publish.confirm_publish_body');

            $selectedPageLocale = old('locale', $pageLocale ?? ($page->locale ?? ''));
            $localeLabels = [
                'en' => __('messages.English'),
                'de' => __('messages.German'),
            ];
            $isPageHubContext = (bool) ($isPageHubContext ?? false);
            $pageContextName = trim((string) ($pageContextName ?? ''));
            $agencyLocaleRaw = is_string($agencyLocale ?? null) ? (string) $agencyLocale : '';
            $agencyLocaleLabel = $agencyLocaleRaw !== ''
                ? ($localeLabels[$agencyLocaleRaw] ?? strtoupper($agencyLocaleRaw))
                : null;
            $inheritOrDefaultLabel = $isPageHubContext
                ? __('Inherit from agency')
                : __('Platform default');
            $pageLanguageDescription = $isPageHubContext
                ? __('This language applies to the currently selected hub.')
                : __('This language applies to your public page including legal notice and footer.');
        @endphp

        @if($publicationColumnsReady)
            <div class="row">
                <div class="col-12 mb-4">
                    <div class="card border {{ $isPublished ? 'border-success-subtle' : 'border-secondary-subtle' }}">
                        <div class="card-body">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                                <div>
                                    <h5 class="mb-1">{{ __('messages.hub.publish.panel_title') }}</h5>
                                    <p class="text-muted mb-0">{{ __('messages.hub.publish.panel_description') }}</p>
                                </div>
                                <span class="badge {{ $isPublished ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $isPublished ? __('messages.hub.publish.status_published') : __('messages.hub.publish.status_unpublished') }}
                                </span>
                            </div>
                            <form method="POST" action="{{ route('page.publication') }}" class="mt-3" onsubmit="return confirm('{{ $publicationConfirmTitle }}\n{{ $publicationConfirmBody }}');">
                                @csrf
                                <input type="hidden" name="publish" value="{{ $isPublished ? 0 : 1 }}">
                                <button type="submit" class="btn btn-sm {{ $isPublished ? 'btn-outline-secondary' : 'btn-success' }}">
                                    {{ $isPublished ? __('messages.hub.publish.action_unpublish') : __('messages.hub.publish.action_publish') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="row">
            <div class="col-12 mb-4">
                <div class="card border">
                    <div class="card-body">
                        <h5 class="mb-2">{{ __('messages.Language preference') }}</h5>
                        <p class="text-muted mb-3">{{ $pageLanguageDescription }}</p>
                        @if($isPageHubContext)
                            <p class="text-muted mb-3">
                                {{ __('Current hub:') }} <strong>{{ $pageContextName !== '' ? $pageContextName : '-' }}</strong>
                                @if($agencyLocaleLabel)
                                    <br>{{ __('Agency default language:') }} <strong>{{ $agencyLocaleLabel }}</strong>
                                @endif
                            </p>
                        @endif
                        <form action="{{ route('page.locale') }}" method="post">
                            @csrf
                            <div class="form-group mb-3">
                                <label class="form-label" for="page_locale">{{ __('messages.Language') }}</label>
                                <select class="form-control" name="locale" id="page_locale">
                                    <option value="">{{ $inheritOrDefaultLabel }}</option>
                                    @foreach($supportedLocales as $localeOption)
                                        @php $localeLabel = $localeLabels[$localeOption] ?? strtoupper($localeOption); @endphp
                                        <option value="{{ $localeOption }}" @if($selectedPageLocale === $localeOption) selected @endif>{{ $localeLabel }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">{{ __('messages.Save') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mb-4">
                <form action="{{ route('editPage') }}" method="post">
                    @csrf
                    <input type="hidden" name="return_to" value="/studio/page-settings">
                    <input type="hidden" name="name" value="{{ $page->name ?? '' }}">
                    <input type="hidden" name="pageDescription" value="{{ $page->littlelink_description ?? '' }}">
                    <input type="hidden" name="show_profile_image" value="{{ $showProfileImage ? 1 : 0 }}">
                    <input type="hidden" name="profile_header_layout" value="{{ $profileHeaderLayout }}">
                    @if(!$canUseCheckmark && $checkmarkEnabled)
                        <input type="hidden" name="checkmark" value="on">
                    @endif

                    <div class="card border">
                        <div class="card-body">
                            <h5 class="mb-3">{{ __('Page & URL') }}</h5>
                            <?php
                                $url = $_SERVER['REQUEST_URI'];
                                if (strpos($url, 'no_page_name') == true) {
                                    echo '<span style="color:#FF0000; font-size:120%;">' . e(__('messages.No page url notice')) . '</span>';
                                }
                            ?>
                            <div class="d-flex align-items-center justify-content-between gap-2 mt-2 mb-1">
                                <label for="littlelink_name" class="form-label mb-0">{{ __('messages.Page URL') }}</label>
                                <span id="littlelinkNameLimitHelp" class="small text-muted">max. 25</span>
                            </div>
                            <div class="input-group mb-0 has-validation">
                                <span class="input-group-text" id="basic-addon3">{{ str_replace(['http://', 'https://'], '', url('')) }}/</span>
                                <input type="littlelink_name" class="form-control" id="littlelink_name" name="littlelink_name" aria-describedby="littlelinkNameLimitHelp" value="{{ $page->littlelink_name ?? '' }}" :value="old('littlelink_name')" maxlength="25" required autofocus>
                            </div>
                            <script>var exceptionvar = "{{ $page->littlelink_name }}";</script>
                            @include('auth.url-validation', ['handleValidationUrl' => route('studio.validate-handle')])
                        </div>
                    </div>

                    <div class="card border mt-4">
                        <div class="card-body">
                            <h5 class="mb-3">{{ __('Interaction & behavior') }}</h5>
                            <div class="ls-settings-list">
                                @if($canUseCheckmark)
                                    <div class="ls-setting-row">
                                        <div class="ls-setting-copy">
                                            <h6>{{ __('messages.Show checkmark') }}</h6>
                                            <p class="text-muted">{{ __('messages.disableverified') }}</p>
                                        </div>
                                        <div class="form-check form-switch ls-setting-control mb-0">
                                            <input name="checkmark" class="form-check-input" type="checkbox" id="checkmark" @if($checkmarkEnabled) checked @endif />
                                            <label class="form-check-label fw-semibold" for="checkmark">{{ __('messages.Enable') }}</label>
                                        </div>
                                    </div>
                                @endif
                                <div class="ls-setting-row">
                                    <div class="ls-setting-copy">
                                        <h6>{{ __('messages.Show share button') }}</h6>
                                        <p class="text-muted">{{ __('messages.disablesharebutton') }}</p>
                                    </div>
                                    <div class="form-check form-switch ls-setting-control mb-0">
                                        <input name="sharebtn" class="form-check-input" type="checkbox" id="sharebtn" @if($shareButtonEnabled) checked @endif />
                                        <label class="form-check-label fw-semibold" for="sharebtn">{{ __('messages.Enable') }}</label>
                                    </div>
                                </div>
                                <div class="ls-setting-row">
                                    <div class="ls-setting-copy">
                                        <h6>{{ __('messages.Open links in new tab') }}</h6>
                                        <p class="text-muted">{{ __('messages.openlinksnewtab') }}</p>
                                    </div>
                                    <div class="form-check form-switch ls-setting-control mb-0">
                                        <input name="tablinks" class="form-check-input" type="checkbox" id="tablinks" @if($openInNewTabEnabled) checked @endif />
                                        <label class="form-check-label fw-semibold" for="tablinks">{{ __('messages.Enable') }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border mt-4">
                        <div class="card-body">
                            <h5 class="mb-3">
                                Branding
                                @if(!$canRemoveBranding)
                                    <span class="badge bg-secondary ms-2">{{ __('messages.page.hide_branding_available_from_basic') }}</span>
                                @endif
                            </h5>
                            <div class="ls-settings-list">
                                <div class="ls-setting-row">
                                    <div class="ls-setting-copy">
                                        <h6>{{ __('messages.page.hide_branding_title') }}</h6>
                                        <p class="text-muted">{{ __('messages.page.hide_branding_description') }}</p>
                                    </div>
                                    <div class="form-check form-switch ls-setting-control mb-0">
                                        <input
                                            name="hide_credit"
                                            class="form-check-input"
                                            type="checkbox"
                                            id="hide_credit"
                                            @if($canRemoveBranding && $hideBrandingEnabled) checked @endif
                                            @if(!$canRemoveBranding) data-branding-upsell="true" data-branding-upsell-target="hide-credit-upsell" @endif
                                        />
                                        <label class="form-check-label fw-semibold" for="hide_credit">{{ __('messages.Enable') }}</label>
                                    </div>
                                </div>
                            </div>
                            @if(!$canRemoveBranding)
                                <div id="hide-credit-upsell" class="alert alert-warning mt-2 mb-0 d-none" role="alert">
                                    {{ __('messages.page.hide_branding_upsell_notice') }}
                                    <a href="{{ $subscriptionDashboardUrl }}" class="alert-link">{{ __('messages.page.hide_branding_upsell_link') }}</a>
                                </div>
                            @endif
                        </div>
                    </div>

                    <button id="submit-btn" type="submit" class="mt-4 btn btn-primary">{{ __('messages.Save') }}</button>
                </form>
            </div>
        </div>
    @endforeach
</div>

<script>
  (function() {
    const brandingToggle = document.querySelector('[data-branding-upsell="true"]');
    if (!brandingToggle) {
      return;
    }

    const upsellTargetId = brandingToggle.dataset.brandingUpsellTarget || '';
    const upsellNotice = upsellTargetId ? document.getElementById(upsellTargetId) : null;

    const showBrandingUpsell = () => {
      brandingToggle.checked = false;
      if (upsellNotice) {
        upsellNotice.classList.remove('d-none');
      }
    };

    brandingToggle.addEventListener('change', showBrandingUpsell);
  })();
</script>
@endsection
