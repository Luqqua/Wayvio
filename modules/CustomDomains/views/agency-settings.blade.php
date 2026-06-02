@php
    $sm = app(\Modules\Tiers\Services\SubscriptionManager::class);
    $agencyContext = app(\App\Services\Agency\AgencyHubContext::class);
    $errors = isset($errors) && $errors instanceof \Illuminate\Support\ViewErrorBag
        ? $errors
        : new \Illuminate\Support\ViewErrorBag();
    $user = auth()->user();
    $isPremium = $user ? $sm->featureEnabled($user, 'domains.custom_domain') : false;
    $isAgencyAccount = $user ? $agencyContext->isAgencyAccount($user) : false;
    $ownerId = (int) ($user?->id ?? 0);
    $brandingAssetPath = $isAgencyAccount ? \App\Models\UserData::getData($ownerId, 'agency_branding_asset') : null;
    $brandingLinkRaw = $isAgencyAccount ? \App\Models\UserData::getData($ownerId, 'agency_branding_link') : null;
    $brandingLink = is_string($brandingLinkRaw) ? trim($brandingLinkRaw) : '';
    if (strtolower($brandingLink) === 'null') {
        $brandingLink = '';
    }
    $brandingLinkInputName = 'branding_link_tenant_' . max(1, $ownerId);
    $brandingLinkAutocompleteSection = 'section-tenant-' . max(1, $ownerId) . ' url';
    $agencyLogoLimit = config('media.upload_limits.agency_logo', ['max_kb' => 3072, 'max_width' => 2500, 'max_height' => 2500]);
    $agencyLogoLimitMb = (int) ceil(max(1, (int) ($agencyLogoLimit['max_kb'] ?? 3072)) / 1024);
    $agencyLogoLimitWidth = max(1, (int) ($agencyLogoLimit['max_width'] ?? 2500));
    $agencyLogoLimitHeight = max(1, (int) ($agencyLogoLimit['max_height'] ?? 2500));

    $brandingAssetUrl = null;
    if (is_string($brandingAssetPath) && $brandingAssetPath !== '' && mediaPathExists($brandingAssetPath)) {
        $brandingAssetUrl = mediaPathUrl($brandingAssetPath);
    }

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
@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger">
    @foreach ($errors->all() as $error)
        <div>{{ $error }}</div>
    @endforeach
</div>
@endif

<div class="card rounded">
    <div class="card-body">
        <h3 class="mb-1">{{ $isAgencyAccount ? __('Branding') : __('Domain') }}</h3>
    </div>
</div>

<div class="card rounded mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
                <h5 class="mb-1">{{ $isAgencyAccount ? __('Agency domain') : __('Direct page domain') }}</h5>
                <p class="text-muted small mb-0">
                    {{ $isAgencyAccount
                        ? __('Configure the shared white-label domain for your agency portal. The owner hub opens at the domain root; managed hubs use /{slug}.')
                        : __('Configure the direct custom domain for your active page.') }}
                </p>
            </div>
            <span class="badge bg-light text-dark border">{{ $isAgencyAccount ? __('Global scope') : __('Single-page scope') }}</span>
        </div>

        <div id="agency-settings-panel" class="mb-3"></div>

        <label class="form-label small">{{ $isAgencyAccount ? __('Set / replace agency domain') : __('Set / replace direct domain') }}</label>
        <div class="input-group">
            <input type="text" id="agency-settings-input" class="form-control" placeholder="{{ $isAgencyAccount ? __('agency.your-domain.tld') : __('your-domain.tld') }}" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" inputmode="url" aria-autocomplete="none" data-lpignore="true" data-1p-ignore="true" readonly onfocus="this.removeAttribute('readonly')" onpointerdown="this.removeAttribute('readonly')" ontouchstart="this.removeAttribute('readonly')" onblur="this.setAttribute('readonly', 'readonly')">
            <button class="btn btn-primary" id="btn-save-agency-settings-domain">{{ __('Save') }}</button>
        </div>
    </div>
</div>

<div class="alert alert-danger mt-4 d-none" id="agency-settings-errors"></div>

@if($isAgencyAccount)
<div class="card rounded mt-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div>
                <h5 class="mb-1">{{ __('Agency branding') }}</h5>
                <p class="text-muted small mb-0">{{ __('Upload one shared logo for all hubs. Each hub can hide or show this branding in Appearance. If nothing is configured, public pages fall back to the current Wayvio branding.') }}</p>
            </div>
            <span class="badge bg-light text-dark border">{{ __('Global scope') }}</span>
        </div>

        <div class="border rounded p-3 mb-3">
            <p class="text-uppercase text-muted small mb-2">{{ __('Preview') }}</p>
            @if($brandingAssetUrl)
                @if($brandingLink !== '')
                    <a href="{{ $brandingLink }}" target="_blank" rel="noreferrer" class="d-inline-block">
                        <img src="{{ $brandingAssetUrl }}" alt="{{ __('Agency branding') }}" style="max-width: 220px; width: 100%; height: auto;">
                    </a>
                @else
                    <img src="{{ $brandingAssetUrl }}" alt="{{ __('Agency branding') }}" style="max-width: 220px; width: 100%; height: auto;">
                @endif
                <p class="text-muted small mt-2 mb-0">{{ __('Custom agency branding is active.') }}</p>
            @else
                <div class="d-inline-flex align-items-center gap-2 px-3 py-2 border rounded bg-light">
                    <img src="{{ asset('assets/wayvio/images/logo.svg') }}" alt="Wayvio logo" style="width: 28px; height: 28px; object-fit: contain;">
                    <span class="fw-semibold" style="line-height: 1; color: #0f3d3e;">WAYVIO</span>
                </div>
                <p class="text-muted small mt-2 mb-0">{{ __('No custom logo uploaded yet. The default Wayvio branding preview is shown until you add one.') }}</p>
            @endif
        </div>

        <form action="{{ route('agency.branding.save') }}" method="POST" enctype="multipart/form-data" autocomplete="off" data-lpignore="true" id="branding-form">
            @csrf
            <div class="mb-3">
                <label class="form-label small" for="branding-asset">{{ __('Logo upload') }}</label>
                <input type="file" id="branding-asset" name="branding_asset" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp,.jpeg,.jpg,.png,.webp" data-autofill-allow="true">
                <div id="branding-asset-preview" class="mt-2" style="display:none;">
                    <img id="branding-asset-preview-img" src="" alt="{{ __('Preview') }}" style="max-width:200px;max-height:80px;object-fit:contain;border:1px solid #dee2e6;border-radius:4px;padding:4px;background:#fff;">
                    <span id="branding-asset-preview-name" class="ms-2 small text-muted"></span>
                </div>
                <div class="form-text">{{ __('Allowed formats: JPG, JPEG, PNG or WebP.') }}</div>
                <div class="form-text">{{ __('messages.upload.limit.notice', ['size' => $agencyLogoLimitMb, 'width' => $agencyLogoLimitWidth, 'height' => $agencyLogoLimitHeight]) }}</div>
            </div>

            <div class="mb-3">
                <label class="form-label small" for="branding-link">{{ __('Branding link') }}</label>
                <input type="url" id="branding-link" name="{{ $brandingLinkInputName }}" class="form-control" placeholder="{{ __('https://agency.your-brand.tld') }}" value="{{ old('branding_link', $brandingLink) }}" autocomplete="{{ $brandingLinkAutocompleteSection }}" autocapitalize="off" autocorrect="off" spellcheck="false" inputmode="url" aria-autocomplete="none" data-lpignore="true" data-1p-ignore="true" readonly onfocus="this.removeAttribute('readonly')" onpointerdown="this.removeAttribute('readonly')" ontouchstart="this.removeAttribute('readonly')" onblur="this.setAttribute('readonly', 'readonly')">
                <input type="hidden" id="branding-link-hidden" name="branding_link" value="{{ old('branding_link', $brandingLink) }}" autocomplete="off" data-lpignore="true" data-1p-ignore="true">
                <div class="form-text">{{ __('Optional. If set, the public footer branding links here.') }}</div>
            </div>

            <div class="d-flex flex-wrap gap-2 align-items-center">
                <input type="hidden" id="remove-branding-asset-hidden" name="remove_branding_asset" value="0">
                <button type="submit" class="btn btn-primary" id="btn-save-branding">{{ __('Save branding') }}</button>
                @if($brandingAssetUrl)
                    <button type="submit" class="btn btn-outline-danger" data-branding-action="remove" formnovalidate>{{ __('Remove logo') }}</button>
                @endif
            </div>
        </form>
        <script>
        (() => {
            const form = document.getElementById('branding-form');
            if (!form) {
                return;
            }

            const fileInput = form.querySelector('#branding-asset');
            const displayInput = form.querySelector('#branding-link');
            const hiddenInput = form.querySelector('#branding-link-hidden');
            const removeInput = form.querySelector('#remove-branding-asset-hidden');
            const preview = form.querySelector('#branding-asset-preview');
            const previewImg = form.querySelector('#branding-asset-preview-img');
            const previewName = form.querySelector('#branding-asset-preview-name');

            if (!displayInput || !hiddenInput || !removeInput) {
                return;
            }

            // Show image preview when a file is selected
            if (fileInput && preview && previewImg && previewName) {
                fileInput.addEventListener('change', function () {
                    const file = this.files && this.files[0];
                    if (file) {
                        previewName.textContent = file.name;
                        const reader = new FileReader();
                        reader.onload = function (e) {
                            previewImg.src = e.target.result;
                            preview.style.display = '';
                        };
                        reader.readAsDataURL(file);
                    } else {
                        preview.style.display = 'none';
                        previewImg.src = '';
                        previewName.textContent = '';
                    }
                });
            }

            const syncValue = () => {
                hiddenInput.value = String(displayInput.value || '').trim();
            };

            let pendingBrandingAction = 'save';
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.addEventListener('click', () => {
                    pendingBrandingAction = button.dataset.brandingAction || 'save';
                });
            });

            const setSubmitLoading = (submitter) => {
                form.querySelectorAll('button[type="submit"]').forEach((button) => {
                    if (!button.dataset.originalHtml) {
                        button.dataset.originalHtml = button.innerHTML;
                    }
                    button.disabled = true;
                });

                if (submitter && submitter.dataset) {
                    submitter.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span><span class="visually-hidden">Loading</span>' + submitter.dataset.originalHtml;
                }
            };

            displayInput.addEventListener('input', syncValue);
            displayInput.addEventListener('change', syncValue);
            form.addEventListener('submit', (event) => {
                syncValue();
                const submitter = event.submitter || document.activeElement;
                const action = submitter && submitter.dataset ? (submitter.dataset.brandingAction || pendingBrandingAction) : pendingBrandingAction;
                removeInput.value = action === 'remove' ? '1' : '0';
                setSubmitLoading(event.submitter);
            });
            syncValue();
        })();
        </script>
    </div>
</div>
@endif

<div class="card rounded mt-4">
    <div class="card-body">
        @include('modules.CustomDomains.views.dns-instructions')
    </div>
</div>

<script>
(() => {
    const endpoints = {
        list: "{{ url('/account/domains') }}?{{ $isAgencyAccount ? 'scope=agency' : 'scope=hub&page_id=' . $ownerId }}",
        create: "{{ url('/account/domains') }}",
        verify: (id) => "{{ url('/account/domains') }}/" + id + "/verify",
        destroy: (id) => "{{ url('/account/domains') }}/" + id,
    };

    const createScope = @json($isAgencyAccount ? 'agency' : 'hub');
    const createPageId = @json($isAgencyAccount ? null : $ownerId);
    const hierarchyPattern = @json($isAgencyAccount ? __('Pattern: {domain} for owner, {domain}/{slug} for hubs') : __('Pattern: {domain}'));
    const i18n = {
        genericError: @json(__('Error')),
        noDomainConfigured: @json(__('No domain configured yet.')),
        verify: @json(__('Verify')),
        remove: @json(__('Remove')),
        loadFailed: @json(__('Failed to load domain settings.')),
        saveFailed: @json(__('Failed to save domain.')),
        verifyFailed: @json(__('Failed to verify domain.')),
        removeFailed: @json(__('Failed to remove domain.')),
        enterDomain: @json(__('Please enter a domain.')),
        domainAlreadyConfigured: @json(__('A domain is already configured. Remove it before adding another one.')),
    };

    const errorBox = document.getElementById('agency-settings-errors');
    const panel = document.getElementById('agency-settings-panel');
    const input = document.getElementById('agency-settings-input');
    const saveBtn = document.getElementById('btn-save-agency-settings-domain');

    const escapeHtml = (value) => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

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
            panel.innerHTML = '<p class="text-muted mb-0">' + escapeHtml(i18n.noDomainConfigured) + '</p>';
            return;
        }

        setCreateLocked(true);
        const domain = escapeHtml(domainRecord.domain || '');
        const pattern = escapeHtml(hierarchyPattern.replace('{domain}', domain));

        panel.innerHTML = `
            <div class="border rounded p-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <strong>${domain}</strong>
                    <div class="d-flex gap-1">
                        ${domainStatusBadge(domainRecord.status)}
                        ${sslStatusBadge(domainRecord.ssl_status)}
                    </div>
                </div>
                <div class="small text-muted">${pattern}</div>
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
        const payload = { domain, scope: createScope };
        if (createPageId !== null) {
            payload.page_id = Number(createPageId);
        }

        const res = await fetch(endpoints.create, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
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
@else
<div class="card rounded">
    <div class="card-body">
        <h3 class="mb-1">
            {{ __('Branding') }}
            <span class="badge bg-secondary ms-2">{{ __('Locked') }}</span>
        </h3>
    </div>
</div>

<div class="alert alert-warning mt-4 mb-0">
    {{ __('Available from :tier.', ['tier' => $domainRequiredTierLabel]) }}
</div>

<div class="card rounded mt-4">
    <div class="card-body">
        <h5 class="mb-2">{{ $isAgencyAccount ? __('Agency domain') : __('Direct page domain') }}</h5>
        <p class="text-muted small mb-0">
            {{ __('No custom domain is active for this account on the current tier.') }}
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
