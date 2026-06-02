<?php use App\Models\UserData; ?>
@extends('layouts.sidebar')

@section('content')
@php
    $headerEditLocked = !($canEditHeader ?? false);
    $headerRequiredTierLabel = (string) ($requiredTierLabel ?? 'Basic');
    $subscriptionUpsellUrl = url('/dashboard/subscription');
    $headerUploadLimit = config('media.upload_limits.header_hero', ['max_kb' => 4096, 'max_width' => 3000, 'max_height' => 1200]);
    $headerLimitMb = (int) ceil(max(1, (int) ($headerUploadLimit['max_kb'] ?? 4096)) / 1024);
    $headerLimitWidth = max(1, (int) ($headerUploadLimit['max_width'] ?? 3000));
    $headerLimitHeight = max(1, (int) ($headerUploadLimit['max_height'] ?? 1200));

    $avatarUploadLimit = config('media.upload_limits.avatar', ['max_kb' => 2048, 'max_width' => 1200, 'max_height' => 1200]);
    $avatarLimitMb = (int) ceil(max(1, (int) ($avatarUploadLimit['max_kb'] ?? 2048)) / 1024);
    $avatarLimitWidth = max(1, (int) ($avatarUploadLimit['max_width'] ?? 1200));
    $avatarLimitHeight = max(1, (int) ($avatarUploadLimit['max_height'] ?? 1200));

    $settingsUserId = app(\App\Services\Agency\AgencyHubContext::class)->editingUserId(auth()->user(), request());
    $rawShowProfileImage = UserData::getData($settingsUserId, 'show_profile_image');
    $showProfileImage = !in_array($rawShowProfileImage, [false, 'false', 0, '0'], true);
    $rawProfileHeaderLayout = UserData::getData($settingsUserId, 'profile_header_layout');
    $profileHeaderLayout = is_string($rawProfileHeaderLayout)
        ? strtolower(trim($rawProfileHeaderLayout))
        : 'standard';
    if (!in_array($profileHeaderLayout, ['standard', 'business', 'business_header_focus_description'], true)) {
        $profileHeaderLayout = 'standard';
    }
    $heroOnlyLayoutActive = in_array($profileHeaderLayout, ['business', 'business_header_focus_description'], true);
    $checkmarkEnabled = UserData::getData($settingsUserId, 'checkmark') == true;
    $shareButtonEnabled = UserData::getData($settingsUserId, 'disable-sharebtn') != 'true';
    $openInNewTabEnabled = UserData::getData($settingsUserId, 'links-new-tab') != false;
    $rawHideBranding = UserData::getData($settingsUserId, 'hide_credit');
    $hideBrandingEnabled = in_array($rawHideBranding, [true, 1, '1', 'true', 'on', 'yes'], true);
@endphp
@foreach($pages as $page)
@php
    $domainUrlResolver = app(\App\Services\Domains\DomainUrlResolver::class);
    $profileOwner = $domainUrlResolver->ownerForPageUser($page);
    $previewUrl = $domainUrlResolver->profileUrlForEditor($profileOwner, $page);

    $headerEnabled = $headerSettings['enabled'] ?? false;
    $headerGradientEnabled = $headerSettings['gradient_enabled'] ?? false;
    $headerHeroEnabled = $headerSettings['hero_enabled'] ?? false;
    $showHeroToggle = $headerHeroEnabled;
    $showHeaderToggle = !$heroOnlyLayoutActive && $headerEnabled && !$headerHeroEnabled;
    $headerPath = $headerSettings['path'] ?? null;
    $hasHeaderImage = is_string($headerPath) && mediaPathExists($headerPath);
    $headerUrl = $hasHeaderImage ? mediaPathUrl($headerPath) : null;
    $currentTheme = is_string($page->theme ?? null) && trim((string) $page->theme) !== ''
        ? trim((string) $page->theme)
        : 'default';
    $profileLayoutSwitcherEnabled = templateCapability($currentTheme, 'profile_layout_switcher', true);
@endphp

<style>
    .ls-header-preview {
        width: 100%;
        height: 200px;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
    }

    .ls-logo-preview {
        width: 100px;
        height: 100px;
        object-fit: cover;
        border-radius: 50%;
        border: 1px solid #e5e7eb;
        background: #fff;
    }

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

    .ls-layout-options {
        display: grid;
        gap: 0;
        border-top: 1px solid #eef1f4;
        border-bottom: 1px solid #eef1f4;
    }

    .ls-layout-option {
        display: flex;
        align-items: center;
        gap: 12px;
        min-height: 48px;
        padding: 13px 0 13px 12px;
        border-left: 3px solid transparent;
        cursor: pointer;
        transition: border-color 0.16s ease, color 0.16s ease;
    }

    .ls-layout-option + .ls-layout-option {
        border-top: 1px solid #eef1f4;
    }

    .ls-layout-option.form-check {
        padding-left: 12px;
    }

    .ls-layout-option .form-check-input {
        margin-left: 0;
        margin-top: 0;
        flex: 0 0 auto;
    }

    .ls-layout-option:has(.form-check-input:checked) {
        border-left-color: rgba(var(--bs-primary-rgb), 0.75);
    }

    .ls-layout-option .form-check-label {
        font-weight: 600;
        margin-bottom: 0;
        cursor: pointer;
    }

    .ls-layout-option:has(.form-check-input:checked) .form-check-label {
        color: rgba(var(--bs-primary-rgb), 1);
    }

    .ls-hero-settings {
        width: 100%;
    }

    .ls-hero-settings .ls-setting-row {
        padding: 16px 0;
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
                    <div class="d-flex align-items-center justify-content-between flex-wrap mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-image"></i>
                            <h4 class="mb-0">Kopfbereich</h4>
                        </div>
                        <a class="btn btn-outline-primary" target="_blank" rel="noreferrer" href="{{ $previewUrl }}">{{ __('messages.View Page') }}</a>
                    </div>
                    <p class="text-muted mb-0">Logo, Name und Beschreibung sowie optionale Header-Grafik.</p>
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

    <form action="{{ route('editPage') }}" method="post" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="return_to" value="/studio/header">
        <input type="hidden" name="littlelink_name" value="{{ $page->littlelink_name ?? '' }}">
        @if($checkmarkEnabled)
            <input type="hidden" name="checkmark" value="on">
        @endif
        @if($shareButtonEnabled)
            <input type="hidden" name="sharebtn" value="on">
        @endif
        @if($openInNewTabEnabled)
            <input type="hidden" name="tablinks" value="on">
        @endif
        @if($hideBrandingEnabled)
            <input type="hidden" name="hide_credit" value="on">
        @endif

        <div class="row">
            <div class="col-12 mb-4">
                <div class="card rounded border">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Profilinhalt</h5>

                        <div class="d-flex align-items-center gap-3 mb-3">
                            @if(userAvatarExists($settingsUserId))
                                <img src="{{ userAvatarUrl($settingsUserId) }}" alt="Logo" class="ls-logo-preview">
                            @elseif(file_exists(base_path('assets/wayvio/images/').findFile('avatar')))
                                <img src="{{ url('assets/wayvio/images/').'/'.findFile('avatar') }}" alt="Logo" class="ls-logo-preview">
                            @else
                                <img src="{{ asset('assets/wayvio/images/logo.svg') }}" alt="Logo" class="ls-logo-preview">
                            @endif

                            @if(userAvatarExists($settingsUserId))
                                <button
                                    type="submit"
                                    form="delete-profile-picture-form"
                                    class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('{{ __('messages.confirm.delete.user') }}')"
                                >
                                    <i class="bi bi-trash-fill"></i> {{ __('Remove') }}
                                </button>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="profile_logo">{{ __('messages.Profile Picture') }}</label>
                            <input type="file" accept="image/jpeg,image/jpg,image/png,image/webp" name="image" class="form-control" id="profile_logo">
                            <div class="form-text">{{ __('messages.upload.limit.notice', ['size' => $avatarLimitMb, 'width' => $avatarLimitWidth, 'height' => $avatarLimitHeight]) }}</div>
                        </div>

                        <div class="ls-settings-list mb-3">
                            <div class="ls-setting-row">
                                <div class="ls-setting-copy">
                                    <h6>{{ __('messages.page.show_profile_picture_title') }}</h6>
                                    <p class="text-muted">{{ __('messages.page.show_profile_picture_description') }}</p>
                                </div>
                                <div class="form-check form-switch ls-setting-control mb-0">
                                    <input type="hidden" name="show_profile_image" value="0">
                                    <input name="show_profile_image" class="form-check-input" type="checkbox" id="show_profile_image" value="1" @if($showProfileImage) checked @endif />
                                    <label class="form-check-label fw-semibold" for="show_profile_image">{{ __('messages.Enable') }}</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <h6 class="mb-1">{{ __('messages.page.profile_layout_title') }}</h6>
                            <p class="text-muted mb-2">{{ __('messages.page.profile_layout_description') }}</p>
                            @if($profileLayoutSwitcherEnabled)
                                <div class="ls-layout-options">
                                    <label class="form-check ls-layout-option" for="profile_header_layout_standard">
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            name="profile_header_layout"
                                            id="profile_header_layout_standard"
                                            value="standard"
                                            {{ $profileHeaderLayout === 'standard' ? 'checked' : '' }}
                                        >
                                        <span class="form-check-label">
                                            {{ __('messages.page.profile_layout_standard') }}
                                        </span>
                                    </label>
                                    <label class="form-check ls-layout-option" for="profile_header_layout_business">
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            name="profile_header_layout"
                                            id="profile_header_layout_business"
                                            value="business"
                                            {{ $profileHeaderLayout === 'business' ? 'checked' : '' }}
                                        >
                                        <span class="form-check-label">
                                            {{ __('messages.page.profile_layout_business') }}
                                        </span>
                                    </label>
                                    <label class="form-check ls-layout-option" for="profile_header_layout_business_header_focus_description">
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            name="profile_header_layout"
                                            id="profile_header_layout_business_header_focus_description"
                                            value="business_header_focus_description"
                                            {{ $profileHeaderLayout === 'business_header_focus_description' ? 'checked' : '' }}
                                        >
                                        <span class="form-check-label">
                                            {{ __('messages.page.profile_layout_business_header_focus_description') }}
                                        </span>
                                    </label>
                                </div>
                            @else
                                <input type="hidden" name="profile_header_layout" value="{{ $profileHeaderLayout }}">
                                <p class="small text-muted mb-0">{{ __('messages.page.profile_layout_locked') }}</p>
                            @endif
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                <label for="name" class="form-label mb-0">{{ __('messages.Display name') }}</label>
                                <span id="nameLimitHelp" class="small text-muted">max. 30</span>
                            </div>
                            <input type="text" id="name" class="form-control" name="name" value="{{ $page->name }}" maxlength="30" aria-describedby="nameLimitHelp" required>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                                <label for="pageDescription" class="form-label mb-0">{{ __('messages.Page Description') }}</label>
                                <span id="pageDescriptionLimitHelp" class="small text-muted">max. 75</span>
                            </div>
                            <textarea id="pageDescription" class="form-control" name="pageDescription" rows="3" maxlength="75" aria-describedby="pageDescriptionLimitHelp">{{ $page->littlelink_description ?? '' }}</textarea>
                        </div>

                        <div class="d-flex justify-content-end align-items-center">
                            <button type="submit" class="btn btn-primary">{{ __('messages.Save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    @if(userAvatarExists($settingsUserId))
        <form id="delete-profile-picture-form" method="POST" action="{{ route('delProfilePicture') }}" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    @endif

    <form action="{{ route('editHeader') }}" method="post" enctype="multipart/form-data" @if($headerEditLocked) data-header-upsell-form @endif>
        @csrf
        <div class="row mt-0">
            <div class="col-12 mb-4">
                <div class="card rounded border">
                    <div class="card-body">
                        <div class="mb-3">
                            <h5 class="card-title mb-1">Hero/Header</h5>
                            <small class="text-muted d-block">{{ __('messages.Header helper') }}</small>
                            <small class="text-muted d-block">{{ __('messages.Hero helper') }}</small>
                        </div>
                        <div class="mb-3">
                            <div class="ls-settings-list ls-hero-settings">
                                <div class="ls-setting-row">
                                    <div class="ls-setting-copy">
                                        <h6>{{ __('Show hero') }}</h6>
                                    </div>
                                    <div class="form-check form-switch ls-setting-control mb-0">
                                        <input type="hidden" name="enable_header_hero" value="0">
                                        <input class="form-check-input" type="checkbox" id="enable-header-hero" name="enable_header_hero" value="1" {{ $showHeroToggle ? 'checked' : '' }} {{ $headerEditLocked ? 'disabled' : '' }}>
                                        <label class="form-check-label fw-semibold" for="enable-header-hero">{{ __('messages.Enable') }}</label>
                                    </div>
                                </div>
                                <div class="ls-setting-row">
                                    <div class="ls-setting-copy">
                                        <h6>{{ __('Show header') }}</h6>
                                    </div>
                                    <div class="form-check form-switch ls-setting-control mb-0">
                                        <input type="hidden" name="enable_header" value="0">
                                        <input class="form-check-input" type="checkbox" id="enable-header" name="enable_header" value="1" {{ $showHeaderToggle ? 'checked' : '' }} {{ $headerEditLocked || $heroOnlyLayoutActive ? 'disabled' : '' }}>
                                        <label class="form-check-label fw-semibold" for="enable-header">{{ __('messages.Enable') }}</label>
                                    </div>
                                </div>
                                <div class="ls-setting-row">
                                    <div class="ls-setting-copy">
                                        <h6>{{ __('Fade into background') }}</h6>
                                        <p class="text-muted">{{ __('Softly fades the bottom of the header image into the page background.') }}</p>
                                    </div>
                                    <div class="form-check form-switch ls-setting-control mb-0">
                                        <input type="hidden" name="enable_header_gradient" value="0">
                                        <input class="form-check-input" type="checkbox" id="enable-header-gradient" name="enable_header_gradient" value="1" {{ $headerGradientEnabled ? 'checked' : '' }} {{ $headerEditLocked ? 'disabled' : '' }}>
                                        <label class="form-check-label fw-semibold" for="enable-header-gradient">{{ __('messages.Enable') }}</label>
                                    </div>
                                </div>
                                <small id="business-hero-only-hint" class="text-muted d-block mt-2 {{ $heroOnlyLayoutActive ? '' : 'd-none' }}">
                                    {{ __('messages.page.business_hero_only_notice') }}
                                </small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="header-image" class="form-label">{{ __('messages.Upload image') }}</label>
                            <input class="form-control" type="file" id="header-image" name="header_image" accept="image/jpeg,image/jpg,image/png,image/webp" {{ $headerEditLocked ? 'disabled' : '' }}>
                            <div class="form-text">{{ __('messages.upload.limit.notice', ['size' => $headerLimitMb, 'width' => $headerLimitWidth, 'height' => $headerLimitHeight]) }}</div>
                        </div>

                        <p class="text-uppercase text-muted small mb-2">{{ __('messages.Preview') }}</p>
                        @if($hasHeaderImage)
                            <div class="mb-3">
                                <div class="ls-header-preview shadow-sm" style="background-image: url('{{ $headerUrl }}');"></div>
                                @if($headerEditLocked)
                                    <button type="button" class="btn btn-sm btn-outline-danger mt-3" data-upsell-redirect>
                                        <i class="bi bi-trash-fill"></i> {{ __('messages.Remove header image') }}
                                    </button>
                                @else
                                    <button type="submit" name="remove_header_image" value="1" class="btn btn-sm btn-outline-danger mt-3">
                                        <i class="bi bi-trash-fill"></i> {{ __('messages.Remove header image') }}
                                    </button>
                                @endif
                            </div>
                        @else
                            <div class="ls-header-preview d-flex align-items-center justify-content-center text-muted bg-light mb-3">
                                {{ __('messages.No image selected') }}
                            </div>
                        @endif

                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                @if($headerEditLocked)
                                    {{ __('Available from :tier.', ['tier' => $headerRequiredTierLabel]) }}
                                @endif
                            </small>
                            @if($headerEditLocked)
                                <button type="button" class="btn btn-primary" data-upsell-redirect>{{ __('Upgrade in subscription') }}</button>
                            @else
                                <button type="submit" class="btn btn-primary">{{ __('messages.Save') }}</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endforeach
@endsection

@push('sidebar-scripts')
<script>
(() => {
    const heroToggle = document.getElementById('enable-header-hero');
    const headerToggle = document.getElementById('enable-header');
    const heroFadeToggle = document.getElementById('enable-header-gradient');
    const profileLayoutStandard = document.getElementById('profile_header_layout_standard');
    const profileLayoutBusiness = document.getElementById('profile_header_layout_business');
    const profileLayoutBusinessHeaderFocusDescription = document.getElementById('profile_header_layout_business_header_focus_description');
    const businessHeroOnlyHint = document.getElementById('business-hero-only-hint');
    const headerToggleLockedByTier = @json($headerEditLocked);
    const heroOnlyLayoutActiveFromServer = @json($heroOnlyLayoutActive);
    const hasLayoutSelectionControls = Boolean(
        profileLayoutStandard || profileLayoutBusiness || profileLayoutBusinessHeaderFocusDescription
    );

    const syncHeaderModeAvailability = () => {
        if (!headerToggle) {
            return;
        }

        const businessLayoutSelected = profileLayoutBusiness ? profileLayoutBusiness.checked : false;
        const businessHeaderFocusDescriptionLayoutSelected = profileLayoutBusinessHeaderFocusDescription
            ? profileLayoutBusinessHeaderFocusDescription.checked
            : false;
        const heroOnlyLayoutSelected = hasLayoutSelectionControls
            ? (businessLayoutSelected || businessHeaderFocusDescriptionLayoutSelected)
            : heroOnlyLayoutActiveFromServer;
        const disableHeaderToggle = headerToggleLockedByTier || heroOnlyLayoutSelected;

        if (disableHeaderToggle) {
            headerToggle.checked = false;
        }

        headerToggle.disabled = disableHeaderToggle;

        if (businessHeroOnlyHint) {
            businessHeroOnlyHint.classList.toggle('d-none', !heroOnlyLayoutSelected);
        }
    };

    const syncHeroFadeAvailability = () => {
        if (!heroFadeToggle || headerToggleLockedByTier) {
            return;
        }

        const canUseFade = Boolean(
            (heroToggle && heroToggle.checked) ||
            (headerToggle && headerToggle.checked)
        );
        heroFadeToggle.disabled = !canUseFade;

        if (!canUseFade) {
            heroFadeToggle.checked = false;
        }
    };

    if (heroToggle && headerToggle) {
        heroToggle.addEventListener('change', () => {
            if (heroToggle.checked) {
                headerToggle.checked = false;
            }
            syncHeroFadeAvailability();
        });

        headerToggle.addEventListener('change', () => {
            if (headerToggle.checked) {
                heroToggle.checked = false;
            }
            syncHeroFadeAvailability();
        });
    }

    if (profileLayoutStandard || profileLayoutBusiness || profileLayoutBusinessHeaderFocusDescription) {
        if (profileLayoutStandard) {
            profileLayoutStandard.addEventListener('change', syncHeaderModeAvailability);
        }
        if (profileLayoutBusiness) {
            profileLayoutBusiness.addEventListener('change', syncHeaderModeAvailability);
        }
        if (profileLayoutBusinessHeaderFocusDescription) {
            profileLayoutBusinessHeaderFocusDescription.addEventListener('change', syncHeaderModeAvailability);
        }
    }
    syncHeaderModeAvailability();
    syncHeroFadeAvailability();

    @if($headerEditLocked)
    const subscriptionUrl = @json($subscriptionUpsellUrl);
    const handleRedirect = (event) => {
        event.preventDefault();
        window.location.assign(subscriptionUrl);
    };

    const upsellForm = document.querySelector('[data-header-upsell-form]');
    if (upsellForm) {
        upsellForm.addEventListener('submit', handleRedirect);
    }

    document.querySelectorAll('[data-upsell-redirect]').forEach((button) => {
        button.addEventListener('click', handleRedirect);
    });
    @endif
})();
</script>
@endpush
