@php
    $sm = app(\Modules\Tiers\Services\SubscriptionManager::class);
    $user = auth()->user();
    $isPremium = $user ? $sm->featureEnabled($user, 'domains.custom_domain') : false;
    $isAgencyAccount = (bool) ($isAgencyAccount ?? false);
    $entryLabel = 'Branding';
@endphp

@if($isPremium && $isAgencyAccount)
<li class="nav-item">
    <a class="nav-link {{ request()->routeIs('domains.page') ? 'active' : '' }}" href="{{ route('domains.page') }}">
        <i class="icon">
            <svg class="icon-20" width="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 5H20V19H4V5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M8 9H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                <path d="M5 13H19" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                <path d="M8 17H12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
            </svg>
        </i>
        <span class="item-name">{{ $entryLabel }}</span>
    </a>
</li>
@endif
