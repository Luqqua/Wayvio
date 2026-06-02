@php
    $sm = app(\Modules\Tiers\Services\SubscriptionManager::class);
    $agencyContext = app(\App\Services\Agency\AgencyHubContext::class);
    $user = auth()->user();
    $isPremium = $user ? $sm->featureEnabled($user, 'domains.custom_domain') : false;
    $agencySidebar = $user ? $agencyContext->sidebarData($user, request()) : ['is_agency' => false, 'hubs' => collect()];
    $isAgencyAccount = (bool) ($agencySidebar['is_agency'] ?? false);
    $ownerId = (int) ($user?->id ?? 0);
    $managedHubs = collect($agencySidebar['hubs'] ?? collect())
        ->filter(fn ($hub) => (int) ($hub->managed_user_id ?? 0) !== $ownerId)
        ->values();
    $managedHubsPayload = $managedHubs
        ->map(function ($hub): array {
            return [
                'id' => (int) ($hub->managed_user_id ?? 0),
                'display_name' => (string) ($hub->display_name ?? ''),
                'slug' => (string) ($hub->managedUser?->littlelink_name ?? ''),
            ];
        })
        ->values()
        ->all();

@endphp

@if($isPremium)
<div class="card rounded mt-3">
    <div class="card-body">
        <h3 class="mb-1">Domains</h3>
    </div>
</div>

@if($isAgencyAccount)
<div class="row mt-3">
    <div class="col-lg-4">
        <div class="card rounded h-100">
            <div class="card-body">
                <h5 class="mb-1">Agency Domain</h5>
                <p class="text-muted small mb-3">One optional fallback domain for all hubs. The owner hub opens at the domain root; managed hubs use <code>/\{slug\}</code>.</p>

                <div id="agency-domain-panel" class="mb-3"></div>

                <label class="form-label small">Set / replace agency domain</label>
                <div class="input-group">
                    <input type="text" id="agency-domain-input" class="form-control" placeholder="agency.your-domain.tld" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" inputmode="url" aria-autocomplete="none" data-lpignore="true" data-1p-ignore="true" readonly onfocus="this.removeAttribute('readonly')" onpointerdown="this.removeAttribute('readonly')" ontouchstart="this.removeAttribute('readonly')" onblur="this.setAttribute('readonly', 'readonly')">
                    <button class="btn btn-primary" id="btn-add-agency-domain">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-8 mt-3 mt-lg-0">
        <div class="card rounded h-100">
            <div class="card-body">
                <h5 class="mb-1">Hub Domains</h5>
                <p class="text-muted small mb-3">Optional direct domains per managed hub. If set, hub opens at domain root.</p>
                <div id="hub-domains-panel"></div>
            </div>
        </div>
    </div>
</div>
@else
<div class="card rounded mt-3">
    <div class="card-body">
        <h5 class="mb-1">Personal Domain</h5>
        <p class="text-muted small mb-3">Optional direct domain for your page.</p>
        <div id="single-domain-panel" class="mb-3"></div>

        <label class="form-label small">Set / replace domain</label>
        <div class="input-group">
            <input type="text" id="single-domain-input" class="form-control" placeholder="your-domain.tld" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" inputmode="url" aria-autocomplete="none" data-lpignore="true" data-1p-ignore="true" readonly onfocus="this.removeAttribute('readonly')" onpointerdown="this.removeAttribute('readonly')" ontouchstart="this.removeAttribute('readonly')" onblur="this.setAttribute('readonly', 'readonly')">
            <button class="btn btn-primary" id="btn-add-single-domain">Save</button>
        </div>
    </div>
</div>
@endif

<div class="alert alert-danger mt-3 d-none" id="domain-errors"></div>

<div class="card rounded mt-3">
    <div class="card-body">
        @include('modules.CustomDomains.views.dns-instructions')
    </div>
</div>

<script>
(() => {
    const endpoints = {
        list: "{{ url('/account/domains') }}",
        create: "{{ url('/account/domains') }}",
        verify: (id) => "{{ url('/account/domains') }}/" + id + "/verify",
        destroy: (id) => "{{ url('/account/domains') }}/" + id
    };

    const isAgencyAccount = @json($isAgencyAccount);
    const ownerUserId = {{ (int) ($user?->id ?? 0) }};
    const managedHubs = @json($managedHubsPayload);

    const errorBox = document.getElementById('domain-errors');
    const agencyPanel = document.getElementById('agency-domain-panel');
    const hubPanel = document.getElementById('hub-domains-panel');
    const singlePanel = document.getElementById('single-domain-panel');

    const agencyInput = document.getElementById('agency-domain-input');
    const addAgencyBtn = document.getElementById('btn-add-agency-domain');
    const singleInput = document.getElementById('single-domain-input');
    const addSingleBtn = document.getElementById('btn-add-single-domain');

    let currentDomains = [];

    const escapeHtml = (value) => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const showError = (msg = 'Error') => {
        errorBox.textContent = msg || 'Error';
        errorBox.classList.remove('d-none');
    };

    const clearError = () => {
        errorBox.classList.add('d-none');
        errorBox.textContent = '';
    };

    const extractErrorMessage = (data, fallback = 'Error') => {
        if (data && typeof data.message === 'string' && data.message.trim() !== '') {
            return data.message;
        }
        if (data && typeof data.error === 'string' && data.error.trim() !== '') {
            return data.error;
        }
        return fallback || 'Error';
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

    const domainStatusBadge = (status) => {
        const normalized = String(status || 'pending');
        const color = normalized === 'verified' ? 'success' : (normalized === 'failed' ? 'danger' : 'warning');
        return badge(normalized, color);
    };

    const canVerifyDomain = (domainRecord) => String(domainRecord?.status || '').toLowerCase() !== 'verified';

    const domainActionButtons = (domainRecord) => {
        const id = escapeHtml(domainRecord?.id || '');
        const verify = canVerifyDomain(domainRecord)
            ? `<button class="btn btn-sm btn-outline-primary" data-action="verify" data-id="${id}">Verify</button>`
            : '';

        return `${verify}<button class="btn btn-sm btn-outline-danger" data-action="delete" data-id="${id}">Remove</button>`;
    };

    const hasDomain = (id) => currentDomains.some((domainRecord) => String(domainRecord.id) === String(id));

    const mapDomains = (domains) => {
        const list = Array.isArray(domains) ? domains : [];
        const agencyDomain = list.find((d) => d.page_id === null || d.page_id === undefined) || null;
        const byPage = new Map();
        list.forEach((d) => {
            const pageId = d.page_id === null || d.page_id === undefined ? null : Number(d.page_id);
            if (Number.isInteger(pageId) && pageId > 0) {
                byPage.set(pageId, d);
            }
        });
        return { agencyDomain, byPage };
    };

    const renderAgencyPanel = (agencyDomain) => {
        if (!agencyPanel) return;

        if (!agencyDomain) {
            if (agencyInput) agencyInput.disabled = false;
            if (addAgencyBtn) addAgencyBtn.disabled = false;
            agencyPanel.innerHTML = '<p class="text-muted mb-0">No agency domain configured.</p>';
            return;
        }

        if (agencyInput) {
            agencyInput.value = '';
            agencyInput.disabled = true;
        }
        if (addAgencyBtn) addAgencyBtn.disabled = true;

        const domain = escapeHtml(agencyDomain.domain || '');
        agencyPanel.innerHTML = `
            <div class="border rounded p-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong>${domain}</strong>
                    <div>${domainStatusBadge(agencyDomain.status)}</div>
                </div>
                <div class="small text-muted">Pattern: ${domain} for owner, ${domain}/\{slug\} for hubs</div>
                <div class="small text-muted mt-1">A domain is already configured. Remove it before adding another one.</div>
                <div class="d-flex gap-1 mt-2">
                    ${domainActionButtons(agencyDomain)}
                </div>
            </div>
        `;
    };

    const renderHubPanel = (byPage) => {
        if (!hubPanel) return;

        if (!Array.isArray(managedHubs) || managedHubs.length === 0) {
            hubPanel.innerHTML = '<p class="text-muted mb-0">No managed hubs available.</p>';
            return;
        }

        const rows = managedHubs.map((hub) => {
            const hubId = Number(hub.id || 0);
            const hubDomain = byPage.get(hubId) || null;
            const hubName = escapeHtml(hub.display_name || 'Hub');
            const hubSlug = escapeHtml(hub.slug || 'n/a');

            if (hubDomain) {
                const domain = escapeHtml(hubDomain.domain || '');
                return `
                    <tr>
                        <td>
                            <div class="fw-semibold">${hubName}</div>
                            <div class="small text-muted">${hubSlug}</div>
                        </td>
                        <td>
                            <div>${domain}</div>
                            <div class="small text-muted">Pattern: ${domain}</div>
                        </td>
                        <td>${domainStatusBadge(hubDomain.status)}</td>
                        <td class="text-end">
                            ${domainActionButtons(hubDomain)}
                        </td>
                    </tr>
                `;
            }

            return `
                <tr>
                    <td>
                        <div class="fw-semibold">${hubName}</div>
                        <div class="small text-muted">${hubSlug}</div>
                    </td>
                    <td>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" placeholder="hub.your-domain.tld" data-role="hub-domain-input" data-page-id="${hubId}" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" inputmode="url" aria-autocomplete="none" data-lpignore="true" data-1p-ignore="true" readonly onfocus="this.removeAttribute('readonly')" onpointerdown="this.removeAttribute('readonly')" ontouchstart="this.removeAttribute('readonly')" onblur="this.setAttribute('readonly', 'readonly')">
                            <button class="btn btn-primary" data-action="add-hub" data-page-id="${hubId}">Save</button>
                        </div>
                    </td>
                    <td>${badge('inherits agency/saas', 'secondary')}</td>
                    <td class="text-end"></td>
                </tr>
            `;
        }).join('');

        hubPanel.innerHTML = `
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Hub</th>
                            <th>Domain</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        `;
    };

    const renderSinglePanel = (personalDomain) => {
        if (!singlePanel) return;

        if (!personalDomain) {
            if (singleInput) singleInput.disabled = false;
            if (addSingleBtn) addSingleBtn.disabled = false;
            singlePanel.innerHTML = '<p class="text-muted mb-0">No domain configured.</p>';
            return;
        }

        if (singleInput) {
            singleInput.value = '';
            singleInput.disabled = true;
        }
        if (addSingleBtn) addSingleBtn.disabled = true;

        const domain = escapeHtml(personalDomain.domain || '');
        singlePanel.innerHTML = `
            <div class="border rounded p-2">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong>${domain}</strong>
                    <div>${domainStatusBadge(personalDomain.status)}</div>
                </div>
                <div class="small text-muted">Pattern: ${domain}</div>
                <div class="small text-muted mt-1">A domain is already configured. Remove it before adding another one.</div>
                <div class="d-flex gap-1 mt-2">
                    ${domainActionButtons(personalDomain)}
                </div>
            </div>
        `;
    };

    const render = (domains) => {
        const mapped = mapDomains(domains);

        if (isAgencyAccount) {
            renderAgencyPanel(mapped.agencyDomain);
            renderHubPanel(mapped.byPage);
        } else {
            const personalDomain = mapped.byPage.get(ownerUserId) || null;
            renderSinglePanel(personalDomain);
        }
    };

    const fetchDomains = async () => {
        clearError();
        const res = await fetch(endpoints.list, { headers: { 'Accept': 'application/json' }});
        const data = await res.json();
        if (!res.ok) {
            throw new Error(extractErrorMessage(data, 'Failed to load domains.'));
        }

        currentDomains = Array.isArray(data) ? data : [];
        render(currentDomains);
        return currentDomains;
    };

    const createDomain = async ({ domain, scope, pageId = null }) => {
        const payload = { domain, scope };
        if (pageId !== null) {
            payload.page_id = Number(pageId);
        }

        const res = await fetch(endpoints.create, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (!res.ok) {
            throw new Error(extractErrorMessage(data, 'Failed to add domain.'));
        }

        return data;
    };

    const verifyDomain = async (id) => {
        const res = await fetch(endpoints.verify(id), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            const error = new Error(extractErrorMessage(data, 'Failed to verify domain.'));
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
                'Accept': 'application/json'
            }
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            const error = new Error(extractErrorMessage(data, 'Failed to remove domain.'));
            error.status = res.status;
            error.code = data?.error || null;
            throw error;
        }
    };

    if (addAgencyBtn) {
        addAgencyBtn.addEventListener('click', async () => {
            clearError();
            const domain = String(agencyInput?.value || '').trim();
            if (!domain) {
                showError('Please enter a domain.');
                return;
            }

            setButtonLoading(addAgencyBtn, true);
            try {
                await createDomain({ domain, scope: 'agency' });
                if (agencyInput) agencyInput.value = '';
                await fetchDomains();
            } catch (err) {
                showError(err.message);
            } finally {
                setButtonLoading(addAgencyBtn, false);
            }
        });
    }

    if (addSingleBtn) {
        addSingleBtn.addEventListener('click', async () => {
            clearError();
            const domain = String(singleInput?.value || '').trim();
            if (!domain) {
                showError('Please enter a domain.');
                return;
            }

            setButtonLoading(addSingleBtn, true);
            try {
                await createDomain({ domain, scope: 'hub', pageId: ownerUserId });
                if (singleInput) singleInput.value = '';
                await fetchDomains();
            } catch (err) {
                showError(err.message);
            } finally {
                setButtonLoading(addSingleBtn, false);
            }
        });
    }

    const delegatedClickHandler = async (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;

        const action = btn.dataset.action;
        clearError();

        setButtonLoading(btn, true);
        try {
            if (action === 'verify') {
                const domainId = btn.dataset.id;
                try {
                    await verifyDomain(domainId);
                } catch (err) {
                    if (err.status === 404 || err.code === 'domain_not_found') {
                        await fetchDomains();
                        if (!hasDomain(domainId)) {
                            return;
                        }
                    }
                    throw err;
                }
                await fetchDomains();
                return;
            }

            if (action === 'delete') {
                const domainId = btn.dataset.id;
                try {
                    await deleteDomain(domainId);
                } catch (err) {
                    if (err.status === 404 || err.status === 503 || err.code === 'domain_not_found') {
                        await fetchDomains();
                        if (!hasDomain(domainId)) {
                            return;
                        }
                    }
                    throw err;
                }
                await fetchDomains();
                return;
            }

            if (action === 'add-hub') {
                const pageId = Number(btn.dataset.pageId || 0);
                if (!Number.isInteger(pageId) || pageId <= 0) {
                    showError('Invalid hub selection.');
                    return;
                }

                const input = document.querySelector(`input[data-role="hub-domain-input"][data-page-id="${pageId}"]`);
                const domain = String(input?.value || '').trim();
                if (!domain) {
                    showError('Please enter a domain.');
                    return;
                }

                await createDomain({ domain, scope: 'hub', pageId });
                await fetchDomains();
            }
        } catch (err) {
            showError(err.message);
        } finally {
            setButtonLoading(btn, false);
        }
    };

    document.addEventListener('click', delegatedClickHandler);

    fetchDomains().catch((err) => showError(err.message));
})();
</script>
@endif
