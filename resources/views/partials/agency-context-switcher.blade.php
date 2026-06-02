@if(($isAgencyAccount ?? false) === true)
@php
    $usedSlots = (int) ($agencySlots['used'] ?? 0);
    $totalSlots = (int) ($agencySlots['total'] ?? 0);
    $activeHubName = trim((string) (($agencySidebar['active_user']->name ?? '') ?: ($agencySidebar['active_user']->littlelink_name ?? '')));
    $isGermanLocale = str_starts_with((string) app()->getLocale(), 'de');
    $activeLabel = $isGermanLocale ? 'Aktiv:' : 'Active:';
    $slotsLabel = $isGermanLocale ? 'Slots (inkl. Inhaber):' : 'Slots (inkl. Owner):';
    $workspaceLabel = $isGermanLocale ? 'Aktiver Workspace' : 'Active Workspace';
@endphp
<li class="nav-item static-item">
    <a class="nav-link static-item disabled" href="#" tabindex="-1">
        <span class="default-icon">Hub Selector</span>
        <span class="mini-icon">-</span>
    </a>
</li>
<li class="nav-item px-3 pb-2 agency-context-panel">
    <div class="rounded border p-2 agency-context-card">
        <div class="small text-muted mb-1 agency-context-line">
            {{ $activeLabel }}
            <span class="agency-context-value">{{ $activeHubName !== '' ? $activeHubName : 'n/a' }}</span>
        </div>
        <div class="small text-muted mb-2 agency-context-line">{{ $slotsLabel }} {{ $usedSlots }}/{{ $totalSlots }}</div>

        <form method="POST" action="{{ route('agency.hubs.switch') }}" class="mb-2 agency-context-form">
            @csrf
            <label class="form-label small mb-1" for="agency-hub-switch">{{ $workspaceLabel }}</label>
            <select id="agency-hub-switch" name="managed_user_id" class="form-select form-select-sm agency-context-select" onchange="this.form.submit()">
                @foreach(($agencyHubs ?? collect()) as $hub)
                    <option
                        value="{{ (int) $hub->managed_user_id }}"
                        @selected((int) ($agencySidebar['active_user_id'] ?? 0) === (int) $hub->managed_user_id)
                    >
                        {{ trim((string)(($hub->managedUser?->name ?? '') ?: ($hub->managedUser?->littlelink_name ?? 'n/a'))) }} ({{ $hub->managedUser?->littlelink_name ?? 'n/a' }})
                    </option>
                @endforeach
            </select>
        </form>
    </div>
</li>
@endif
