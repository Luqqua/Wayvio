<?php use App\Models\UserData; ?>
@php
    $rawHeaderEnabled = UserData::getData($userinfo->id, 'header_enabled');
    $headerEnabled = !in_array($rawHeaderEnabled, [null, 'null', false, 'false', 0, '0'], true);
    $rawHeaderGradientEnabled = UserData::getData($userinfo->id, 'header_gradient_enabled');
    $headerGradientEnabled = !in_array($rawHeaderGradientEnabled, [null, 'null', false, 'false', 0, '0'], true);
    $rawHeaderHeroEnabled = UserData::getData($userinfo->id, 'header_hero_enabled');
    $headerHeroEnabled = !in_array($rawHeaderHeroEnabled, [null, 'null', false, 'false', 0, '0'], true);
    $rawShowProfileImage = UserData::getData($userinfo->id, 'show_profile_image');
    $showAvatar = !in_array($rawShowProfileImage, [false, 'false', 0, '0'], true);
    $suppressStandardAvatar = !empty($suppressStandardAvatar);
    $useBusinessProfileLayout = !empty($useBusinessProfileLayout);
    $useBusinessHeaderFocusDescriptionLayout = !empty($useBusinessHeaderFocusDescriptionLayout);
    $renderStandardAvatar = $showAvatar && !$suppressStandardAvatar;
    $headerImagePath = UserData::getData($userinfo->id, 'header_image');
    $mediaStorage = app(\App\Services\Uploads\MediaStorageService::class);

    $hasHeaderImage = false;
    $headerUrl = null;
    if (is_string($headerImagePath) && !empty($headerImagePath) && $mediaStorage->exists($headerImagePath)) {
        $hasHeaderImage = true;
        $headerUrl = $mediaStorage->url($headerImagePath);
    }

    $currentTheme = $info->theme ?? 'default';
    $supportsHeader = templateCapability($currentTheme, 'header', $currentTheme === 'default');
    $canUseHeaderByTier = pageOwnerHasTierFeature($userinfo->id, 'design.header_image');
    $showHeader = $canUseHeaderByTier && $supportsHeader && $headerEnabled && $hasHeaderImage;
    $showHeaderHero = $showHeader && $headerHeroEnabled;
    // Business layouts only support the header in hero mode — suppress compact header if active
    if (!empty($useBusinessProfileLayout) || !empty($useBusinessHeaderFocusDescriptionLayout)) {
        $showHeader = $showHeaderHero;
    }
    $headerGradientEnabled = $showHeader && $headerGradientEnabled;
    $heroFadeEnabled = $showHeaderHero && $headerGradientEnabled;

    $heroSocialIcons = collect();
    if ($showHeaderHero) {
        $iconQuery = \App\Models\Link::where('user_id', $userinfo->id)->where('button_id', 94);
        if (\Illuminate\Support\Facades\Schema::hasColumn('links', 'is_disabled')) {
            $iconQuery->where('is_disabled', false);
        }
        $heroSocialIcons = $iconQuery->get();
    }
@endphp
@once
@push('wayvio-head-end')
<style>
/* Unified spacing + hierarchy tokens for all profile header layouts */
.ls-profile-header,
.ls-profile-summary-core {
    --ls-page-content-width: clamp(260px, 92vw, 520px);
    --ls-header-max-width: var(--ls-page-content-width);
    --ls-business-title-content-width: clamp(300px, 92vw, 820px);
    --ls-business-description-content-width: clamp(280px, 88vw, 860px);
    --ls-header-side-padding: clamp(20px, 4vw, 32px);
    --ls-size-title-focus: var(--ls-type-profile-title-size, clamp(2.9rem, 6.1vw, 4.85rem));
    --ls-size-title-secondary: var(--ls-type-profile-title-secondary-size, clamp(1.05rem, 1.8vw, 1.45rem));
    --ls-size-description-focus: var(--ls-type-profile-description-focus-size, clamp(3.1rem, 6.9vw, 5.2rem));
    --ls-size-description-secondary: var(--ls-type-profile-description-size, clamp(1.2rem, 2.5vw, 1.9rem));
    --ls-gap-title-description: clamp(16px, 2.2vw, 22px);
    --ls-gap-description-icons: clamp(16px, 2.1vw, 24px);
    --ls-gap-icons-content: clamp(40px, 5vw, 56px);
    --ls-brand-gap: clamp(12px, 1.7vw, 18px);
    --ls-brand-mark-size: clamp(28px, 3vw, 44px);
    --ls-icon-size: clamp(24px, 2.1vw, 30px);
    --ls-icon-gap: clamp(14px, 1.6vw, 20px);
    --ls-title-lockup-gap: clamp(10px, 1.5vw, 16px);
    --ls-title-max-width: 15ch;
    --ls-description-max-width: 34ch;
    --ls-hero-min-height: clamp(300px, 36vw, 410px);
    --ls-business-hero-height: 50vh;
    --ls-business-title-hero-min-height: var(--ls-business-hero-height);
    --ls-business-description-hero-min-height: var(--ls-business-hero-height);
}

@supports (height: 50svh) {
    .ls-profile-header,
    .ls-profile-summary-core {
        --ls-business-hero-height: 50svh;
    }
}

.ls-profile-summary-core {
    --ls-title-size: var(--ls-size-title-focus);
    --ls-description-size: var(--ls-size-description-secondary);
    width: min(100%, var(--ls-header-max-width));
    margin: 0 auto var(--ls-gap-icons-content);
    padding: 0 var(--ls-header-side-padding);
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    align-items: center;
    --ls-social-icons-gap: var(--ls-icon-gap);
    --ls-social-icon-size: var(--ls-icon-size);
}

.ls-profile-summary-core--inside-hero {
    margin-bottom: 0;
}

.ls-profile-summary-core--title-focus {
    --ls-title-size: var(--ls-size-title-focus);
    --ls-description-size: var(--ls-size-description-secondary);
    --ls-brand-mark-size: clamp(34px, calc(var(--ls-title-size) * 0.92), 78px);
    --ls-title-max-width: 15ch;
    --ls-description-max-width: 34ch;
}

.ls-profile-summary-core--title-focus:not(.ls-profile-summary-core--business) {
    --ls-title-size: clamp(1.85rem, 2.7vw, 2.55rem);
    --ls-brand-mark-size: clamp(38px, 3.6vw, 52px);
    --ls-title-max-width: 100%;
}

.ls-profile-summary-core--description-focus {
    --ls-title-size: var(--ls-size-title-secondary);
    --ls-description-size: clamp(2.5rem, 5.4vw, 4.35rem);
    --ls-brand-mark-size: clamp(26px, calc(var(--ls-title-size) * 1.02), 44px);
    --ls-title-max-width: 28ch;
    --ls-description-max-width: clamp(22rem, 86vw, 54rem);
}

.ls-profile-summary-core--description-focus-empty {
    --ls-title-size: var(--ls-size-title-focus);
    --ls-brand-mark-size: clamp(34px, calc(var(--ls-title-size) * 0.92), 78px);
    --ls-title-max-width: 15ch;
}

.ls-profile-summary-core__title {
    width: 100%;
    max-width: var(--ls-title-max-width);
    min-width: 0;
}

.ls-profile-summary-core__title .profile-heading {
    margin: 0 !important;
    text-align: center !important;
    font-size: var(--ls-title-size) !important;
    line-height: var(--ls-type-profile-title-line, 1.04) !important;
    font-weight: var(--ls-type-profile-title-weight, 800) !important;
    letter-spacing: 0;
    text-wrap: balance;
    word-break: normal !important;
    overflow-wrap: break-word !important;
    hyphens: none !important;
}

.ls-profile-summary-core__title--eyebrow .profile-heading {
    line-height: var(--ls-type-profile-title-secondary-line, 1.16) !important;
    font-weight: var(--ls-type-profile-title-secondary-weight, 700) !important;
    letter-spacing: 0;
    opacity: 0.96;
}

.ls-profile-summary-core__description {
    width: 100%;
    margin-top: var(--ls-gap-title-description);
}

.ls-profile-summary-core__description .description-parent {
    margin: 0 !important;
    padding: 0 !important;
    text-align: center !important;
    max-width: var(--ls-description-max-width);
    margin-inline: auto !important;
}

.ls-profile-summary-core__description .description-parent p {
    margin: 0 !important;
    text-align: center !important;
    word-break: normal !important;
    overflow-wrap: break-word;
    hyphens: none;
    font-size: var(--ls-description-size) !important;
    line-height: var(--ls-type-profile-title-line, 1.04) !important;
    font-weight: 750;
    letter-spacing: 0;
    text-wrap: balance;
}

.ls-profile-summary-core--title-focus .ls-profile-summary-core__description .description-parent p {
    font-weight: var(--ls-type-profile-description-weight, 500);
    line-height: var(--ls-type-profile-description-line, 1.28) !important;
    letter-spacing: 0;
}

.ls-profile-summary-core__icons {
    width: 100%;
    margin-top: var(--ls-gap-description-icons);
}

.ls-profile-summary-core__icons--no-description {
    margin-top: var(--ls-gap-title-description);
}

.ls-profile-summary-core__icons .social-icon-div {
    margin: 0 !important;
    padding: 0 !important;
    justify-content: center !important;
    align-items: center;
}

.ls-profile-summary-core__icons .social-icon {
    font-size: var(--ls-icon-size) !important;
}

.ls-profile-summary-core__brand {
    width: 100%;
    max-width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: center;
    flex-wrap: wrap;
    gap: var(--ls-brand-gap);
    row-gap: 10px;
}

.ls-profile-summary-core__brand--no-avatar {
    gap: 0;
}

.ls-profile-summary-core__brand-mark {
    width: var(--ls-brand-mark-size);
    height: var(--ls-brand-mark-size);
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}

.ls-profile-summary-core__brand-mark #avatar {
    width: var(--ls-brand-mark-size) !important;
    height: var(--ls-brand-mark-size) !important;
    min-width: 0 !important;
    aspect-ratio: 1 / 1;
    object-fit: cover !important;
    display: block;
}

.ls-profile-summary-core--business {
    width: min(100%, var(--ls-business-title-content-width));
    --ls-description-size: clamp(1rem, 1.8vw, 1.35rem);
    --ls-description-max-width: 42ch;
}

.ls-profile-summary-core--business .ls-profile-summary-core__title {
    width: 100%;
    max-width: var(--ls-title-max-width);
}

.ls-profile-summary-core--business:not(.ls-profile-summary-core--inside-hero) {
    padding-top: clamp(28px, 6vw, 54px);
}

.ls-profile-summary-core--business.ls-profile-summary-core--title-focus {
    --ls-title-size: clamp(1.95rem, 2.95vw, 2.78rem);
    --ls-title-max-width: min(100%, 940px);
    --ls-brand-mark-size: clamp(42px, 4vw, 58px);
    --ls-title-lockup-gap: clamp(12px, 1.5vw, 18px);
    --ls-gap-title-description: clamp(14px, 1.8vw, 20px);
    --ls-gap-icons-content: clamp(38px, 4.5vw, 54px);
    padding-left: clamp(16px, 2.8vw, 24px);
    padding-right: clamp(16px, 2.8vw, 24px);
}

.ls-profile-summary-core--business.ls-profile-summary-core--title-focus.ls-profile-summary-core--inside-hero {
    --ls-title-size: clamp(2rem, 3.2vw, 2.92rem);
    --ls-brand-mark-size: clamp(44px, 4.6vw, 64px);
    --ls-description-size: clamp(1rem, 1.35vw, 1.25rem);
}

.ls-profile-summary-core--business.ls-profile-summary-core--title-focus .ls-profile-summary-core__brand {
    width: auto;
    max-width: var(--ls-title-max-width);
    margin: 0 auto;
    flex-wrap: nowrap;
    gap: var(--ls-title-lockup-gap);
}

.ls-profile-summary-core--business.ls-profile-summary-core--title-focus .ls-profile-summary-core__title {
    width: auto;
    max-width: 100%;
    flex: 0 1 auto;
    min-width: 0;
}

.ls-profile-summary-core--business.ls-profile-summary-core--title-focus .ls-profile-summary-core__title .profile-heading {
    text-align: left !important;
    white-space: nowrap;
    text-wrap: nowrap;
    overflow-wrap: normal !important;
    word-break: keep-all !important;
}

.ls-profile-summary-core--business.ls-profile-summary-core--title-focus .ls-profile-summary-core__brand--no-avatar {
    width: 100%;
}

.ls-profile-summary-core--business.ls-profile-summary-core--title-focus .ls-profile-summary-core__brand--no-avatar .profile-heading {
    text-align: center !important;
}

.ls-profile-summary-core--business.ls-profile-summary-core--title-focus .ls-profile-summary-core__description .description-parent p {
    line-height: 1.34 !important;
    font-weight: 500;
}

.ls-profile-summary-core--business.ls-profile-summary-core--description-focus {
    width: min(100%, var(--ls-business-description-content-width));
    --ls-title-size: clamp(0.95rem, 1.2vw, 1.125rem);
    --ls-description-size: clamp(2.15rem, 4.4vw, 3.75rem);
    --ls-description-max-width: min(100%, 860px);
    --ls-brand-mark-size: clamp(24px, calc(var(--ls-title-size) * 1.55), 38px);
    --ls-gap-title-description: clamp(22px, 3vw, 36px);
    --ls-gap-description-icons: clamp(24px, 2.8vw, 34px);
    --ls-gap-icons-content: clamp(44px, 5.5vw, 66px);
}

.ls-profile-summary-core--business.ls-profile-summary-core--description-focus.ls-profile-summary-core--inside-hero {
    --ls-description-size: clamp(2.55rem, 4.8vw, 4rem);
    --ls-gap-title-description: clamp(22px, 2.6vw, 34px);
}

.ls-profile-summary-core--business.ls-profile-summary-core--description-focus .ls-profile-summary-core__brand {
    width: auto;
    max-width: min(100%, 34rem);
    margin: 0 auto;
    flex-wrap: nowrap;
    gap: clamp(9px, 1.2vw, 14px);
}

.ls-profile-summary-core--business.ls-profile-summary-core--description-focus .ls-profile-summary-core__title {
    width: auto;
    max-width: min(100%, 28ch);
}

.ls-profile-summary-core--business.ls-profile-summary-core--description-focus .ls-profile-summary-core__title .profile-heading {
    text-align: left !important;
}

.ls-profile-summary-core--business.ls-profile-summary-core--description-focus .ls-profile-summary-core__brand--no-avatar .profile-heading {
    text-align: center !important;
}

.ls-profile-summary-core--business.ls-profile-summary-core--description-focus .ls-profile-summary-core__description .description-parent p {
    line-height: 1.06 !important;
    font-weight: 800;
}

.ls-profile-summary-core--business.ls-profile-summary-core--description-focus-empty {
    width: min(100%, var(--ls-business-title-content-width));
    --ls-title-size: clamp(2.35rem, 4.4vw, 3.65rem);
    --ls-title-max-width: min(100%, 940px);
    --ls-brand-mark-size: clamp(44px, 5vw, 72px);
}

/* Spacing when no header is shown */
.ls-profile-header--no-cover {
    padding-top: 0;
}

.ls-profile-header--cover-no-avatar {
    margin-bottom: clamp(18px, 3vw, 28px);
}

/* Tighter vertical spacing below avatar */
.ls-avatar-holder {
    margin-bottom: 2px !important;
}

/* Remove extra offset above the profile header */
.container > .row > .column {
    margin-top: 0 !important;
}

@media (min-width: 541px) and (max-width: 1024px) {
    .ls-profile-summary-core--business.ls-profile-summary-core--title-focus {
        --ls-title-size: clamp(1.9rem, 3.8vw, 2.62rem);
        --ls-title-max-width: min(100%, 860px);
        --ls-brand-mark-size: clamp(40px, 4.8vw, 56px);
        --ls-title-lockup-gap: clamp(11px, 1.8vw, 16px);
    }

    .ls-profile-summary-core--business.ls-profile-summary-core--title-focus.ls-profile-summary-core--inside-hero {
        --ls-title-size: clamp(1.95rem, 4.0vw, 2.72rem);
        --ls-description-size: clamp(1rem, 1.55vw, 1.2rem);
    }

    .ls-profile-summary-core--business.ls-profile-summary-core--description-focus {
        --ls-description-size: clamp(2.25rem, 5.2vw, 3.55rem);
        --ls-description-max-width: min(100%, 760px);
    }

    .ls-profile-summary-core--business.ls-profile-summary-core--description-focus.ls-profile-summary-core--inside-hero {
        --ls-description-size: clamp(2.35rem, 5.4vw, 3.7rem);
    }

    .ls-profile-summary-core--title-focus:not(.ls-profile-summary-core--business) {
        --ls-title-size: clamp(1.8rem, 3.4vw, 2.4rem);
        --ls-brand-mark-size: clamp(36px, 4.0vw, 48px);
    }
}

@media (max-width: 900px) {
    .ls-profile-summary-core--business.ls-profile-summary-core--title-focus .ls-profile-summary-core__title .profile-heading {
        white-space: normal;
        text-wrap: balance;
        overflow-wrap: break-word !important;
        word-break: normal !important;
    }
}

@media (max-width: 540px) {
    .ls-profile-header {
        --ls-header-side-padding: 18px;
        --ls-gap-icons-content: 32px;
        --ls-icon-size: 24px;
        --ls-icon-gap: 16px;
        --ls-title-lockup-gap: 10px;
    }

    .ls-profile-summary-core--title-focus {
        --ls-title-max-width: 12ch;
        --ls-description-max-width: 26ch;
    }

    .ls-profile-summary-core--title-focus:not(.ls-profile-summary-core--business) {
        --ls-title-size: clamp(1.6rem, 6.0vw, 2.0rem);
        --ls-brand-mark-size: clamp(32px, 8.5vw, 40px);
        --ls-title-max-width: 100%;
    }

    .ls-profile-summary-core--description-focus {
        --ls-description-size: clamp(2.1rem, 8.2vw, 3rem);
        --ls-description-max-width: 100%;
    }

    .ls-profile-summary-core__description .description-parent p {
        text-wrap: pretty;
    }

    .ls-profile-summary-core--business:not(.ls-profile-summary-core--inside-hero) {
        padding-top: 26px;
    }

    .ls-profile-summary-core--business.ls-profile-summary-core--title-focus:not(.ls-profile-summary-core--inside-hero) {
        --ls-title-size: clamp(1.85rem, 7.2vw, 2.35rem);
        --ls-title-max-width: min(100%, 92vw);
        --ls-brand-mark-size: clamp(36px, 10vw, 46px);
        --ls-title-lockup-gap: 10px;
    }

    .ls-profile-summary-core--business.ls-profile-summary-core--title-focus.ls-profile-summary-core--inside-hero {
        --ls-title-size: clamp(1.9rem, 7.5vw, 2.45rem);
        --ls-title-max-width: min(100%, 92vw);
        --ls-brand-mark-size: clamp(38px, 10.5vw, 48px);
        --ls-description-max-width: 28ch;
    }

    .ls-profile-summary-core--business.ls-profile-summary-core--title-focus .ls-profile-summary-core__title {
        max-width: var(--ls-title-max-width);
    }

    .ls-profile-summary-core--business.ls-profile-summary-core--title-focus .ls-profile-summary-core__brand {
        max-width: 100%;
    }

    .ls-profile-summary-core--business.ls-profile-summary-core--description-focus {
        --ls-description-size: clamp(2.25rem, 10.5vw, 3.65rem);
        --ls-description-max-width: 19ch;
    }

    .ls-profile-summary-core--business.ls-profile-summary-core--description-focus .ls-profile-summary-core__brand {
        flex-wrap: nowrap;
        justify-content: center;
        max-width: 100%;
    }

    .ls-profile-summary-core--business.ls-profile-summary-core--description-focus .ls-profile-summary-core__title .profile-heading {
        text-align: left !important;
    }

    .ls-profile-summary-core--business.ls-profile-summary-core--description-focus .ls-profile-summary-core__brand--no-avatar .profile-heading {
        text-align: center !important;
    }

    .ls-profile-header--cover-no-avatar {
        margin-bottom: 18px;
    }
}
</style>
@if($showHeader)
<style>
    /* Remove the 5% margin that creates the gap at top */
    .container > .row > .column {
        margin-top: 0 !important;
    }

    /* Position header to break out of container and start at page top */
    .ls-profile-header {
        position: relative;
        width: 100vw;
        max-width: none;
        left: 50%;
        right: 50%;
        margin-left: -50vw;
        margin-right: -50vw;
        margin-top: 0;
        padding: 0;
        overflow: hidden;
        z-index: 10;
    }

    /* The cover image extends to top of page */
    .ls-header-cover {
        width: 100%;
        height: 280px;
        border-radius: 0;
        position: relative;
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        margin: 0;
        padding: 0;
        overflow: hidden;
    }

    .ls-profile-header--hero .ls-header-cover::before {
        content: "";
        position: absolute;
        inset: 0;
        background:
            linear-gradient(180deg, rgba(15, 19, 28, 0.12) 0%, rgba(15, 19, 28, 0.34) 48%, rgba(15, 19, 28, 0.68) 100%),
            linear-gradient(180deg, rgba(255, 255, 255, 0.08) 0%, transparent 26%);
        pointer-events: none;
        z-index: 1;
    }

    /* Avatar positioning */
    .ls-avatar-holder {
        display: flex;
        justify-content: center;
        position: relative;
        z-index: 11;
    }

    .ls-avatar-holder--overlay {
        margin-top: -72px;
        margin-bottom: 2px;
    }

    .ls-profile-header--hero {
        min-height: var(--ls-hero-min-height);
        --hero-fade-height: 56%;
        --hero-fade-bleed: 10px;
        --hero-fade-color: var(--ls-page-background-start, var(--template-background, #111827));
        overflow: visible;
    }

    .ls-profile-header--hero.ls-profile-header--business-title-focus {
        --ls-hero-min-height: var(--ls-business-title-hero-min-height);
    }

    .ls-profile-header--hero.ls-profile-header--business-description-focus {
        --ls-hero-min-height: var(--ls-business-description-hero-min-height);
    }

    .ls-profile-header--hero.ls-profile-header--business-layout {
        height: var(--ls-hero-min-height);
        min-height: var(--ls-hero-min-height);
    }

    .ls-profile-header--hero .ls-header-cover {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        overflow: visible;
    }

    .ls-header-cover__bottom-fade {
        position: absolute;
        left: 0;
        right: 0;
        bottom: calc(-1 * var(--hero-fade-bleed, 8px));
        height: calc(var(--hero-fade-height) + var(--hero-fade-bleed, 8px));
        background: linear-gradient(
            to bottom,
            transparent 0%,
            color-mix(in srgb, var(--hero-fade-color) 18%, transparent) 30%,
            color-mix(in srgb, var(--hero-fade-color) 72%, transparent) 70%,
            var(--hero-fade-color) calc(100% - var(--hero-fade-bleed, 8px)),
            var(--hero-fade-color) 100%
        );
        pointer-events: none;
        z-index: 4;
    }

    .ls-profile-header:not(.ls-profile-header--hero) {
        --hero-fade-color: var(--ls-page-background-start, var(--template-background, #111827));
        --hero-fade-height: 60%;
        --hero-fade-bleed: 8px;
        overflow: visible;
    }

    .ls-profile-header:not(.ls-profile-header--hero) .ls-header-cover {
        overflow: visible;
    }

    .ls-profile-hero-content {
        position: relative;
        z-index: 11;
        min-height: var(--ls-hero-min-height);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: clamp(54px, 6vw, 76px) 0 clamp(24px, 2.8vw, 34px);
        box-sizing: border-box;
    }

    .ls-profile-header--hero.ls-profile-header--business-layout .ls-profile-hero-content {
        height: 100%;
        min-height: 100%;
        padding: clamp(38px, 5vw, 58px) 0 clamp(20px, 2.4vw, 28px);
    }

    .ls-profile-header--hero.ls-profile-header--business-description-focus .ls-profile-hero-content {
        padding-bottom: clamp(60px, 8vw, 90px);
    }


    .ls-profile-hero-inner {
        width: 100%;
        max-width: none;
        margin: 0 auto;
        box-sizing: border-box;
        position: relative;
        z-index: 3;
    }

    .ls-profile-header--hero .ls-profile-summary-core {
        margin-bottom: 0;
        padding-top: 0;
        padding-bottom: 0;
    }

    .ls-profile-header--hero:not(.ls-profile-header--business-layout) .ls-profile-summary-core--inside-hero {
        --ls-title-size: clamp(2.2rem, 3.8vw, 3.35rem);
        --ls-brand-mark-size: clamp(44px, 4.6vw, 64px);
    }

    .ls-avatar-holder--hero {
        margin-top: 0 !important;
        margin-bottom: 10px !important;
    }

    /* Keep share button, admin bar, and report icon on top with proper z-index */
    .share-button,
    #share-button,
    .report-icon,
    #report-icon {
        z-index: 100 !important;
    }

    .admin-bar,
    #admin-bar {
        z-index: 101 !important;
    }

    @media (min-width: 541px) and (max-width: 1024px) {
        .ls-profile-header--hero.ls-profile-header--business-title-focus {
            --ls-hero-min-height: var(--ls-business-title-hero-min-height);
        }

        .ls-profile-header--hero.ls-profile-header--business-description-focus {
            --ls-hero-min-height: var(--ls-business-description-hero-min-height);
        }

        .ls-profile-header--hero:not(.ls-profile-header--business-layout) .ls-profile-summary-core--inside-hero {
            --ls-title-size: clamp(2.15rem, 4.6vw, 3.1rem);
        }
    }

    @media (max-width: 540px) {
        .ls-header-cover {
            height: 220px;
        }

        .ls-avatar-holder--overlay {
            margin-top: -64px;
            margin-bottom: 2px;
        }

        .ls-profile-header--hero {
            --ls-hero-min-height: 280px;
        }

        .ls-profile-header--hero.ls-profile-header--business-title-focus {
            --ls-hero-min-height: var(--ls-business-title-hero-min-height);
        }

        .ls-profile-header--hero.ls-profile-header--business-description-focus {
            --ls-hero-min-height: var(--ls-business-description-hero-min-height);
        }

        .ls-profile-header--hero:not(.ls-profile-header--business-layout) .ls-profile-summary-core--inside-hero {
            --ls-title-size: clamp(1.9rem, 7.5vw, 2.45rem);
            --ls-brand-mark-size: clamp(38px, 10.5vw, 48px);
        }

        .ls-profile-hero-content {
            padding-top: 46px;
            padding-bottom: 22px;
        }

        .ls-profile-header--hero.ls-profile-header--business-layout .ls-profile-hero-content {
            padding-top: 34px;
            padding-bottom: 18px;
        }
    }
</style>
@endif
@if($heroFadeEnabled)
<style>
    .ls-profile-header--hero .ls-header-cover {
        -webkit-mask-image: linear-gradient(to bottom, black 30%, transparent 88%);
        mask-image: linear-gradient(to bottom, black 30%, transparent 88%);
    }
    .ls-profile-header--hero .ls-header-cover::before {
        background:
            linear-gradient(180deg, rgba(15, 19, 28, 0.14) 0%, rgba(15, 19, 28, 0.20) 100%),
            linear-gradient(180deg, rgba(255, 255, 255, 0.08) 0%, transparent 26%);
    }
    .ls-profile-header--hero .ls-header-cover__bottom-fade {
        display: none;
    }
</style>
@endif
@endpush
@endonce

@if($showHeader)
@push('wayvio-body-end')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var shareDiv = document.querySelector('.sharediv');
    if (shareDiv) {
        shareDiv.classList.add('sharediv--over-header');
    }

});
</script>
@endpush
@endif

<div class="ls-profile-header {{ $showHeader ? '' : 'ls-profile-header--no-cover' }} {{ $showHeader && !$renderStandardAvatar ? 'ls-profile-header--cover-no-avatar' : '' }} {{ $showHeaderHero ? 'ls-profile-header--hero' : '' }} {{ $useBusinessProfileLayout ? 'ls-profile-header--business-layout' : '' }} {{ $useBusinessHeaderFocusDescriptionLayout ? 'ls-profile-header--business-description-focus' : ($useBusinessProfileLayout ? 'ls-profile-header--business-title-focus' : '') }}">
    @if($showHeader)
        <div class="ls-header-cover" style="background-image: url('{{ $headerUrl }}');">
            @if($headerGradientEnabled)
                <div class="ls-header-cover__bottom-fade" aria-hidden="true"></div>
            @endif
        </div>
    @endif
    @if($showHeaderHero)
        <div class="ls-profile-hero-content">
            <div class="ls-profile-hero-inner">
                @if($useBusinessHeaderFocusDescriptionLayout)
                    @include('wayvio.elements.profile-summary-business-header-focus-description', ['insideHero' => true, 'icons' => $heroSocialIcons])
                @elseif($useBusinessProfileLayout)
                    @include('wayvio.elements.profile-summary-business', ['insideHero' => true, 'icons' => $heroSocialIcons])
                @else
                    @php
                        $rawHeroDescription = $info->littlelink_description ?? '';
                        $heroHasDescription = trim(strip_tags((string) $rawHeroDescription)) !== '';
                    @endphp
                    <div class="ls-profile-summary-core ls-profile-summary-core--title-focus fadein">
                    @if($showAvatar)
                        <div class="ls-avatar-holder ls-avatar-holder--hero">
                            @include('wayvio.elements.avatar')
                        </div>
                    @endif
                        <div class="ls-profile-summary-core__title">
                            @include('wayvio.elements.heading', ['headingBottomMargin' => '0'])
                        </div>
                        @if($heroHasDescription)
                            <div class="ls-profile-summary-core__description">
                                @include('wayvio.elements.bio')
                            </div>
                        @endif
                        <div class="ls-profile-summary-core__icons {{ $heroHasDescription ? '' : 'ls-profile-summary-core__icons--no-description' }}">
                            @include('wayvio.elements.icons', ['icons' => $heroSocialIcons])
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @elseif($renderStandardAvatar)
        <div class="ls-avatar-holder {{ $showHeader ? 'ls-avatar-holder--overlay' : '' }}">
            @include('wayvio.elements.avatar')
        </div>
    @endif
</div>
