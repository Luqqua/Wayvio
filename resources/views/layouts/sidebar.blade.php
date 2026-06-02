@php
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
$ownerUser = Auth::user();
$agencyContext = app(\App\Services\Agency\AgencyHubContext::class);
$agencySidebar = $ownerUser ? $agencyContext->sidebarData($ownerUser, request()) : [
    'is_agency' => false,
    'active_user' => null,
    'hubs' => collect(),
    'slots' => ['used' => 0, 'total' => 1],
];
$activeEditorUser = $agencySidebar['active_user'] ?? $ownerUser;
$usrhandl = $activeEditorUser?->littlelink_name ?? $ownerUser?->littlelink_name;
$isAgencyAccount = (bool) ($agencySidebar['is_agency'] ?? false);
$agencyHubs = $agencySidebar['hubs'] ?? collect();
$agencySlots = $agencySidebar['slots'] ?? ['used' => 0, 'total' => 1];
$domainUrlResolver = app(\App\Services\Domains\DomainUrlResolver::class);
$reservedSlugs = reservedSlugs();
$profileUrl = ($ownerUser && $activeEditorUser)
    ? $domainUrlResolver->profileUrlForEditor($ownerUser, $activeEditorUser)
    : url('/');
$brandingSidebar = config('branding.dashboard.sidebar', []);
$sidebarColorClass = $brandingSidebar['color_class'] ?? 'sidebar-white';
$sidebarTypeClasses = $brandingSidebar['type_classes'] ?? ['sidebar-base'];
$sidebarItemClass = $brandingSidebar['item_class'] ?? 'navs-rounded-all';
$resolvedSidebarClasses = array_unique(array_filter(array_merge(
    ['sidebar-default'],
    is_array($sidebarColorClass) ? $sidebarColorClass : [$sidebarColorClass],
    is_array($sidebarTypeClasses) ? $sidebarTypeClasses : [$sidebarTypeClasses],
    is_array($sidebarItemClass) ? $sidebarItemClass : [$sidebarItemClass]
)));
$clearClientPrefs = config('branding.dashboard.clear_client_prefs', true);
$activeContextPageId = (int) ($agencySidebar['active_user_id'] ?? ($activeEditorUser?->id ?? $ownerUser?->id ?? 0));
$publicationColumnsReady = Schema::hasTable('users') && Schema::hasColumn('users', 'is_published');
$activeEditorPublished = !$publicationColumnsReady || (bool) ($activeEditorUser?->is_published ?? false);
@endphp
<!doctype html>
@include('layouts.lang')
<html>
  <head>
    <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
      <title>{{ strtoupper((string) config('app.name')) }}</title>

      <script src="{{asset('assets/js/detect-dark-mode.js')}}"></script>
      
      <base href="{{url()->current()}}" />

	  @include('layouts.analytics')
	  @stack('sidebar-stylesheets')
    @php
    // Update the 'updated_at' timestamp for the currently authenticated user
    if (auth()->check()) {
        $user = auth()->user();
        $user->touch();
    }
    @endphp

      <!-- Favicon -->
      @if(file_exists(base_path("assets/wayvio/images/").findFile('favicon')))
      <link rel="icon" type="image/png" href="{{ asset('assets/wayvio/images/'.findFile('favicon')) }}">
      @else
      <link rel="icon" type="image/svg+xml" href="{{ asset('assets/wayvio/images/logo.svg') }}">
      @endif
      
      <!-- Library / Plugin Css Build -->
      <link rel="stylesheet" href="{{asset('assets/css/core/libs.min.css')}}" />
      
      <!-- Aos Animation Css -->
      <link rel="stylesheet" href="{{asset('assets/vendor/aos/dist/aos.css')}}" />
      
      @include('layouts.fonts')
      
      <!-- Hope Ui Design System Css -->
      <link rel="stylesheet" href="{{asset('assets/css/hope-ui.min.css?v=2.0.0')}}" />
      
      <!-- Custom Css -->
      <link rel="stylesheet" href="{{asset('assets/css/custom.min.css?v=2.0.0')}}" />
      
      <!-- Dark Css -->
      <link rel="stylesheet" href="{{asset('assets/css/dark.min.css')}}" />
      
      <!-- Customizer Css -->
            @if(file_exists(base_path("assets/dashboard-themes/dashboard.css")))
      <link rel="stylesheet" href="{{asset('assets/dashboard-themes/dashboard.css')}}" />
      @else
      <link rel="stylesheet" href="{{asset('assets/css/customizer.min.css')}}" />
      @endif
      
      <!-- RTL Css -->
      <link rel="stylesheet" href="{{asset('assets/css/rtl.min.css')}}" />
      
	  <meta name="csrf-token" content="{{ csrf_token() }}">
		  <link rel="stylesheet" href="{{ asset('assets/wayvio/css/hover-min.css') }}">
		  <link rel="stylesheet" href="{{ asset('assets/wayvio/css/animate.css') }}">
		  <link rel="stylesheet" href="{{ asset('assets/external-dependencies/bootstrap-icons.css') }}">
      <style>
        /* Reduce bright corner/glow artifacts on dark sidebar surfaces */
        body.dark .sidebar.sidebar-default .nav-link:not(.static-item).active,
        body.dark .sidebar.sidebar-default .nav-link:not(.static-item)[aria-expanded=true],
        body.dark .sidebar.sidebar-dark.sidebar-default .nav-link.active,
        body.dark .sidebar.sidebar-color.sidebar-default .nav-link.active,
        body.dark .sidebar.sidebar-color.sidebar-default .nav-link[aria-expanded=true] {
          box-shadow: 0 8px 18px rgba(0, 0, 0, 0.28) !important;
        }

        body.dark .btn-border,
        body.dark .btn.btn-border {
          border-color: #3b4256;
        }

        .sidebar {
          height: 100vh;
          max-height: 100vh;
          overflow-x: hidden;
        }

        .sidebar .sidebar-body,
        .sidebar .data-scrollbar {
          flex: 1 1 auto;
          min-height: 0;
          overflow-x: hidden;
        }

        .sidebar .sidebar-list {
          padding-bottom: calc(1rem + env(safe-area-inset-bottom, 0px));
          overflow-x: hidden;
        }

        .sidebar .iq-main-menu > .nav-item:last-child {
          padding-bottom: calc(3rem + env(safe-area-inset-bottom, 0px));
        }

        .sidebar-zone-divider {
          padding: 0 1rem;
          margin: 0.5rem 0;
        }

        .sidebar-zone-rule {
          height: 1px;
          background: rgba(148, 163, 184, 0.22);
        }

        .sidebar-group-gap {
          padding: 0 1rem;
          margin: 0.35rem 0;
          pointer-events: none;
        }

        .sidebar-zone-rule--short {
          width: 64%;
          margin: 0 auto;
          opacity: 0.85;
        }

        .sidebar.sidebar-mini .agency-context-panel,
        .sidebar-hover.sidebar-mini:hover .agency-context-panel {
          display: none;
        }

        .sidebar .sidebar-header {
          gap: 0.75rem;
          min-height: 66px;
          justify-content: space-between;
        }

        .sidebar .sidebar-header .navbar-brand {
          display: flex;
          align-items: center;
          min-width: 0;
          flex: 1 1 auto;
          overflow: hidden;
        }

        .sidebar .sidebar-header .navbar-brand .logo-title {
          overflow: hidden;
          white-space: nowrap;
          text-overflow: ellipsis;
        }

        .sidebar .sidebar-header .sidebar-toggle--desktop {
          position: static;
          top: auto;
          right: auto;
          margin-left: auto;
          margin-right: 0;
          width: 30px;
          height: 30px;
          padding: 0;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          background: transparent;
          color: var(--bs-body-color);
          border-radius: 0;
          box-shadow: none;
          flex: 0 0 auto;
          transform: translateX(-0.2rem);
        }

        .sidebar .sidebar-header .sidebar-toggle--desktop:hover {
          background: transparent;
          color: var(--bs-primary);
          box-shadow: none;
        }

        .sidebar .sidebar-header .sidebar-toggle--desktop .icon {
          transform: none;
          width: 30px;
          height: 30px;
        }

        .sidebar .sidebar-header .sidebar-toggle--desktop .icon-20 {
          transform: none;
          width: 30px;
          height: 30px;
        }

        body.dark .sidebar .sidebar-header .sidebar-toggle--desktop {
          color: #8a92a6 !important;
        }

        body.dark .sidebar.sidebar-dark .sidebar-header .sidebar-toggle--desktop,
        body.dark .sidebar.sidebar-color .sidebar-header .sidebar-toggle--desktop {
          color: #ffffff !important;
        }

        .sidebar.sidebar-mini:not(.sidebar-hover:hover) .sidebar-header .navbar-brand {
          display: none;
        }

        .sidebar.sidebar-mini:not(.sidebar-hover:hover) .sidebar-header {
          justify-content: center;
          padding-left: 1rem;
          padding-right: 1rem;
        }

        .sidebar.sidebar-mini:not(.sidebar-hover:hover) .sidebar-header .sidebar-toggle--desktop {
          margin-left: 0;
          margin-right: 0;
          transform: none;
        }

        .sidebar .iq-main-menu,
        .sidebar .iq-main-menu > .nav-item,
        .sidebar .iq-main-menu .nav-link,
        .sidebar .agency-context-panel,
        .sidebar .agency-context-card,
        .sidebar .agency-context-form,
        .sidebar .agency-context-select {
          min-width: 0;
          max-width: 100%;
        }

        .sidebar .agency-context-card {
          width: 100%;
          overflow: hidden;
        }

        .sidebar .agency-context-line {
          display: block;
          min-width: 0;
          max-width: 100%;
          overflow: hidden;
          white-space: nowrap;
          text-overflow: ellipsis;
        }

        .sidebar .agency-context-value {
          display: inline-block;
          max-width: 100%;
          overflow: hidden;
          white-space: nowrap;
          text-overflow: ellipsis;
          vertical-align: bottom;
        }

        .iq-banner {
          position: relative;
        }

        .iq-banner .dashboard-topbar {
          position: absolute;
          top: 0;
          left: 0;
          right: 0;
          width: 100%;
          background: transparent !important;
          backdrop-filter: none;
          border-bottom: 0;
          box-shadow: none;
          z-index: 6;
        }

        .iq-banner .dashboard-topbar .navbar-inner {
          min-height: 72px;
          background: transparent;
        }

        /* Avoid duplicate branding: show topbar brand only on mobile. */
        .iq-banner .dashboard-topbar .navbar-brand {
          display: none;
        }

        .iq-banner .dashboard-topbar:not(.menu-sticky) .navbar-brand,
        .iq-banner .dashboard-topbar:not(.menu-sticky) .navbar-brand .logo-title {
          color: #ffffff;
        }

        .iq-banner .dashboard-topbar:not(.menu-sticky) .sidebar-toggle,
        .iq-banner .dashboard-topbar:not(.menu-sticky) .navbar-toggler {
          color: rgba(255, 255, 255, 0.8);
          border-color: rgba(255, 255, 255, 0.28);
        }

        .iq-banner .dashboard-topbar:not(.menu-sticky) .navbar-toggler .navbar-toggler-bar {
          background: rgba(255, 255, 255, 0.8);
        }

        .iq-banner .dashboard-topbar:not(.menu-sticky) .navbar-list > li,
        .iq-banner .dashboard-topbar:not(.menu-sticky) .navbar-list > li .nav-link,
        .iq-banner .dashboard-topbar:not(.menu-sticky) .navbar-list > li .dropdown-toggle,
        .iq-banner .dashboard-topbar:not(.menu-sticky) .navbar-list > li .caption-title,
        .iq-banner .dashboard-topbar:not(.menu-sticky) .navbar-list > li .caption-sub-title {
          color: rgba(255, 255, 255, 0.8) !important;
        }

        .iq-banner .dashboard-topbar:not(.menu-sticky) .view-page-btn,
        .iq-banner .dashboard-topbar:not(.menu-sticky) .view-page-share {
          background: rgba(255, 255, 255, 0.12) !important;
          border-color: rgba(255, 255, 255, 0.24) !important;
          color: rgba(255, 255, 255, 0.8) !important;
          box-shadow: none;
        }

        .iq-banner .dashboard-topbar:not(.menu-sticky) .view-page-btn:hover,
        .iq-banner .dashboard-topbar:not(.menu-sticky) .view-page-share:hover {
          background: rgba(255, 255, 255, 0.18) !important;
          color: #ffffff !important;
        }

        .iq-banner .dashboard-topbar .view-page-actions-wrap {
          padding-top: 0.85rem;
        }

        .iq-banner .header-block {
          height: 264px !important;
          background-color: var(--bs-primary);
          border-bottom-left-radius: 1rem;
          border-bottom-right-radius: 1rem;
          margin-bottom: 0 !important;
          overflow: visible;
          position: relative;
        }

        .iq-banner .header-block .iq-header-img {
          height: 264px !important;
          border-bottom-left-radius: 1rem;
          border-bottom-right-radius: 1rem;
        }

        /* Keep first page block overlapping the hero across all breakpoints.
           In this layout, content is rendered inside `.iq-banner`, not as a sibling. */
        .main-content > .iq-banner > .content-inner.mt-n5,
        .main-content > .iq-banner > .conatiner-fluid.content-inner.mt-n5,
        .main-content > .iq-banner > .container-fluid.content-inner.mt-n5 {
          background: transparent;
          margin-top: -3rem !important;
          position: relative;
          z-index: 3;
        }

        /* Normalize inter-card spacing on opted-in pages.
           This keeps card padding/layout untouched and avoids stacked margins. */
        .main-content > .iq-banner > .content-inner.ls-consistent-spacing .card {
          margin-bottom: 0;
        }

        .iq-banner .header-block .hero-mobile-actions {
          position: absolute;
          right: calc(var(--bs-gutter-x, 1rem) * 2) !important;
          left: calc(var(--bs-gutter-x, 1rem) * 2) !important;
          top: 5.35rem;
          z-index: 5;
          display: flex;
          justify-content: flex-end;
          gap: 0.5rem;
          opacity: 1;
          visibility: visible;
          transform: translateY(0);
          transition: opacity 0.18s ease, transform 0.18s ease, visibility 0s linear 0.18s;
          will-change: opacity, transform;
        }

        .iq-banner.mobile-menu-open .header-block .hero-mobile-actions {
          opacity: 0;
          visibility: hidden;
          pointer-events: none;
          transform: translateY(-0.35rem);
          transition-delay: 0s, 0s, 0s;
        }

        .iq-banner .header-block .hero-mobile-actions .btn {
          background: rgba(255, 255, 255, 0.12) !important;
          border-color: rgba(255, 255, 255, 0.24) !important;
          color: rgba(255, 255, 255, 0.9) !important;
        }

        .iq-banner .header-block .hero-mobile-actions .btn:hover {
          background: rgba(255, 255, 255, 0.18) !important;
          color: #ffffff !important;
        }

        .iq-banner .header-block .hero-mobile-actions .hero-mobile-publish-note {
          flex: 0 0 100%;
          width: 100%;
          text-align: right;
          line-height: 1.25;
        }

        @media (max-width: 991.98px) {
          .iq-banner .dashboard-topbar .navbar-brand {
            display: flex;
            margin-left: 0 !important;
          }

          .iq-banner .dashboard-topbar {
            left: 0;
            right: 0;
          }

          .iq-banner .dashboard-topbar .navbar-collapse {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            min-height: calc(100vh - 72px);
            padding: 1rem 0.85rem 1.25rem;
            background: rgba(11, 31, 31, 0.94);
            border-top: 1px solid rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(8px);
            overflow: visible;
            z-index: 1205;
          }

          .iq-banner .dashboard-topbar .mobile-sidebar-menu {
            width: 100%;
            overflow: visible;
            padding-right: 0.2rem;
          }

          .iq-banner .dashboard-topbar .mobile-sidebar-menu .navbar-nav {
            width: 100%;
            float: none;
            flex-direction: column;
            align-items: stretch !important;
            gap: 0.45rem;
            padding: 0;
            margin: 0;
          }

          .iq-banner .dashboard-topbar .mobile-sidebar-menu .nav-item {
            width: 100%;
            margin: 0 !important;
            padding: 0 !important;
          }

          .iq-banner .dashboard-topbar .mobile-sidebar-menu .nav-link {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            min-height: 46px;
            padding: 0.72rem 0.9rem;
            border-radius: 0.72rem;
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.92) !important;
          }

          .iq-banner .dashboard-topbar .mobile-sidebar-menu .nav-link.active {
            background: rgba(255, 255, 255, 0.96);
            border-color: rgba(255, 255, 255, 0.9);
            color: var(--bs-primary) !important;
          }

          .iq-banner .dashboard-topbar .mobile-sidebar-menu .nav-link.static-item {
            min-height: 0;
            padding: 0.35rem 0.15rem;
            border: none;
            border-radius: 0;
            background: transparent;
            color: rgba(255, 255, 255, 0.7) !important;
          }

          .iq-banner .dashboard-topbar .mobile-sidebar-menu .nav-link .item-name,
          .iq-banner .dashboard-topbar .mobile-sidebar-menu .nav-link .default-icon,
          .iq-banner .dashboard-topbar .mobile-sidebar-menu .nav-link .mini-icon {
            color: inherit !important;
          }

          .iq-banner .dashboard-topbar .mobile-sidebar-menu .nav-link .mini-icon {
            display: none !important;
          }

          .iq-banner .dashboard-topbar .mobile-sidebar-menu .nav-link .default-icon {
            display: inline !important;
          }

          .iq-banner .dashboard-topbar .mobile-sidebar-menu .sidebar-zone-divider {
            padding: 0.25rem 0;
          }

          .iq-banner .dashboard-topbar .mobile-sidebar-menu .sidebar-zone-rule {
            background: rgba(255, 255, 255, 0.22);
          }
        }

        @media (max-width: 991.98px) {

          .iq-banner .header-block {
            height: 220px !important;
          }

          .iq-banner .header-block .iq-header-img {
            height: 220px !important;
          }
        }

        @media (max-width: 575.98px) {
          .iq-banner .dashboard-topbar .navbar-inner {
            min-height: 64px;
          }

          .iq-banner .dashboard-topbar .navbar-collapse {
            min-height: calc(100vh - 64px);
          }

          .iq-banner .header-block .hero-mobile-actions {
            right: calc(var(--bs-gutter-x, 1rem) * 2) !important;
            left: calc(var(--bs-gutter-x, 1rem) * 2) !important;
            top: 4.6rem;
            gap: 0.4rem;
          }

          .iq-banner .header-block .hero-mobile-actions .btn {
            font-size: 0.76rem;
            padding: 0.35rem 0.55rem;
          }
        }

        @media (min-width: 992px) and (max-width: 1199.98px) {
          .sidebar.sidebar-mini {
            -webkit-transform: translateX(0) !important;
                -ms-transform: translateX(0) !important;
                    transform: translateX(0) !important;
          }

          .sidebar + .main-content,
          .sidebar.sidebar-mini + .main-content {
            margin-left: var(--sidebar-width) !important;
          }
        }

      </style>

	  </head>
  <body class="  ">
    <!-- loader Start -->
    <div id="loading">
      <div class="loader simple-loader">
          <div class="loader-body"></div>
      </div>    </div>
    <!-- loader END -->
    
    <aside class="sidebar {{ implode(' ', $resolvedSidebarClasses) }}">
        <div class="sidebar-header d-flex align-items-center">
            <a href="{{ route('panelIndex') }}" class="navbar-brand">
                
                <!--Logo start-->
                <div class="logo-main">
                @if(file_exists(base_path("assets/wayvio/images/").findFile('avatar')))
                <div class="logo-normal">
                  <img class="img logo" src="{{ asset('assets/wayvio/images/'.findFile('avatar')) }}" style="width:auto;height:30px;">
              </div>
              <div class="logo-mini">
                <img class="img logo" src="{{ asset('assets/wayvio/images/'.findFile('avatar')) }}" style="width:auto;height:30px;">
              </div>
                @else
                <div class="logo-normal">
                  <img class="img logo" type="image/svg+xml" src="{{ asset('assets/wayvio/images/logo.svg') }}" width="40px" height="40px">
              </div>
              <div class="logo-mini">
                <img class="img logo" type="image/svg+xml" src="{{ asset('assets/wayvio/images/logo.svg') }}" width="40px" height="40px">
              </div>
                @endif
                </div>
                <!--logo End-->
                
                <h4 class="logo-title">{{ strtoupper((string) config('app.name')) }}</h4>
            </a>
            <div class="sidebar-toggle sidebar-toggle--desktop d-none d-lg-flex" data-toggle="sidebar" data-active="true">
                <i class="icon">
                    <svg width="20" height="20" class="icon-20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="3.5" y="4" width="17" height="16" rx="2" stroke="currentColor" stroke-width="1.5"></rect>
                        <path d="M9 4V20" stroke="currentColor" stroke-width="1.5"></path>
                        <path d="M12.5 9H17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                        <path d="M12.5 15H17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                    </svg>
                </i>
            </div>
        </div>
        <div class="sidebar-body pt-0 data-scrollbar">
            <div class="sidebar-list">
                <!-- Sidebar Menu Start -->
                <ul class="navbar-nav iq-main-menu" id="sidebar-menu">
                    @php
                      $subscriptionManager = app(\Modules\Tiers\Services\SubscriptionManager::class);
                      $tierResolver = class_exists(\Modules\Tiers\Services\TierResolver::class)
                        ? app(\Modules\Tiers\Services\TierResolver::class)
                        : null;
                      $navTier = auth()->check() ? $subscriptionManager->getUserTier(auth()->user()) : null;
                      $navMetaEnabled = $tierResolver
                        ? $tierResolver->featureEnabled($navTier, 'seo.custom_meta')
                        : false;
                      $navCustomDomainEnabled = auth()->check()
                        ? $subscriptionManager->featureEnabled(auth()->user(), 'domains.custom_domain')
                        : false;
                      $navHeaderEnabled = $tierResolver
                        ? $tierResolver->featureEnabled($navTier, 'design.header_image')
                        : false;
                      $navButtonEditorEnabled = $tierResolver
                        ? ($tierResolver->featureEnabled($navTier, 'design.link_styling')
                            || $tierResolver->featureEnabled($navTier, 'design.custom_colors'))
                        : false;
                      $navPartnerEnabled = (bool) config('partners.web_dashboard_enabled', false)
                        && auth()->check()
                        && class_exists(\Modules\Partners\Services\PartnerManager::class)
                        && app(\Modules\Partners\Services\PartnerManager::class)->isActivePartner(auth()->user());
                    @endphp
                    <li class="nav-item static-item">
                        <a class="nav-link static-item disabled" href="#" tabindex="-1">
                            <span class="default-icon">{{ $isAgencyAccount ? __('Global') : __('messages.Home') }}</span>
                            <span class="mini-icon">-</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('panelIndex') ? 'active' : '' }}" aria-current="page" href="{{ route('panelIndex') }}">
                            <i class="icon">
                                <svg width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="icon-20">
                                  <path fill-rule="evenodd" clip-rule="evenodd" d="M7.33049 2.00049H16.6695C20.0705 2.00049 21.9905 3.92949 22.0005 7.33049V16.6705C22.0005 20.0705 20.0705 22.0005 16.6695 22.0005H7.33049C3.92949 22.0005 2.00049 20.0705 2.00049 16.6705V7.33049C2.00049 3.92949 3.92949 2.00049 7.33049 2.00049ZM12.0495 17.8605C12.4805 17.8605 12.8395 17.5405 12.8795 17.1105V6.92049C12.9195 6.61049 12.7705 6.29949 12.5005 6.13049C12.2195 5.96049 11.8795 5.96049 11.6105 6.13049C11.3395 6.29949 11.1905 6.61049 11.2195 6.92049V17.1105C11.2705 17.5405 11.6295 17.8605 12.0495 17.8605ZM16.6505 17.8605C17.0705 17.8605 17.4295 17.5405 17.4805 17.1105V13.8305C17.5095 13.5095 17.3605 13.2105 17.0895 13.0405C16.8205 12.8705 16.4805 12.8705 16.2005 13.0405C15.9295 13.2105 15.7805 13.5095 15.8205 13.8305V17.1105C15.8605 17.5405 16.2195 17.8605 16.6505 17.8605ZM8.21949 17.1105C8.17949 17.5405 7.82049 17.8605 7.38949 17.8605C6.95949 17.8605 6.59949 17.5405 6.56049 17.1105V10.2005C6.53049 9.88949 6.67949 9.58049 6.95049 9.41049C7.21949 9.24049 7.56049 9.24049 7.83049 9.41049C8.09949 9.58049 8.25049 9.88949 8.21949 10.2005V17.1105Z" fill="currentColor"></path>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('messages.Dashboard') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::segment(2) == 'subscription' ? 'active' : ''}}" aria-current="page" href="{{ url('/dashboard/subscription') }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 2.25C6.615 2.25 2.25 6.615 2.25 12C2.25 17.385 6.615 21.75 12 21.75C17.385 21.75 21.75 17.385 21.75 12C21.75 6.615 17.385 2.25 12 2.25ZM12 19.875C7.542 19.875 4.125 16.458 4.125 12C4.125 7.542 7.542 4.125 12 4.125C16.458 4.125 19.875 7.542 19.875 12C19.875 16.458 16.458 19.875 12 19.875Z" fill="currentColor"/>
                                    <path d="M12.75 6.75H11.25V12.75L16.125 15.675L16.875 14.445L12.75 12.15V6.75Z" fill="currentColor"/>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('Subscription') }}</span>
                        </a>
                    </li>
                    @if($navPartnerEnabled)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('partner.dashboard') ? 'active' : '' }}" aria-current="page" href="{{ route('partner.dashboard') }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 4L18 7V11C18 15.4183 15.4183 18.5 12 20C8.58172 18.5 6 15.4183 6 11V7L12 4Z" stroke="currentColor" stroke-width="1.5"></path>
                                    <path d="M9.5 11.5L11.25 13.25L14.5 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('Partner') }}</span>
                        </a>
                    </li>
                    @endif
                    @if($isAgencyAccount)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('agency.hubs.index') ? 'active' : '' }}" aria-current="page" href="{{ route('agency.hubs.index') }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7 8C7 6.34315 8.34315 5 10 5H14C15.6569 5 17 6.34315 17 8V9H7V8Z" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M5 11.5C5 10.6716 5.67157 10 6.5 10H17.5C18.3284 10 19 10.6716 19 11.5V16C19 18.2091 17.2091 20 15 20H9C6.79086 20 5 18.2091 5 16V11.5Z" stroke="currentColor" stroke-width="1.5"/>
                                    <circle cx="9" cy="14.5" r="1" fill="currentColor"/>
                                    <circle cx="15" cy="14.5" r="1" fill="currentColor"/>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('Hubs') }}</span>
                        </a>
                    </li>
                    @endif
                    @includeIf('modules.CustomDomains.views.sidebar-entry')

                    @if($isAgencyAccount)
                    <li class="nav-item sidebar-zone-divider" aria-hidden="true">
                        <div class="sidebar-zone-rule"></div>
                    </li>
                    @includeIf('partials.agency-context-switcher', [
                        'isAgencyAccount' => $isAgencyAccount,
                        'agencyHubs' => $agencyHubs,
                        'agencySidebar' => $agencySidebar,
                        'agencySlots' => $agencySlots,
                    ])
                    @endif

                    <li class="nav-item sidebar-zone-divider" aria-hidden="true">
                        <div class="sidebar-zone-rule"></div>
                    </li>
                    <li class="nav-item static-item">
                        <a class="nav-link static-item disabled" href="#" tabindex="-1">
                            <span class="default-icon">{{ $isAgencyAccount ? __('Selected hub') : __('messages.Personalization') }}</span>
                            <span class="mini-icon">-</span>
                        </a>
                    </li>
                    <li class="nav-item sidebar-group-gap" aria-hidden="true"><div class="sidebar-zone-rule sidebar-zone-rule--short"></div></li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::segment(2) == 'header' ? 'active' : ''}}" href="{{ url('/studio/header') }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"></rect>
                                    <path d="M8.5 11.5C9.32843 11.5 10 10.8284 10 10C10 9.17157 9.32843 8.5 8.5 8.5C7.67157 8.5 7 9.17157 7 10C7 10.8284 7.67157 11.5 8.5 11.5Z" fill="currentColor"></path>
                                    <path d="M21 16L16.5 12.5L12 16.5L8 13L3 17.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('Sidebar: Header') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::segment(2) == 'theme' ? 'active' : ''}}" href="{{ url('/studio/theme') }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 3C7.02944 3 3 7.02944 3 12C3 16.9706 7.02944 21 12 21C12.9625 21 13.5 20.4418 13.5 19.6758C13.5 19.2996 13.4017 18.9709 13.2812 18.6667C13.1608 18.3624 13.0625 18.0338 13.0625 17.6576C13.0625 16.8916 13.6 16.3333 14.5625 16.3333H16.5C19.5376 16.3333 22 13.8709 22 10.8333C22 6.50603 17.5239 3 12 3Z" stroke="currentColor" stroke-width="1.5"></path>
                                    <circle cx="7.75" cy="11" r="1" fill="currentColor"></circle>
                                    <circle cx="10.75" cy="8" r="1" fill="currentColor"></circle>
                                    <circle cx="14.25" cy="8.5" r="1" fill="currentColor"></circle>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('Sidebar: Design') }}</span>
                        </a>
                    </li>
                    @if((bool) config('app.enable_button_editor', true))
                    <li class="nav-item">
                        <a class="nav-link {{ Request::segment(2) == 'button-editor' ? 'active' : ''}}" href="{{ url('/studio/button-editor') }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 8H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    <path d="M5 16H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                    <circle cx="9" cy="8" r="2" fill="currentColor"></circle>
                                    <circle cx="15" cy="16" r="2" fill="currentColor"></circle>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('Sidebar: Buttons') }}</span>
                            @if(!$navButtonEditorEnabled)
                                <span class="badge bg-secondary ms-2">{{ __('Locked') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif
                    <li class="nav-item sidebar-group-gap" aria-hidden="true"><div class="sidebar-zone-rule sidebar-zone-rule--short"></div></li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::segment(2) == 'add-link' ? 'active' : ''}}" aria-current="page" href="{{ route('showButtons', ['page_id' => $activeContextPageId]) }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M7.33 2H16.66C20.06 2 22 3.92 22 7.33V16.67C22 20.06 20.07 22 16.67 22H7.33C3.92 22 2 20.06 2 16.67V7.33C2 3.92 3.92 2 7.33 2ZM12.82 12.83H15.66C16.12 12.82 16.49 12.45 16.49 11.99C16.49 11.53 16.12 11.16 15.66 11.16H12.82V8.34C12.82 7.88 12.45 7.51 11.99 7.51C11.53 7.51 11.16 7.88 11.16 8.34V11.16H8.33C8.11 11.16 7.9 11.25 7.74 11.4C7.59 11.56 7.5 11.769 7.5 11.99C7.5 12.45 7.87 12.82 8.33 12.83H11.16V15.66C11.16 16.12 11.53 16.49 11.99 16.49C12.45 16.49 12.82 16.12 12.82 15.66V12.83Z" fill="currentColor"></path>
                                    <circle cx="18" cy="11.8999" r="1" fill="currentColor"></circle>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('messages.Add Link') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::segment(2) == 'links' ? 'active' : ''}}" href="{{ url('/studio/links') }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M4.54 2H7.92C9.33 2 10.46 3.15 10.46 4.561V7.97C10.46 9.39 9.33 10.53 7.92 10.53H4.54C3.14 10.53 2 9.39 2 7.97V4.561C2 3.15 3.14 2 4.54 2ZM4.54 13.4697H7.92C9.33 13.4697 10.46 14.6107 10.46 16.0307V19.4397C10.46 20.8497 9.33 21.9997 7.92 21.9997H4.54C3.14 21.9997 2 20.8497 2 19.4397V16.0307C2 14.6107 3.14 13.4697 4.54 13.4697ZM19.4601 2H16.0801C14.6701 2 13.5401 3.15 13.5401 4.561V7.97C13.5401 9.39 14.6701 10.53 16.0801 10.53H19.4601C20.8601 10.53 22.0001 9.39 22.0001 7.97V4.561C22.0001 3.15 20.8601 2 19.4601 2ZM16.0801 13.4697H19.4601C20.8601 13.4697 22.0001 14.6107 22.0001 16.0307V19.4397C22.0001 20.8497 20.8601 21.9997 19.4601 21.9997H16.0801C14.6701 21.9997 13.5401 20.8497 13.5401 19.4397V16.0307C13.5401 14.6107 14.6701 13.4697 16.0801 13.4697Z" fill="currentColor"></path>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('messages.Links') }}</span>
                        </a>
                    </li>
                    <li class="nav-item sidebar-group-gap" aria-hidden="true"><div class="sidebar-zone-rule sidebar-zone-rule--short"></div></li>
                    <li class="nav-item">
                        <a class="nav-link {{ in_array(Request::segment(2), ['page-settings', 'page', 'no_page_name', 'behavior'], true) ? 'active' : '' }}" href="{{ url('/studio/page-settings') }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.5"></rect>
                                    <path d="M7 9H17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                    <path d="M7 13H13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('Sidebar: Page Settings') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        @php
                            $domainRouteName = $isAgencyAccount ? 'domains.hub.page' : 'domains.page';
                            $domainUnlockedUrl = $isAgencyAccount ? route('domains.hub.page') : route('domains.page');
                            $domainNavLabel = 'Domain';
                        @endphp
                        <a class="nav-link {{ request()->routeIs($domainRouteName) ? 'active' : '' }}" href="{{ $domainUnlockedUrl }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 5H20V19H4V5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M7 12H17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <path d="M8 9H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <path d="M8 15H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                            </i>
                            <span class="item-name">{{ $domainNavLabel }}</span>
                            @if(!$navCustomDomainEnabled)
                                <span class="badge bg-secondary ms-2">{{ __('Locked') }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->is('dashboard/analytics') ? 'active' : '' }}" aria-current="page" href="{{ url('/dashboard/analytics') }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M5 3C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3H5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M7 14.5L10 11L13 14L17 9.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="7" cy="14.5" r="1" fill="currentColor"/>
                                    <circle cx="10" cy="11" r="1" fill="currentColor"/>
                                    <circle cx="13" cy="14" r="1" fill="currentColor"/>
                                    <circle cx="17" cy="9.5" r="1" fill="currentColor"/>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('Analytics') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('forms.dashboard*') ? 'active' : '' }}" aria-current="page" href="{{ route('forms.dashboard') }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect x="4" y="3" width="16" height="18" rx="2" stroke="currentColor" stroke-width="1.5"></rect>
                                    <path d="M8 8H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                    <path d="M8 12H16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                    <path d="M8 16H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('Forms') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::segment(2) == 'meta' ? 'active' : ''}}" href="{{ url('/studio/meta') }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 5.5C4 4.11929 5.11929 3 6.5 3H11L9 7L11 11H6.5C5.11929 11 4 9.88071 4 8.5V5.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M20 8.5C20 9.88071 18.8807 11 17.5 11H13L15 7L13 3H17.5C18.8807 3 20 4.11929 20 5.5V8.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M10 13L8 17L10 21H6.5C5.11929 21 4 19.8807 4 18.5V15.5C4 14.1193 5.11929 13 6.5 13H10Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M14 13L16 17L14 21H17.5C18.8807 21 20 19.8807 20 18.5V15.5C20 14.1193 18.8807 13 17.5 13H14Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('Sidebar: Meta Tag') }}</span>
                            @if(!$navMetaEnabled)
                                <span class="badge bg-secondary ms-2">{{ __('Locked') }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item sidebar-group-gap" aria-hidden="true"><div class="sidebar-zone-rule sidebar-zone-rule--short"></div></li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::segment(2) == 'legal' ? 'active' : ''}}" aria-current="page" href="{{ route('showLegal', ['page_id' => $activeContextPageId]) }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M8 3.5H13.5L18.5 8.5V19.5C18.5 20.3284 17.8284 21 17 21H8C7.17157 21 6.5 20.3284 6.5 19.5V5C6.5 4.17157 7.17157 3.5 8 3.5Z" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M13.5 3.5V8.5H18.5" stroke="currentColor" stroke-width="1.5"/>
                                    <path d="M9.5 12H15.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                    <path d="M9.5 15H15.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('Sidebar: Legal') }}</span>
                        </a>
                    </li>
                    @if(auth()->user()->role == 'admin')
                    <li class="nav-item sidebar-zone-divider" aria-hidden="true">
                        <div class="sidebar-zone-rule"></div>
                    </li>
                    <li class="nav-item static-item">
                        <a class="nav-link static-item disabled" href="#" tabindex="-1">
                            <span class="default-icon">{{ __('messages.Administration') }}</span>
                            <span class="mini-icon">-</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::segment(2) == 'users' ? 'active' : ''}}" href="{{ url('admin/users/all') }}">
                          <i class="bi bi-people-fill"></i>
                            <span class="item-name">{{ __('messages.Manage Users') }}</span>
                        </a>
                    </li>
                    @endif
                    <li class="nav-item sidebar-zone-divider" aria-hidden="true">
                        <div class="sidebar-zone-rule"></div>
                    </li>
                    <li class="nav-item static-item">
                        <a class="nav-link static-item disabled" href="#" tabindex="-1">
                            <span class="default-icon">{{ __('Help') }}</span>
                            <span class="mini-icon">-</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('help.*') ? 'active' : '' }}" href="{{ route('help.index') }}">
                            <i class="bi bi-life-preserver"></i>
                            <span class="item-name">{{ __('Help center') }}</span>
                        </a>
                    </li>
                    <li class="nav-item sidebar-zone-divider" aria-hidden="true">
                        <div class="sidebar-zone-rule"></div>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ Request::segment(2) == 'profile' ? 'active' : ''}}" href="{{ url('/studio/profile') }}">
                            <i class="icon">
                                <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M12 4.5C9.79086 4.5 8 6.29086 8 8.5C8 10.7091 9.79086 12.5 12 12.5C14.2091 12.5 16 10.7091 16 8.5C16 6.29086 14.2091 4.5 12 4.5Z" stroke="currentColor" stroke-width="1.5"></path>
                                    <path d="M4.75 19.5C5.83947 16.8848 8.60111 15 12 15C15.3989 15 18.1605 16.8848 19.25 19.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                </svg>
                            </i>
                            <span class="item-name">{{ __('messages.Settings') }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <form action="{{ route('logout') }}" method="post" class="d-block">
                            @csrf
                            <button type="submit" class="nav-link border-0 bg-transparent w-100 text-start">
                                <i class="icon">
                                    <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M14 7L19 12L14 17" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path d="M19 12H9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                        <path d="M10 4.75H7.75C6.50736 4.75 5.5 5.75736 5.5 7V17C5.5 18.2426 6.50736 19.25 7.75 19.25H10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                    </svg>
                                </i>
                                <span class="item-name">{{ __('messages.Logout') }}</span>
                            </button>
                        </form>
                    </li>
                </ul>
                <!-- Sidebar Menu End -->        </div>
        </div>
        <div class="sidebar-footer"></div>
    </aside>    <main class="main-content">
      <div class="position-relative iq-banner">
        <!--Nav Start-->
        <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar dashboard-topbar">
          <div class="container-fluid navbar-inner">
            <a href="{{ route('panelIndex') }}" class="navbar-brand">
                
                <!--Logo start-->
                <div class="logo-main">
                  @if(file_exists(base_path("assets/wayvio/images/").findFile('avatar')))
                  <div class="logo-normal">
                    <img class="img logo" src="{{ asset('assets/wayvio/images/'.findFile('avatar')) }}" style="width:auto;height:30px;">
                </div>
                <div class="logo-mini">
                  <img class="img logo" src="{{ asset('assets/wayvio/images/'.findFile('avatar')) }}" style="width:auto;height:30px;">
                </div>
                  @else
                  <div class="logo-normal">
                    <picture>
                      <source media="(max-width: 991.98px)" srcset="{{ asset('assets/wayvio/images/logo-white.svg') }}">
                      <img class="img logo" type="image/svg+xml" src="{{ asset('assets/wayvio/images/logo.svg') }}" width="40px" height="40px">
                    </picture>
                </div>
                <div class="logo-mini">
                  <picture>
                    <source media="(max-width: 991.98px)" srcset="{{ asset('assets/wayvio/images/logo-white.svg') }}">
                    <img class="img logo" type="image/svg+xml" src="{{ asset('assets/wayvio/images/logo.svg') }}" width="40px" height="40px">
                  </picture>
                </div>
                  @endif
                  </div>
                <!--logo End-->
                
                
                <h4 class="logo-title">{{ strtoupper((string) config('app.name')) }}</h4>
            </a>
            {{-- <div class="input-group search-input">
              <span class="input-group-text" id="search-input">
                <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></circle>
                    <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
              </span>
              <input type="search" class="form-control" placeholder="Search...">
            </div> --}}
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
              <span class="navbar-toggler-icon">
                  <span class="mt-2 navbar-toggler-bar bar1"></span>
                  <span class="navbar-toggler-bar bar2"></span>
                  <span class="navbar-toggler-bar bar3"></span>
                </span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
              <div class="mobile-sidebar-menu d-lg-none" id="mobileSidebarMenu"></div>
              <ul class="mb-2 navbar-nav ms-auto align-items-center navbar-list mb-lg-0 d-none d-lg-flex">
                <li class="me-0 me-lg-2">
                  <div class="d-flex flex-column align-items-start view-page-actions-wrap">
                    <div class="dropdown d-flex flex-row align-items-center view-share-actions">
                      <a target="_blank" href="{{ $profileUrl }}">
                        <button style="border-bottom-right-radius:0;border-top-right-radius:0;" type="button" class="btn btn-primary btn-sm pe-2 view-page-btn">{{__('messages.View Page')}}</button>
                      </a>
                      <button style="border-bottom-left-radius:0;border-top-left-radius:0;" class="btn btn-primary btn-sm dropdown-toggle ms-auto px-1 view-page-share" type="button" id="dropdownMenuButtonSM" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="btn-seg-ico bi bi-share-fill"></i>
                      </button>
                      <ul class="dropdown-menu" aria-labelledby="dropdownMenuButtonSM">
                        <li><h6 class="dropdown-header">{{__('messages.Share your profile:')}}</h6></li>
                        @if(env('SUPPORTED_DOMAINS') !== '' and env('SUPPORTED_DOMAINS') !== null)
                        @php $sDomains = str_replace(' ', '', env('SUPPORTED_DOMAINS')); $sDomains = explode(',', $sDomains); @endphp
                          @foreach ($sDomains as $myvar)
                              <li>
                                  <a class="dropdown-item share-button" style="cursor:pointer!important;" data-share="{{'https://'.$myvar.'/'.(in_array($usrhandl,$reservedSlugs)?'p/'.$usrhandl:$usrhandl)}}">
                                      <i class="bi bi-files"></i> {{ $myvar }}
                                  </a>
                              </li>
                          @endforeach         
                        @else
                        <li><a class="dropdown-item share-button" style="cursor:pointer!important;" data-share="{{ $profileUrl }}"><i class="bi bi-files"></i> {{ str_replace(['http://', 'https://'], '', parse_url($profileUrl, PHP_URL_HOST) ?: url('')) }}                      </a></li>
                        @endif
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" data-bs-toggle="modal" style="cursor:pointer!important;" data-bs-target="#staticBackdrop"><i class="bi bi-qr-code-scan"></i> {{__('messages.QR Code')}}</a></li>
                      </ul>
                    </div>
                    @if(!$activeEditorPublished)
                      <div class="small text-warning mt-1 px-1">{{ __('messages.hub.publish.only_visible_to_you') }}</div>
                      <div class="small text-warning px-1">
                        {!! __('messages.hub.publish.publish_here_hint', ['link' => '<a href="'.url('/studio/page-settings').'" class="text-warning text-decoration-underline">'.__('messages.hub.publish.here_link_label').'</a>']) !!}
                      </div>
                    @endif
                  </div>
                </li>
              </ul>
            </div>
          </div>
        </nav>          <!-- Nav Header Component Start -->
          <div class="iq-navbar-header header-block mb-2">
              <div style="z-index:0!important;" class="iq-header-img">
                @php
                  $brandingHeaderImg = config('branding.dashboard.header_image');
                  $headerImage = null;
                  if ($brandingHeaderImg) {
                      if (preg_match('/^https?:\\/\\//i', $brandingHeaderImg)) {
                          $headerImage = $brandingHeaderImg;
                      } else {
                          $path = base_path($brandingHeaderImg);
                          $headerImage = file_exists($path) ? url($brandingHeaderImg) : null;
                      }
                  }
                  if (!$headerImage && file_exists(base_path("assets/dashboard-themes/header.png"))) {
                      $headerImage = asset('assets/dashboard-themes/header.png');
                  }
                  if (!$headerImage) {
                      $headerImage = asset('assets/images/dashboard/top-header-overlay.png');
                  }
                @endphp
                  <img src="{{$headerImage}}" draggable="false" alt="header" class="img-fluid w-100 h-100 animated-scaleX">
                  <img src="{{$headerImage}}" draggable="false" alt="header" class="theme-color-purple-img img-fluid w-100 h-100 animated-scaleX">
                  <img src="{{$headerImage}}" draggable="false" alt="header" class="theme-color-blue-img img-fluid w-100 h-100 animated-scaleX">
                  <img src="{{$headerImage}}" draggable="false" alt="header" class="theme-color-green-img img-fluid w-100 h-100 animated-scaleX">
                  <img src="{{$headerImage}}" draggable="false" alt="header" class="theme-color-yellow-img img-fluid w-100 h-100 animated-scaleX">
                  <img src="{{$headerImage}}" draggable="false" alt="header" class="theme-color-pink-img img-fluid w-100 h-100 animated-scaleX">
              </div>
              <div class="hero-mobile-actions d-flex flex-wrap d-lg-none">
                <a target="_blank" href="{{ $profileUrl }}" class="btn btn-primary btn-sm view-page-btn">{{__('messages.View Page')}}</a>
                <button type="button" class="btn btn-primary btn-sm view-page-share" data-bs-toggle="modal" data-bs-target="#staticBackdrop">
                  <i class="bi bi-qr-code-scan me-1"></i>{{__('messages.QR Code')}}
                </button>
                @if(!$activeEditorPublished)
                  <div class="hero-mobile-publish-note small text-warning mt-1">{{ __('messages.hub.publish.only_visible_to_you') }}</div>
                  <div class="hero-mobile-publish-note small text-warning">
                    {!! __('messages.hub.publish.publish_here_hint', ['link' => '<a href="'.url('/studio/page-settings').'" class="text-warning text-decoration-underline">'.__('messages.hub.publish.here_link_label').'</a>']) !!}
                  </div>
                @endif
              </div>
          </div>          <!-- Nav Header Component End -->
        <!--Nav End-->

      @yield('content')

      <!-- Footer Section Start -->
      @php
        $publicLegalLinks = legalDocumentLinks((string) app()->getLocale());
        $legalLocale = $publicLegalLinks['locale'];
        $footerIsEn = $legalLocale === 'en';
        $footerLabels = [
          'agb' => $footerIsEn ? 'Terms' : 'AGB',
          'avv' => $footerIsEn ? 'DPA' : 'AVV',
          'privacy' => $footerIsEn ? 'Privacy' : 'Privatsphäre',
          'imprint' => $footerIsEn ? 'Imprint' : 'Impressum',
          'contact' => $footerIsEn ? 'Contact' : 'Kontakt',
          'help' => $footerIsEn ? 'Help Center' : 'Hilfecenter',
          'license' => $footerIsEn ? 'License' : 'Lizenz',
        ];
        $helpCenterUrl = $footerIsEn
          ? (Route::has('help.en.index') ? route('help.en.index') : '')
          : (Route::has('help.index') ? route('help.index') : '');
        $contactUrl = (string) ($publicLegalLinks['imprint'] ?? '');
      @endphp
      <footer class="footer">
          <div class="footer-body">
              <ul class="left-panel list-inline mb-0 p-0">
                @if($publicLegalLinks['agb'] !== '')<li class="list-inline-item"><a class="list-inline-item" href="{{ $publicLegalLinks['agb'] }}">{{ $footerLabels['agb'] }}</a></li>@endif
                @if($publicLegalLinks['avv'] !== '')<li class="list-inline-item"><a class="list-inline-item" href="{{ $publicLegalLinks['avv'] }}">{{ $footerLabels['avv'] }}</a></li>@endif
                @if($publicLegalLinks['privacy'] !== '')<li class="list-inline-item"><a class="list-inline-item" href="{{ $publicLegalLinks['privacy'] }}">{{ $footerLabels['privacy'] }}</a></li>@endif
                @if($publicLegalLinks['imprint'] !== '')<li class="list-inline-item"><a class="list-inline-item" href="{{ $publicLegalLinks['imprint'] }}">{{ $footerLabels['imprint'] }}</a></li>@endif
                @if($contactUrl !== '')<li class="list-inline-item"><a class="list-inline-item" href="{{ $contactUrl }}">{{ $footerLabels['contact'] }}</a></li>@endif
                @if($helpCenterUrl !== '')<li class="list-inline-item"><a class="list-inline-item" href="{{ $helpCenterUrl }}">{{ $footerLabels['help'] }}</a></li>@endif
                <li class="list-inline-item"><a class="list-inline-item" href="https://github.com/Luqqua/wayvio" target="_blank" rel="noreferrer">{{ $footerLabels['license'] }}</a></li>
              </ul>
              <div class="right-panel">
                &copy; @php echo date('Y'); @endphp {{ strtoupper((string) config('app.name')) }}
              </div>
          </div>
      </footer>
      <!-- Footer Section End -->    </main>

    <!-- offcanvas start -->
    <div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasExample" data-bs-scroll="true" data-bs-backdrop="true" aria-labelledby="offcanvasExampleLabel" style="display:none !important;" aria-hidden="true">
      <div class="offcanvas-header">
        <div class="d-flex align-items-center">
          <h3 class="offcanvas-title me-3" id="offcanvasExampleLabel">{{__('messages.Settings')}}</h3>
        </div>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body data-scrollbar">
        <div class="row">
          <div class="col-lg-12">
             <h5 class="mb-3">{{__('messages.Scheme')}}</h5>
            <div class="d-grid gap-3 grid-cols-3 mb-4">
              <div class="btn btn-border" data-setting="color-mode" data-name="color" data-value="auto">
                  <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path fill="currentColor" d="M7,2V13H10V22L17,10H13L17,2H7Z" />
                  </svg>
                <span class="ms-2 "> {{__('messages.Auto')}} </span>
              </div>
    
               <div class="btn btn-border" data-setting="color-mode" data-name="color" data-value="dark">
                 <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path fill="currentColor" d="M9,2C7.95,2 6.95,2.16 6,2.46C10.06,3.73 13,7.5 13,12C13,16.5 10.06,20.27 6,21.54C6.95,21.84 7.95,22 9,22A10,10 0 0,0 19,12A10,10 0 0,0 9,2Z" />
                  </svg>
                <span class="ms-2 "> {{__('messages.Dark')}}  </span>
              </div>
               <div class="btn btn-border active" data-setting="color-mode" data-name="color" data-value="light">
                  <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill="currentColor" d="M12,8A4,4 0 0,0 8,12A4,4 0 0,0 12,16A4,4 0 0,0 16,12A4,4 0 0,0 12,8M12,18A6,6 0 0,1 6,12A6,6 0 0,1 12,6A6,6 0 0,1 18,12A6,6 0 0,1 12,18M20,8.69V4H15.31L12,0.69L8.69,4H4V8.69L0.69,12L4,15.31V20H8.69L12,23.31L15.31,20H20V15.31L23.31,12L20,8.69Z" />
                </svg>
                <span class="ms-2 "> {{__('messages.Light')}}</span>
              </div>
            </div>
            <hr class="hr-horizontal"> 
            <div class="d-flex align-items-center justify-content-between">
            <h5 class="mt-4 mb-3">{{__('messages.Color Customizer')}}</h5>
            <button class="btn btn-transparent p-0 border-0" data-value="theme-color-default" data-info="#079aa2" data-setting="color-mode1" data-name="color" data-bs-toggle="tooltip" data-bs-placement="top" title="" data-bs-original-title="Default">
              <svg class="icon-18" width="18"  viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M21.4799 12.2424C21.7557 12.2326 21.9886 12.4482 21.9852 12.7241C21.9595 14.8075 21.2975 16.8392 20.0799 18.5506C18.7652 20.3986 16.8748 21.7718 14.6964 22.4612C12.518 23.1505 10.1711 23.1183 8.01299 22.3694C5.85488 21.6205 4.00382 20.196 2.74167 18.3126C1.47952 16.4293 0.875433 14.1905 1.02139 11.937C1.16734 9.68346 2.05534 7.53876 3.55018 5.82945C5.04501 4.12014 7.06478 2.93987 9.30193 2.46835C11.5391 1.99683 13.8711 2.2599 15.9428 3.2175L16.7558 1.91838C16.9822 1.55679 17.5282 1.62643 17.6565 2.03324L18.8635 5.85986C18.945 6.11851 18.8055 6.39505 18.549 6.48314L14.6564 7.82007C14.2314 7.96603 13.8445 7.52091 14.0483 7.12042L14.6828 5.87345C13.1977 5.18699 11.526 4.9984 9.92231 5.33642C8.31859 5.67443 6.8707 6.52052 5.79911 7.74586C4.72753 8.97119 4.09095 10.5086 3.98633 12.1241C3.8817 13.7395 4.31474 15.3445 5.21953 16.6945C6.12431 18.0446 7.45126 19.0658 8.99832 19.6027C10.5454 20.1395 12.2278 20.1626 13.7894 19.6684C15.351 19.1743 16.7062 18.1899 17.6486 16.8652C18.4937 15.6773 18.9654 14.2742 19.0113 12.8307C19.0201 12.5545 19.2341 12.3223 19.5103 12.3125L21.4799 12.2424Z" fill="#31BAF1"/>
                <path d="M20.0941 18.5594C21.3117 16.848 21.9736 14.8163 21.9993 12.7329C22.0027 12.4569 21.7699 12.2413 21.4941 12.2512L19.5244 12.3213C19.2482 12.3311 19.0342 12.5633 19.0254 12.8395C18.9796 14.283 18.5078 15.6861 17.6628 16.8739C16.7203 18.1986 15.3651 19.183 13.8035 19.6772C12.2419 20.1714 10.5595 20.1483 9.01246 19.6114C7.4654 19.0746 6.13845 18.0534 5.23367 16.7033C4.66562 15.8557 4.28352 14.9076 4.10367 13.9196C4.00935 18.0934 6.49194 21.37 10.008 22.6416C10.697 22.8908 11.4336 22.9852 12.1652 22.9465C13.075 22.8983 13.8508 22.742 14.7105 22.4699C16.8889 21.7805 18.7794 20.4073 20.0941 18.5594Z" fill="#0169CA"/>
              </svg>
            </button>
            </div>
            <div class="grid-cols-5 mb-4 d-grid gap-x-2">
              <div class="btn btn-border bg-transparent"  data-value="theme-color-blue"   data-info="#573BFF" data-setting="color-mode1" data-name="color" data-bs-toggle="tooltip" data-bs-placement="top" title="" data-bs-original-title="Theme-1">
              <svg  class="customizer-btn icon-32" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" > <circle cx="12" cy="12" r="10" fill="#00C3F9" /> <path d="M2,12 a1,1 1 1,0 20,0" fill="#573BFF" /></svg>
              </div>
              <div class="btn btn-border bg-transparent" data-value="theme-color-gray" data-info="#FD8D00" data-setting="color-mode1" data-name="color" data-bs-toggle="tooltip" data-bs-placement="top" title="" data-bs-original-title="Theme-2">
              <svg  class="customizer-btn icon-32" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" > <circle cx="12" cy="12" r="10" fill="#91969E" /> <path d="M2,12 a1,1 1 1,0 20,0" fill="#FD8D00" /></svg>
              </div>
              <div class="btn btn-border bg-transparent"  data-value="theme-color-red" data-info="#366AF0" data-setting="color-mode1" data-name="color" data-bs-toggle="tooltip" data-bs-placement="top" title="" data-bs-original-title="Theme-3">
              <svg  class="customizer-btn icon-32" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" > <circle cx="12" cy="12" r="10" fill="#DB5363" /> <path d="M2,12 a1,1 1 1,0 20,0" fill="#366AF0" /></svg>
              </div>
              <div class="btn btn-border bg-transparent"  data-value="theme-color-yellow" data-info="#6410F1" data-setting="color-mode1" data-name="color" data-bs-toggle="tooltip" data-bs-placement="top" title="" data-bs-original-title="Theme-4">
              <svg  class="customizer-btn icon-32" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" > <circle cx="12" cy="12" r="10" fill="#EA6A12" /> <path d="M2,12 a1,1 1 1,0 20,0" fill="#6410F1" /></svg>
              </div>
              <div class="btn btn-border bg-transparent"  data-value="theme-color-pink" data-info="#25C799" data-setting="color-mode1" data-name="color" data-bs-toggle="tooltip" data-bs-placement="top" title="" data-bs-original-title="Theme-5">
              <svg  class="customizer-btn icon-32" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="32" > <circle cx="12" cy="12" r="10" fill="#E586B3" /> <path d="M2,12 a1,1 1 1,0 20,0" fill="#25C799" /></svg>
              </div>
              
            </div>
            {{-- <hr class="hr-horizontal">
            <h5 class="mb-3 mt-4">Scheme Direction</h5>
            <div class="d-grid gap-3 grid-cols-2 mb-4">
              <div class="text-center">
                <img src="{{asset('assets/images/settings/dark/01.png')}}" alt="ltr" class="mode dark-img img-fluid btn-border p-0 flex-column active mb-2" data-setting="dir-mode" data-name="dir" data-value="ltr">
                <img src="{{asset('assets/images/settings/light/01.png')}}" alt="ltr" class="mode light-img img-fluid btn-border p-0 flex-column active mb-2" data-setting="dir-mode" data-name="dir" data-value="ltr">
                <span class=" mt-2"> LTR </span>
              </div>
               <div class="text-center">
                 <img src="{{asset('assets/images/settings/dark/02.png')}}" alt="" class="mode dark-img img-fluid btn-border p-0 flex-column mb-2" data-setting="dir-mode" data-name="dir" data-value="rtl">
                  <img src="{{asset('assets/images/settings/light/02.png')}}" alt="" class="mode light-img img-fluid btn-border p-0 flex-column mb-2" data-setting="dir-mode" data-name="dir" data-value="rtl">
                  <span class="mt-2 "> RTL  </span>
              </div>
            </div> --}}
            <hr class="hr-horizontal">
            <h5 class="mt-4 mb-3">{{__('messages.Sidebar Color')}}</h5>
            <div class="d-grid gap-3 grid-cols-2 mb-4">
              <div class="btn btn-border d-block" data-setting="sidebar" data-name="sidebar-color" data-value="sidebar-white">
                <span class=""> {{__('messages.Default')}} </span>
              </div>
              <div class="btn btn-border d-block" data-setting="sidebar" data-name="sidebar-color" data-value="sidebar-dark">
                <span class=""> {{__('messages.Dark')}} </span>
              </div>
              <div class="btn btn-border d-block" data-setting="sidebar" data-name="sidebar-color" data-value="sidebar-color">
                <span class=""> {{__('messages.Color')}} </span>
              </div>
              
              <div class="btn btn-border d-block" data-setting="sidebar" data-name="sidebar-color" data-value="sidebar-transparent">
                <span class=""> {{__('messages.Transparent')}} </span>
              </div>
            </div>
            <hr class="hr-horizontal">
            <h5 class="mt-4 mb-3">{{__('messages.Sidebar Types')}}</h5>
            <div class="d-grid gap-3 grid-cols-3 mb-4">
              <div class="text-center">
                <img src="{{asset('assets/images/settings/dark/03.png')}}" alt="mini" class="mode dark-img img-fluid btn-border p-0 flex-column mb-2" data-setting="sidebar" data-name="sidebar-type" data-value="sidebar-mini">
                <img src="{{asset('assets/images/settings/light/03.png')}}" alt="mini" class="mode light-img img-fluid btn-border p-0 flex-column mb-2" data-setting="sidebar" data-name="sidebar-type" data-value="sidebar-mini">
                <span class="mt-2">{{__('messages.Mini')}}</span>
              </div>
              <div class="text-center">
               <img src="{{asset('assets/images/settings/dark/04.png')}}" alt="hover" class="mode dark-img img-fluid btn-border p-0 flex-column mb-2" data-setting="sidebar" data-name="sidebar-type" data-value="sidebar-hover" data-extra-value="sidebar-mini">
               <img src="{{asset('assets/images/settings/light/04.png')}}" alt="hover" class="mode light-img img-fluid btn-border p-0 flex-column mb-2" data-setting="sidebar" data-name="sidebar-type" data-value="sidebar-hover" data-extra-value="sidebar-mini">
                <span class="mt-2">{{__('messages.Hover')}}</span>
              </div>
              <div class="text-center">
                 <img src="{{asset('assets/images/settings/dark/05.png')}}" alt="boxed" class="mode dark-img img-fluid btn-border p-0 flex-column mb-2" data-setting="sidebar" data-name="sidebar-type" data-value="sidebar-boxed">
                 <img src="{{asset('assets/images/settings/light/05.png')}}" alt="boxed" class="mode light-img img-fluid btn-border p-0 flex-column mb-2" data-setting="sidebar" data-name="sidebar-type" data-value="sidebar-boxed">
                <span class="mt-2">{{__('messages.Boxed')}}</span>
              </div>
            </div>
            <hr class="hr-horizontal">
            <h5 class="mt-4 mb-3">{{__('messages.Sidebar Active Style')}}</h5>
            <div class="d-grid gap-3 grid-cols-2 mb-4">
              <div class="text-center">
                <img src="{{asset('assets/images/settings/dark/06.png')}}" alt="rounded-one-side" class="mode dark-img img-fluid btn-border p-0 flex-column mb-2" data-setting="sidebar" data-name="sidebar-item" data-value="navs-rounded">
                <img src="{{asset('assets/images/settings/light/06.png')}}" alt="rounded-one-side" class="mode light-img img-fluid btn-border p-0 flex-column mb-2" data-setting="sidebar" data-name="sidebar-item" data-value="navs-rounded">
                <span class="mt-2">{{__('messages.Rounded One Side')}}</span>
              </div>
              <div class="text-center">
                <img src="{{asset('assets/images/settings/dark/07.png')}}" alt="rounded-all" class="mode dark-img img-fluid btn-border p-0 flex-column active mb-2" data-setting="sidebar" data-name="sidebar-item" data-value="navs-rounded-all">
                <img src="{{asset('assets/images/settings/light/07.png')}}" alt="rounded-all" class="mode light-img img-fluid btn-border p-0 flex-column active mb-2" data-setting="sidebar" data-name="sidebar-item" data-value="navs-rounded-all">
                <span class="mt-2">{{__('messages.Rounded All')}}</span>
              </div>
              <div class="text-center">
                 <img src="{{asset('assets/images/settings/dark/08.png')}}" alt="pill-one-side" class="mode dark-img img-fluid btn-border p-0 flex-column mb-2" data-setting="sidebar" data-name="sidebar-item" data-value="navs-pill">
                 <img src="{{asset('assets/images/settings/light/09.png')}}" alt="pill-one-side" class="mode light-img img-fluid btn-border p-0 flex-column mb-2" data-setting="sidebar" data-name="sidebar-item" data-value="navs-pill">
                <span class="mt-2">{{__('messages.Pill One Side')}}</span>
              </div>
              <div class="text-center">
                 <img src="{{asset('assets/images/settings/dark/09.png')}}" alt="pill-all" class="mode dark-img img-fluid btn-border p-0 flex-column" data-setting="sidebar" data-name="sidebar-item" data-value="navs-pill-all">
                 <img src="{{asset('assets/images/settings/light/08.png')}}" alt="pill-all" class="mode light-img img-fluid btn-border p-0 flex-column" data-setting="sidebar" data-name="sidebar-item" data-value="navs-pill-all">
                <span class="mt-2">{{__('messages.Pill All')}}</span>
              </div>
            </div>
            <hr class="hr-horizontal">
            </div>
          </div>
        </div>
      </div>
    </div>

      <div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
      <div class="modal-dialog">
          <div class="modal-content">
              <div class="modal-header">
                  <h5 class="modal-title" id="staticBackdropLabel">{{__('messages.Scan QR Code')}}</h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              @php
              try {
                $redirectURL = url('').'/'.'u/'.Auth::user()->id;

                $argValues = config('advanced-config.qr_code_gradient') ?? [0, 0, 0, 0, 0, 0, 'diagonal'];
                list($arg1, $arg2, $arg3, $arg4, $arg5, $arg6, $arg7) = $argValues;

                if (extension_loaded('imagick')) {
                  $imgSrc = QrCode::format('png')->gradient($arg1, $arg2, $arg3, $arg4, $arg5, $arg6, $arg7)->eye('circle')->style('round')->size(1000)->generate($redirectURL);
                  $imgSrc = base64_encode($imgSrc);
                  $imgSrc = 'data:image/png;base64,' . $imgSrc;
                  $imgType = 'png';
                } else {
                  $imgSrc = QrCode::gradient($arg1, $arg2, $arg3, $arg4, $arg5, $arg6, $arg7)->eye('circle')->style('round')->size(1000)->generate($redirectURL);
                  $imgSrc = base64_encode($imgSrc);
                  $imgSrc = 'data:image/svg+xml;base64,' . $imgSrc;
                  $imgType = 'svg';
                }

              } catch(exception $e) {
                $imgSrc = url('/assets/wayvio/images/themes/no-preview.png');
                $imgType = NULL;
              }
              @endphp
              <div class="modal-body">
                <div class="bd-example">
                  <img id="generatedImage" draggable="false" src="@php if(isset($imgSrc)){echo $imgSrc;} @endphp" style="width:100%;height:auto;" class="bd-placeholder-img img-thumbnail">
              </div>
              </div>
              <div class="modal-footer">
                @if($imgType == 'png')
                  <button type="button" class="btn btn-info" id="downloadButton">{{__('messages.Download')}}</button>
                @endif
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{__('messages.Close')}}</button>
              </div>
          </div>
      </div>
      </div>

      <script>
        document.addEventListener("DOMContentLoaded", function() {
            var downloadButton = document.getElementById("downloadButton");
            var generatedImage = document.getElementById("generatedImage");
            // MODULE: Guard against missing download button to avoid JS errors on non-preview pages
            if (downloadButton && generatedImage) {
                downloadButton.addEventListener("click", function() {
                    var format = generatedImage.getAttribute("data-format") || "png";
                    var downloadLink = document.createElement("a");
                    downloadLink.href = generatedImage.src;
                    downloadLink.download = "generated_image." + format;
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                    document.body.removeChild(downloadLink);
                });
            }
        });
        </script>

    <!-- Library Bundle Script -->
    <script src="{{asset('assets/js/core/libs.min.js')}}"></script>
    
    <!-- External Library Bundle Script -->
    <script src="{{asset('assets/js/core/external.min.js')}}"></script>
    
    <!-- Widgetchart Script -->
    <script src="{{asset('assets/js/charts/widgetcharts.js')}}"></script>
    
    <!-- mapchart Script -->
    <script src="{{asset('assets/js/charts/vectore-chart.js')}}"></script>
    <script src="{{asset('assets/js/charts/dashboard.js')}}" ></script>
    
    <!-- fslightbox Script -->
    <script src="{{asset('assets/js/plugins/fslightbox.js')}}"></script>
    
    <!-- Settings Script -->
    <script>
      window.LS_BRANDING = {
        sidebar: {
          color_class: @json($sidebarColorClass),
          type_classes: @json(array_values(is_array($sidebarTypeClasses) ? $sidebarTypeClasses : [$sidebarTypeClasses])),
          item_class: @json($sidebarItemClass),
        },
        clearPrefs: @json($clearClientPrefs),
      };
      if (window.LS_BRANDING.clearPrefs) {
        ['sidebar','sidebarType','sidebar-style','colorcustom-mode','colorcustominfo-mode','colorcustomchart-mode'].forEach((key) => {
          try { localStorage.removeItem(key); } catch (e) {}
        });
      }
    </script>
    <script src="{{asset('assets/js/plugins/setting.js')}}"></script>
    <script>
      (function() {
        const cfg = (window.LS_BRANDING && window.LS_BRANDING.sidebar) || {};
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) return;
        const removables = ['sidebar-white','sidebar-dark','sidebar-color','sidebar-transparent','sidebar-mini','sidebar-hover','sidebar-boxed','navs-rounded','navs-rounded-all','navs-pill','navs-pill-all','sidebar-base'];
        removables.forEach(cls => sidebar.classList.remove(cls));
        if (cfg.color_class) sidebar.classList.add(cfg.color_class);
        if (Array.isArray(cfg.type_classes)) { cfg.type_classes.filter(Boolean).forEach(c => sidebar.classList.add(c)); }
        if (cfg.item_class) sidebar.classList.add(cfg.item_class);
      })();
    </script>
    
    <!-- Slider-tab Script -->
    <script src="{{asset('assets/js/plugins/slider-tabs.js')}}"></script>
    
    <!-- Form Wizard Script -->
    <script src="{{asset('assets/js/plugins/form-wizard.js')}}"></script>
    
    <!-- AOS Animation Plugin-->
    <script src="{{asset('assets/vendor/aos/dist/aos.js')}}"></script>
    
    <!-- App Script -->
    <script src="{{asset('assets/js/hope-ui.js')}}" defer></script>
    
    <!-- Flatpickr Script -->
    <script src="{{asset('assets/vendor/flatpickr/dist/flatpickr.min.js')}}"></script>
    <script src="{{asset('assets/js/plugins/flatpickr.js')}}" defer></script>
    
    <script src="{{asset('assets/js/plugins/prism.mini.js')}}"></script>

    <script>
      (function () {
        document.addEventListener('DOMContentLoaded', function () {
          const sourceMenu = document.querySelector('aside.sidebar #sidebar-menu');
          const mobileMenu = document.getElementById('mobileSidebarMenu');
          const collapseEl = document.getElementById('navbarSupportedContent');
          const banner = document.querySelector('.iq-banner');
          const topbar = document.querySelector('.dashboard-topbar');
          const menuToggler = document.querySelector('.dashboard-topbar .navbar-toggler');
          const heroMobileActions = document.querySelector('.iq-banner .hero-mobile-actions');
          const footer = document.querySelector('main.main-content footer.footer');
          if (!sourceMenu || !mobileMenu || !collapseEl) {
            return;
          }

          const clonedMenu = sourceMenu.cloneNode(true);
          clonedMenu.removeAttribute('id');
          mobileMenu.innerHTML = '';
          mobileMenu.appendChild(clonedMenu);

          const setMobileMenuOpenState = function (isOpen) {
            if (!banner) {
              return;
            }
            banner.classList.toggle('mobile-menu-open', !!isOpen && window.innerWidth < 992);
          };

          const updateMobileMenuHeight = function () {
            if (!topbar || window.innerWidth >= 992) {
              collapseEl.style.minHeight = '';
              setMobileMenuOpenState(false);
              if (heroMobileActions) {
                heroMobileActions.style.removeProperty('right');
              }
              return;
            }

            const topbarBottomDoc = topbar.getBoundingClientRect().bottom + window.scrollY;
            let minHeight = window.innerHeight - topbar.offsetHeight;
            if (footer) {
              const footerTopDoc = footer.getBoundingClientRect().top + window.scrollY;
              if (footerTopDoc > topbarBottomDoc) {
                minHeight = Math.max(minHeight, footerTopDoc - topbarBottomDoc);
              }
            }
            collapseEl.style.minHeight = `${Math.round(minHeight)}px`;
          };

          const syncHeroMobileActionsOffset = function () {
            if (!heroMobileActions || !menuToggler || window.innerWidth >= 992) {
              if (heroMobileActions) {
                heroMobileActions.style.removeProperty('right');
              }
              return;
            }

            const bannerRect = banner ? banner.getBoundingClientRect() : null;
            const togglerRect = menuToggler.getBoundingClientRect();
            const rightInset = bannerRect
              ? Math.max(0, bannerRect.right - togglerRect.right)
              : Math.max(0, window.innerWidth - togglerRect.right);
            heroMobileActions.style.right = `${rightInset.toFixed(2)}px`;
          };

          collapseEl.addEventListener('show.bs.collapse', function () {
            setMobileMenuOpenState(true);
            updateMobileMenuHeight();
          });

          collapseEl.addEventListener('hidden.bs.collapse', function () {
            setMobileMenuOpenState(false);
          });

          let collapseInstance = null;
          if (window.bootstrap && typeof window.bootstrap.Collapse !== 'undefined') {
            const collapseApi = window.bootstrap.Collapse;
            if (typeof collapseApi.getOrCreateInstance === 'function') {
              collapseInstance = collapseApi.getOrCreateInstance(collapseEl, { toggle: false });
            } else if (typeof collapseApi.getInstance === 'function') {
              collapseInstance = collapseApi.getInstance(collapseEl);
              if (!collapseInstance && typeof collapseApi === 'function') {
                collapseInstance = new collapseApi(collapseEl, { toggle: false });
              }
            } else if (typeof collapseApi === 'function') {
              collapseInstance = new collapseApi(collapseEl, { toggle: false });
            }
          }

          mobileMenu.querySelectorAll('a.nav-link, button.nav-link').forEach(function (menuControl) {
            menuControl.addEventListener('click', function () {
              if (window.innerWidth < 992 && collapseEl.classList.contains('show')) {
                if (collapseInstance && typeof collapseInstance.hide === 'function') {
                  collapseInstance.hide();
                } else if (window.jQuery && typeof window.jQuery(collapseEl).collapse === 'function') {
                  window.jQuery(collapseEl).collapse('hide');
                }
              }
            });
          });

          if (menuToggler) {
            menuToggler.addEventListener('click', function () {
              window.setTimeout(function () {
                const isOpenOrAnimating = collapseEl.classList.contains('show') || collapseEl.classList.contains('collapsing');
                setMobileMenuOpenState(isOpenOrAnimating);
                syncHeroMobileActionsOffset();
              }, 20);
            });
          }

          window.addEventListener('resize', function () {
            updateMobileMenuHeight();
            syncHeroMobileActionsOffset();
          });
          updateMobileMenuHeight();
          syncHeroMobileActionsOffset();
        });
      })();
    </script>

    @include('layouts.autofill-strict-off')

    <!-- Share Button -->
    <script>
      // Get a reference to all buttons with the class "share-button"
      const shareButtons = document.querySelectorAll('.share-button');
      
      // Add a click event listener to each button
      shareButtons.forEach(button => {
        button.addEventListener('click', () => {
          // Get the value to share/copy from the "data-share" attribute
          const valueToShare = button.dataset.share;
      
          // Check if the Web Share API is supported
          if (navigator.share) {
            // Call the Web Share API to open the native share dialog
            navigator.share({
              title: '{{__("messages.Share your profile")}}',
              text: valueToShare,
              url: valueToShare,
            })
            .catch(err => console.error('{{__("messages.Error sharing:")}}', err));
          } else {
            // If the Web Share API is not supported, copy the value to the clipboard
            navigator.clipboard.writeText(valueToShare)
            .then(() => {
              // If copying was successful, alert the user
              alert('{{__("messages.Text copied to clipboard!")}}');
            })
            .catch(err => {
              // If copying failed, alert the user
              alert('{{__("messages.Error copying text:")}}', err);
            });
          }
        });
      });
      </script>

<script src="{{ asset('assets/js/popper.js') }}"></script>
<script src="{{ asset('assets/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('assets/js/Sortable.min.js') }}"></script>
<script src="{{ asset('assets/js/jquery-block-ui.js') }}"></script>
<script src="{{ asset('assets/js/main-dashboard.js') }}"></script>

@stack('sidebar-scripts')

  </body>
</html>
