@php
    $sm = app(\Modules\Tiers\Services\SubscriptionManager::class);
    $agencyContext = app(\App\Services\Agency\AgencyHubContext::class);
    $user = auth()->user();
    $isPremium = $user ? $sm->featureEnabled($user, 'domains.custom_domain') : false;
    $isAgencyAccount = $user ? $agencyContext->isAgencyAccount($user) : false;
    $ownerId = (int) ($user?->id ?? 0);
    $sidebar = $user ? $agencyContext->sidebarData($user, request()) : ['active_user' => null];
    $activeUser = $sidebar['active_user'] ?? $user;
    $activePageId = (int) ($activeUser?->id ?? $ownerId);
    $activeLabel = trim((string) (($activeUser?->name ?? '') ?: ($activeUser?->littlelink_name ?? '')));
    $activeSlug = trim((string) ($activeUser?->littlelink_name ?? ''));
    $ownerWorkspaceSelected = $isAgencyAccount && $activePageId === $ownerId;

    $domainRequiredTierLabel = 'Pro';
    $tierPlans = collect(config('tiers.plans', []))->keyBy('slug');
    foreach (config('tiers.order', ['free', 'basic', 'pro', 'agency']) as $tierSlug) {
        $planConfig = $tierPlans->get($tierSlug);
        if ((bool) data_get($planConfig, 'features.domains.custom_domain', false)) {
            $domainRequiredTierLabel = (string) ($planConfig['name'] ?? ucfirst((string) $tierSlug));
            break;
        }
    }

    $subscriptionDashboardUrl = url('/dashboard/subscription');
@endphp

@if($isPremium)
<div class="card rounded">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h3 class="mb-1">{{ __('Hub Domain') }}</h3>
            </div>
            <span class="badge bg-light text-dark border">{{ __('Selected workspace') }}</span>
        </div>
    </div>
</div>

<div class="card rounded mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
                <h5 class="mb-1">{{ $activeLabel !== '' ? $activeLabel : __('Current workspace') }}</h5>
                <p class="text-muted small mb-0">{{ $activeSlug !== '' ? '@' . $activeSlug : __('No slug available') }}</p>
            </div>
            <span class="badge bg-light text-dark border">page_id: {{ $activePageId }}</span>
        </div>

        @if($ownerWorkspaceSelected)
            <div class="alert alert-info mb-0">
                {{ __('The owner workspace uses the global agency settings domain. Select a managed hub to attach a dedicated direct domain.') }}
            </div>
        @else
            <div id="hub-domain-panel" class="mb-3"></div>

            <label class="form-label small">{{ __('Set / replace hub domain') }}</label>
            <div class="input-group">
                <input type="text" id="hub-domain-input" class="form-control" placeholder="{{ __('hub.your-domain.tld') }}" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" inputmode="url" aria-autocomplete="none" data-lpignore="true" data-1p-ignore="true" readonly onfocus="this.removeAttribute('readonly')" onpointerdown="this.removeAttribute('readonly')" ontouchstart="this.removeAttribute('readonly')" onblur="this.setAttribute('readonly', 'readonly')">
                <button class="btn btn-primary" id="btn-save-hub-domain">{{ __('Save') }}</button>
            </div>
        @endif
    </div>
</div>

<div class="alert alert-danger mt-4 d-none" id="hub-domain-errors"></div>

<div class="card rounded mt-4">
    <div class="card-body">
        @include('modules.CustomDomains.views.dns-instructions')
    </div>
</div>

@if(!$ownerWorkspaceSelected)
<script>
(() => {
    const activePageId = {{ $activePageId }};
    const endpoints = {
        list: "{{ url('/account/domains') }}?scope=hub&page_id={{ $activePageId }}",
        create: "{{ url('/account/domains') }}",
        verify: (id) => "{{ url('/account/domains') }}/" + id + "/verify",
        destroy: (id) => "{{ url('/account/domains') }}/" + id,
    };

    const errorBox = document.getElementById('hub-domain-errors');
    const panel = document.getElementById('hub-domain-panel');
    const input = document.getElementById('hub-domain-input');
    const saveBtn = document.getElementById('btn-save-hub-domain');
    const patternTemplate = @json(__('Pattern: {domain}'));
    const i18n = {
        genericError: @json(__('Error')),
        noDirectDomainConfigured: @json(__('No direct hub domain configured. This workspace falls back to the agency or SaaS route.')),
        verify: @json(__('Verify')),
        remove: @json(__('Remove')),
        loadFailed: @json(__('Failed to load hub domain.')),
        saveFailed: @json(__('Failed to save hub domain.')),
        verifyFailed: @json(__('Failed to verify domain.')),
        removeFailed: @json(__('Failed to remove domain.')),
        enterDomain: @json(__('Please enter a domain.')),
        domainAlreadyConfigured: @json(__('A domain is already configured. Remove it before adding another one.')),
    };

    const escapeHtml = (value) => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    const formatPattern = (domain) => String(patternTemplate || 'Pattern: {domain}').replace('{domain}', domain);

    const showError = (msg = i18n.genericError) => {
        errorBox.textContent = msg || i18n.genericError;
        errorBox.classList.remove('d-none');
    };

    const clearError = () => {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    };

    const extractErrorMessage = (data, fallback = i18n.genericError) => {
        if (data && typeof data.message === 'string' && data.message.trim() !== '') {
            return data.message;
        }
        if (data && typeof data.error === 'string' && data.error.trim() !== '') {
            return data.error;
        }
        return fallback || i18n.genericError;
    };

    const setButtonLoading = (button, loading) => {
        if (!button) return;

        if (loading) {
            if (!button.dataset.originalHtml) {
                button.dataset.originalHtml = button.innerHTML;
            }
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="visually-hidden">Loading</span>' + button.dataset.originalHtml;
            return;
        }

        button.disabled = false;
        if (button.dataset.originalHtml) {
            button.innerHTML = button.dataset.originalHtml;
            delete button.dataset.originalHtml;
        }
    };

    const badge = (label, color = 'secondary') => `<span class="badge bg-${color}">${escapeHtml(label)}</span>`;

    const setCreateLocked = (locked) => {
        if (input) {
            input.disabled = locked;
            if (locked) {
                input.value = '';
            }
        }
        if (saveBtn) {
            saveBtn.disabled = locked;
        }
    };

    const domainStatusBadge = (status) => {
        const normalized = String(status || 'pending');
        const color = normalized === 'verified' ? 'success' : (normalized === 'failed' ? 'danger' : 'warning');
        return badge(normalized, color);
    };

    const sslStatusBadge = (status) => {
        const normalized = String(status || 'pending');
        const color = normalized === 'active' ? 'success' : (normalized === 'failed' ? 'danger' : 'secondary');
        return badge(`TLS: ${normalized}`, color);
    };

    const canVerifyDomain = (domainRecord) => String(domainRecord?.status || '').toLowerCase() !== 'verified';

    const domainActionButtons = (domainRecord) => {
        const id = escapeHtml(domainRecord?.id || '');
        const verify = canVerifyDomain(domainRecord)
            ? `<button class="btn btn-sm btn-outline-primary" data-action="verify" data-id="${id}">${escapeHtml(i18n.verify)}</button>`
            : '';

        return `${verify}<button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${id}">${escapeHtml(i18n.remove)}</button>`;
    };

    const render = (domainRecord) => {
        if (!domainRecord) {
            setCreateLocked(false);
            panel.innerHTML = '<p class="text-muted mb-0">' + escapeHtml(i18n.noDirectDomainConfigured) + '</p>';
            return;
        }

        setCreateLocked(true);
        const domain = escapeHtml(domainRecord.domain || '');

        panel.innerHTML = `
            <div class="border rounded p-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <strong>${domain}</strong>
                    <div class="d-flex gap-1">
                        ${domainStatusBadge(domainRecord.status)}
                        ${sslStatusBadge(domainRecord.ssl_status)}
                    </div>
                </div>
                <div class="small text-muted">${escapeHtml(formatPattern(domainRecord.domain || ''))}</div>
                <div class="small text-muted mt-2">${escapeHtml(i18n.domainAlreadyConfigured)}</div>
                <div class="d-flex gap-1 mt-3">
                    ${domainActionButtons(domainRecord)}
                </div>
            </div>
        `;
    };

    const fetchDomain = async () => {
        clearError();
        const res = await fetch(endpoints.list, { headers: { 'Accept': 'application/json' } });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(extractErrorMessage(data, i18n.loadFailed));
        }

        const domainRecord = Array.isArray(data) ? (data[0] || null) : null;
        render(domainRecord);
        return domainRecord;
    };

    const createDomain = async (domain) => {
        const res = await fetch(endpoints.create, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                domain,
                scope: 'hub',
                page_id: activePageId,
            }),
        });

        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(extractErrorMessage(data, i18n.saveFailed));
        }

        return data;
    };

    const verifyDomain = async (id) => {
        const res = await fetch(endpoints.verify(id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            const error = new Error(extractErrorMessage(data, i18n.verifyFailed));
            error.status = res.status;
            error.code = data?.error || null;
            throw error;
        }
    };

    const deleteDomain = async (id) => {
        const res = await fetch(endpoints.destroy(id), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            const error = new Error(extractErrorMessage(data, i18n.removeFailed));
            error.status = res.status;
            error.code = data?.error || null;
            throw error;
        }
    };

    saveBtn?.addEventListener('click', async () => {
        clearError();

        const domain = String(input?.value || '').trim();
        if (!domain) {
            showError(i18n.enterDomain);
            return;
        }

        setButtonLoading(saveBtn, true);
        try {
            await createDomain(domain);
            if (input) {
                input.value = '';
            }
            await fetchDomain();
        } catch (err) {
            showError(err.message);
        } finally {
            setButtonLoading(saveBtn, false);
        }
    });

    document.addEventListener('click', async (event) => {
        const btn = event.target.closest('button[data-action]');
        if (!btn || !panel.contains(btn)) {
            return;
        }

        clearError();

        setButtonLoading(btn, true);
        try {
            if (btn.dataset.action === 'verify') {
                try {
                    await verifyDomain(btn.dataset.id);
                } catch (err) {
                    if (err.status === 404 || err.code === 'domain_not_found') {
                        const domainRecord = await fetchDomain();
                        if (!domainRecord) {
                            return;
                        }
                    }
                    throw err;
                }
                await fetchDomain();
                return;
            }

            if (btn.dataset.action === 'delete') {
                try {
                    await deleteDomain(btn.dataset.id);
                } catch (err) {
                    if (err.status === 404 || err.status === 503 || err.code === 'domain_not_found') {
                        const domainRecord = await fetchDomain();
                        if (!domainRecord) {
                            return;
                        }
                    }
                    throw err;
                }
                await fetchDomain();
            }
        } catch (err) {
            showError(err.message);
        } finally {
            setButtonLoading(btn, false);
        }
    });

    fetchDomain().catch((err) => showError(err.message));
})();
</script>
@endif
@else
<div class="card rounded">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h3 class="mb-1">
                    {{ __('Hub Domain') }}
                    <span class="badge bg-secondary ms-2">{{ __('Locked') }}</span>
                </h3>
            </div>
            <span class="badge bg-light text-dark border">{{ __('Selected workspace') }}</span>
        </div>
    </div>
</div>

<div class="alert alert-warning mt-4 mb-0">
    {{ __('Available from :tier.', ['tier' => $domainRequiredTierLabel]) }}
</div>

<div class="card rounded mt-4">
    <div class="card-body">
        <h5 class="mb-2">{{ $activeLabel !== '' ? $activeLabel : __('Current workspace') }}</h5>
        <p class="text-muted small mb-0">
            {{ __('No custom domain is active for this workspace on the current tier.') }}
            {{ __('Upgrade to :tier to add, verify, and manage domains.', ['tier' => $domainRequiredTierLabel]) }}
        </p>
        <div class="mt-3">
            <button type="button" class="btn btn-primary" data-upsell-redirect>{{ __('Go to subscription') }}</button>
        </div>
    </div>
</div>

<div class="card rounded mt-4">
    <div class="card-body">
        @include('modules.CustomDomains.views.dns-instructions')
    </div>
</div>

<script>
(() => {
    const subscriptionUrl = @json($subscriptionDashboardUrl);
    document.querySelectorAll('[data-upsell-redirect]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            window.location.assign(subscriptionUrl);
        });
    });
})();
</script>
@endif
