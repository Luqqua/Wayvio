@extends('layouts.sidebar')

@php
    $slots = $agencySidebar['slots'] ?? ['used' => 0, 'total' => 1, 'available' => 0];
    $createBlocked = (bool) ($slots['create_blocked'] ?? false);
    $showInventoryWarning = $createBlocked && ((int) ($slots['available'] ?? 0)) > 0;
    $activeUserId = (int) ($agencySidebar['active_user_id'] ?? 0);
    $activeUser = $agencySidebar['active_user'] ?? null;
    $graceBanner = $graceBanner ?? ['active' => false, 'over_quota' => 0, 'days_left' => null, 'ends_at' => null];
    $domainUrlResolver = app(\App\Services\Domains\DomainUrlResolver::class);
    $owner = auth()->user();

    $slotsUsed  = (int) ($slots['used'] ?? 0);
    $slotsTotal = max(1, (int) ($slots['total'] ?? 1));
    $slotsPct   = min(100, (int) round($slotsUsed / $slotsTotal * 100));
    $barColor   = $slotsPct >= 90 ? 'bg-danger' : ($slotsPct >= 80 ? 'bg-warning' : 'bg-primary');

    $openCreateTab = $errors->any();
    $canReactivateSuspended = ((int) ($slots['available'] ?? 0)) > 0
        || ((int) ($reactivationSlotsAvailable ?? 0)) > 0;
@endphp

@push('sidebar-stylesheets')
<style>
/* ── Teal tab buttons (filled, white text) ─────────────── */
#hubTabs .nav-link {
    background-color: #079aa2 !important;
    color: #fff !important;
    border-color: #079aa2 !important;
    border-bottom-color: #079aa2 !important;
    opacity: .78;
    font-weight: 500;
    transition: opacity .15s, background-color .15s;
}
#hubTabs .nav-link:hover:not(.active) {
    opacity: .9;
    background-color: #068891 !important;
    border-color: #068891 !important;
}
#hubTabs .nav-link.active {
    background-color: #046068 !important;
    color: #fff !important;
    border-color: #046068 !important;
    /* bottom border matches card background to blend in */
    border-bottom-color: #fff !important;
    opacity: 1;
    font-weight: 600;
}

/* ── Dark mode tab buttons ─────────────────────────────── */
.dark #hubTabs .nav-link {
    background-color: #057b82 !important;
    color: #fff !important;
    border-color: #057b82 !important;
    border-bottom-color: #057b82 !important;
    opacity: .75;
}
.dark #hubTabs .nav-link:hover:not(.active) {
    background-color: #068891 !important;
    opacity: .9;
}
.dark #hubTabs .nav-link.active {
    background-color: #046068 !important;
    color: #fff !important;
    border-color: #046068 !important;
    border-bottom-color: #222738 !important;
    opacity: 1;
}

/* ── Tab-content card border blending ─────────────────── */
.dark .hub-tab-card {
    border-color: #30384f !important;
}

/* ── Hub card rows ─────────────────────────────────────── */
.dark .hub-card-row {
    border-color: #30384f !important;
    background-color: #1e2333;
}

/* ── Hub card layout with proper breakpoint ────────────── */
.hub-card-row {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;        /* always wrappable */
}
.hub-card-main {
    display: flex;
    align-items: center;
    gap: .75rem;
    flex: 1 1 0;
    min-width: 0;
}
.hub-card-actions {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
    /* on narrow screens: take full width */
    flex: 1 1 100%;
}
@media (min-width: 768px) {
    .hub-card-actions {
        flex: 0 0 auto;   /* shrink back to content width on md+ */
    }
}

/* ── Progress bar track ────────────────────────────────── */
.dark .hub-progress-track {
    background-color: #30384f;
}

/* ── Slot pill ─────────────────────────────────────────── */
.hub-slot-pill {
    background-color: #079aa2 !important;
}
.dark .hub-slot-pill {
    background-color: #057b82 !important;
}

/* ── Slug preview color in dark ────────────────────────── */
.dark #hub-slug-preview {
    color: #dee2e6;
}
</style>
@endpush

@section('content')
<div class="container-fluid content-inner mt-n5 py-0 pb-5 ls-consistent-spacing">

    {{-- Flash & error alerts --}}
    @if(session('success'))
        <div class="alert alert-success rounded mt-4 mb-0">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger rounded mt-4 mb-0">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif
    @if(!empty($graceBanner['active']))
        <div class="alert alert-warning rounded mt-4">
            <div class="fw-semibold mb-1">
                {{ __('You have :count too many hub(s) for your current plan.', ['count' => (int) ($graceBanner['over_quota'] ?? 0)]) }}
            </div>
            <div class="small">
                {{ __('Choose which hubs should be deactivated or upgrade your plan again.') }}
                {{ __('Automatic deactivation in :days days.', ['days' => (int) ($graceBanner['days_left'] ?? 0)]) }}
            </div>
            <a href="{{ route('subscription.dashboard') }}" class="btn btn-sm btn-outline-dark mt-2">{{ __('Upgrade') }}</a>
        </div>
    @endif
    @if($showInventoryWarning)
        <div class="alert alert-warning rounded mt-4">
            <div class="fw-semibold mb-1">{{ __('Hub inventory is full') }}</div>
            <div class="small mb-2">
                {{ __('You reached the maximum number of managed hubs (including suspended hubs). Permanently delete a suspended hub or upgrade your plan.') }}
            </div>
            <a href="{{ route('subscription.dashboard') }}" class="btn btn-sm btn-outline-dark">{{ __('Upgrade') }}</a>
        </div>
    @endif

    {{-- Header --}}
    <div class="card rounded mt-4">
        <div class="card-body d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
            <div>
                <h3 class="mb-1">{{ __('Hub-Verwaltung') }}</h3>
                <p class="text-muted mb-0 small">{{ __('Erstelle, bearbeite und verwalte deine Hubs.') }}</p>
            </div>
            <div class="flex-shrink-0">
                <span class="badge rounded-pill hub-slot-pill fs-6 px-3 py-2 text-white">
                    {{ $slotsUsed }}&thinsp;/&thinsp;{{ $slotsTotal }} Slots
                </span>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs mt-4 border-bottom-0 gap-2" id="hubTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 {{ $openCreateTab ? '' : 'active' }}"
                    id="tab-overview-btn"
                    data-bs-toggle="tab"
                    data-bs-target="#tab-overview"
                    type="button" role="tab"
                    aria-controls="tab-overview"
                    aria-selected="{{ $openCreateTab ? 'false' : 'true' }}">
                {{ __('Übersicht') }}
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link px-4 {{ $openCreateTab ? 'active' : '' }}"
                    id="tab-create-btn"
                    data-bs-toggle="tab"
                    data-bs-target="#tab-create"
                    type="button" role="tab"
                    aria-controls="tab-create"
                    aria-selected="{{ $openCreateTab ? 'true' : 'false' }}">
                {{ __('Hub erstellen') }}
            </button>
        </li>
    </ul>

    <div class="tab-content">

        {{-- ── Übersicht Tab ──────────────────────────────── --}}
        <div class="tab-pane fade {{ $openCreateTab ? '' : 'show active' }}"
             id="tab-overview" role="tabpanel" aria-labelledby="tab-overview-btn">

            <div class="card hub-tab-card rounded-top-0 border-top-0">
                <div class="card-body pb-2">

                    {{-- Slot progress bar --}}
                    <div class="mb-4">
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>{{ __('Belegte Slots') }}</span>
                            <span>{{ $slotsUsed }} von {{ $slotsTotal }}</span>
                        </div>
                        <div class="progress hub-progress-track" style="height:6px;">
                            <div class="progress-bar {{ $barColor }}"
                                 role="progressbar"
                                 style="width:{{ $slotsPct }}%"
                                 aria-valuenow="{{ $slotsPct }}"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                            </div>
                        </div>
                    </div>

                    {{-- Hub cards --}}
                    @if($managedHubs->isEmpty())
                        <p class="text-muted text-center py-4 mb-0">{{ __('Noch keine Hubs erstellt.') }}</p>
                    @else
                        <div class="d-flex flex-column gap-3 pb-2">
                            @foreach($managedHubs as $hub)
                                @php
                                    $hubUser    = $hub->managedUser;
                                    $profileUrl = ($owner && $hubUser)
                                        ? $domainUrlResolver->profileUrlForEditor($owner, $hubUser)
                                        : null;
                                    $managedId  = (int) ($hub->managed_user_id ?? 0);
                                    $isPublished = (bool) ($hubUser?->is_published ?? false);
                                    $hubStatus  = $hub->status ?? 'active';
                                    $slug       = $hubUser?->littlelink_name ?? null;

                                    $hubTitle     = trim((string)(($hubUser?->name ?? '') ?: ($hubUser?->littlelink_name ?? '?')));
                                    $initials     = mb_strtoupper(mb_substr($hubTitle, 0, 2));
                                    $avatarColors = ['#3a57e8','#079aa2','#1aa053','#f16a1b','#c03221','#001F4D','#6f42c1'];
                                    $avatarColor  = $avatarColors[crc32($hubTitle) % count($avatarColors)];

                                    $pubConfirmTitle = $isPublished
                                        ? __('messages.hub.publish.confirm_unpublish_title')
                                        : __('messages.hub.publish.confirm_publish_title');
                                    $pubConfirmBody = $isPublished
                                        ? __('messages.hub.publish.confirm_unpublish_body')
                                        : __('messages.hub.publish.confirm_publish_body');
                                @endphp

                                {{-- Card: wraps at <md, single row on md+ --}}
                                <div class="hub-card-row border rounded p-3">

                                    {{-- Main: avatar + info + badges, always horizontal --}}
                                    <div class="hub-card-main">

                                        {{-- Avatar --}}
                                        <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded text-white fw-bold"
                                             style="width:44px;height:44px;font-size:.9rem;background:{{ $avatarColor }};letter-spacing:.03em;">
                                            {{ $initials }}
                                        </div>

                                        {{-- Name + URL --}}
                                        <div class="flex-grow-1" style="min-width:0;">
                                            <div class="fw-semibold text-truncate">{{ $hubTitle }}</div>
                                            @if($profileUrl)
                                                <a href="{{ $profileUrl }}" target="_blank" rel="noopener"
                                                   class="small text-muted text-decoration-none text-truncate d-block">
                                                    {{ $profileUrl }}
                                                </a>
                                            @elseif($slug)
                                                <span class="small text-muted">wayvio.com/{{ $slug }}</span>
                                            @else
                                                <span class="small text-muted">—</span>
                                            @endif
                                        </div>

                                        {{-- Badges (hidden on xs) --}}
                                        <div class="d-none d-md-flex flex-column gap-1 align-items-end flex-shrink-0">
                                            @if($hubStatus === 'suspended')
                                                <span class="badge bg-warning text-dark" style="font-size:.68rem;">{{ __('Gesperrt') }}</span>
                                            @elseif($hubStatus === 'pending_deletion')
                                                <span class="badge bg-danger" style="font-size:.68rem;">{{ __('Löschung ausstehend') }}</span>
                                            @else
                                                <span class="badge bg-success" style="font-size:.68rem;">{{ __('Aktiv') }}</span>
                                            @endif

                                            @if($hubStatus === 'active')
                                                <span class="badge {{ $isPublished ? 'bg-primary' : 'bg-secondary' }}" style="font-size:.68rem;">
                                                    {{ $isPublished ? __('Öffentlich') : __('Privat') }}
                                                </span>
                                            @endif
                                        </div>

                                    </div>{{-- /.hub-card-main --}}

                                    {{-- Actions: full-width below md, inline on md+ --}}
                                    <div class="hub-card-actions">
                                        @if($hubStatus === 'active')

                                            <form method="POST" action="{{ route('agency.hubs.switch') }}" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="managed_user_id" value="{{ $managedId }}">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    {{ __('Bearbeiten') }}
                                                </button>
                                            </form>

                                            <form method="POST"
                                                  action="{{ route('agency.hubs.publication', ['managed_user_id' => $managedId]) }}"
                                                  onsubmit="return confirm('{{ $pubConfirmTitle }}\n{{ $pubConfirmBody }}');"
                                                  class="d-inline">
                                                @csrf
                                                <input type="hidden" name="publish" value="{{ $isPublished ? 0 : 1 }}">
                                                <button type="submit"
                                                        class="btn btn-sm {{ $isPublished ? 'btn-outline-secondary' : 'btn-outline-success' }}">
                                                    {{ $isPublished
                                                        ? __('messages.hub.publish.action_unpublish')
                                                        : __('Öffentlich') }}
                                                </button>
                                            </form>

                                            <form method="POST"
                                                  action="{{ route('agency.hubs.destroy', ['managed_user_id' => $managedId]) }}"
                                                  onsubmit="return confirm('{{ __('Diesen Hub deaktivieren?') }}');"
                                                  class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    {{ __('Deaktivieren') }}
                                                </button>
                                            </form>

                                        @else

                                            @if($hubStatus === 'suspended' && $canReactivateSuspended)
                                                <form method="POST"
                                                      action="{{ route('agency.hubs.reactivate', ['managed_user_id' => $managedId]) }}"
                                                      onsubmit="return confirm('{{ __('Diesen Hub reaktivieren?') }}');"
                                                      class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success">
                                                        {{ __('Reaktivieren') }}
                                                    </button>
                                                </form>
                                            @elseif($hubStatus === 'suspended')
                                                <a href="{{ route('subscription.dashboard') }}"
                                                   class="btn btn-sm btn-outline-secondary">
                                                    {{ __('Upgrade') }}
                                                </a>
                                            @endif

                                            @if(in_array($hubStatus, ['suspended', 'pending_deletion'], true))
                                                <form method="POST"
                                                      action="{{ route('agency.hubs.destroy', ['managed_user_id' => $managedId]) }}"
                                                      onsubmit="return confirm('{{ __('Diesen Hub dauerhaft löschen? Alle Hub-Daten werden unwiderruflich entfernt.') }}');"
                                                      class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        {{ __('Endgültig löschen') }}
                                                    </button>
                                                </form>
                                            @endif

                                        @endif
                                    </div>{{-- /.hub-card-actions --}}

                                </div>{{-- /.hub-card-row --}}
                            @endforeach
                        </div>
                    @endif

                </div>
            </div>
        </div>

        {{-- ── Hub erstellen Tab ──────────────────────────── --}}
        <div class="tab-pane fade {{ $openCreateTab ? 'show active' : '' }}"
             id="tab-create" role="tabpanel" aria-labelledby="tab-create-btn">

            <div class="card hub-tab-card rounded-top-0 border-top-0">
                <div class="card-body" style="max-width:480px;">

                    @if($createBlocked && !$showInventoryWarning)
                        <p class="text-muted small mb-0">
                            {{ __('Du hast alle verfügbaren Hub-Slots belegt.') }}
                            <a href="{{ route('subscription.dashboard') }}">{{ __('Plan upgraden') }}</a>
                        </p>
                    @else
                        <form method="POST" action="{{ route('agency.hubs.store') }}" id="hub-create-form">
                            @csrf

                            {{-- Display name --}}
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-baseline mb-1">
                                    <label for="hub_display_name" class="form-label mb-0 fw-medium">
                                        {{ __('Anzeigename') }}
                                    </label>
                                    <span id="hub-name-counter" class="small text-muted">0&thinsp;/&thinsp;30</span>
                                </div>
                                <input type="text"
                                       class="form-control @error('display_name') is-invalid @enderror"
                                       id="hub_display_name"
                                       name="display_name"
                                       maxlength="30"
                                       value="{{ old('display_name') }}"
                                       autocomplete="off"
                                       required>
                                @error('display_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Slug --}}
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-baseline mb-1">
                                    <label for="hub_littlelink_name" class="form-label mb-0 fw-medium">
                                        {{ __('URL-Endung') }}
                                    </label>
                                    <span id="hub-slug-counter" class="small text-muted">0&thinsp;/&thinsp;25</span>
                                </div>
                                <input type="text"
                                       class="form-control @error('littlelink_name') is-invalid @enderror"
                                       id="hub_littlelink_name"
                                       name="littlelink_name"
                                       maxlength="25"
                                       value="{{ old('littlelink_name') }}"
                                       autocomplete="off"
                                       required>
                                @error('littlelink_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text small text-muted mt-1">
                                    wayvio.com/<span id="hub-slug-preview" class="fw-medium text-body">deine-endung</span>
                                </div>
                            </div>

                            <button type="submit"
                                    class="btn btn-primary w-100"
                                    @disabled($createBlocked)>
                                {{ __('Hub erstellen') }}
                            </button>

                            @if($showInventoryWarning)
                                <p class="small text-muted text-center mt-2 mb-0">
                                    {{ __('Zuerst einen gesperrten Hub endgültig löschen oder Plan upgraden.') }}
                                </p>
                            @endif
                        </form>
                    @endif

                </div>
            </div>
        </div>

    </div>{{-- /.tab-content --}}

</div>

@push('sidebar-scripts')
<script>
(function () {
    function bindCounter(inputId, counterId) {
        const input = document.getElementById(inputId);
        const counter = document.getElementById(counterId);
        if (!input || !counter) return;
        const max = input.maxLength || 0;
        const update = () => { counter.textContent = input.value.length + ' / ' + max; };
        input.addEventListener('input', update);
        update();
    }
    bindCounter('hub_display_name', 'hub-name-counter');
    bindCounter('hub_littlelink_name', 'hub-slug-counter');

    const slugInput   = document.getElementById('hub_littlelink_name');
    const slugPreview = document.getElementById('hub-slug-preview');
    if (slugInput && slugPreview) {
        slugInput.addEventListener('input', function () {
            slugPreview.textContent = this.value.length ? this.value : 'deine-endung';
        });
        if (slugInput.value) slugPreview.textContent = slugInput.value;
    }
})();
</script>
@endpush

@endsection
