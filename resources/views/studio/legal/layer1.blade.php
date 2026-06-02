@php
    $layer1 = (array) ($privacyLayerModel['layer1'] ?? []);
    $layer1Sync = (array) ($privacyLayerModel['layer1_sync'] ?? []);
    $layer1ImprintDefaults = (array) ($privacyLayerModel['layer1_imprint_defaults'] ?? []);
    $legalLocale = $privacyLocale ?? app()->getLocale();
    $isSynced = static function (string $key) use ($layer1Sync): bool {
        return (bool) ($layer1Sync[$key] ?? false);
    };
    $syncSourceText = static function (string $key) use ($layer1Sync, $legalLocale): string {
        return (bool) ($layer1Sync[$key] ?? false)
            ? __('messages.legal.privacy.layer1.source.imprint', [], $legalLocale)
            : __('messages.legal.privacy.layer1.source.custom', [], $legalLocale);
    };
@endphp

<div class="card border imprint-card" id="privacy-layer1">
    <div class="card-body">
        <h5 class="mb-2">{{ __('messages.legal.privacy.layer1.title', [], $legalLocale) }}</h5>
        <p class="text-muted mb-2">{{ __('messages.legal.privacy.layer1.description', [], $legalLocale) }}</p>
        <p class="text-muted mb-3 small">{{ __('messages.legal.privacy.layer1.sync_description', [], $legalLocale) }}</p>
        <div class="row g-3">
                <div class="col-12">
                    <input type="hidden" name="privacy_sync_controller_name" value="0" />
                    <div class="form-check mb-2 privacy-sync-row">
                        <input
                            class="form-check-input privacy-layer-sync-toggle"
                            type="checkbox"
                            id="privacy_sync_controller_name"
                            name="privacy_sync_controller_name"
                            value="1"
                            data-target="privacy_controller_name"
                            data-source-value="{{ (string) ($layer1ImprintDefaults['controller_name'] ?? '') }}"
                            @checked(old('privacy_sync_controller_name', $isSynced('controller_name')))
                        />
                        <label class="form-check-label" for="privacy_sync_controller_name">
                            {{ __('messages.legal.privacy.layer1.sync_from_imprint', [], $legalLocale) }}
                        </label>
                    </div>
                    <div class="small text-muted mb-2 privacy-source-indicator" data-sync-toggle="privacy_sync_controller_name">{{ $syncSourceText('controller_name') }}</div>
                    <label for="privacy_controller_name" class="form-label">{{ __('messages.legal.privacy.layer1.label.controller_name', [], $legalLocale) }}</label>
                    <input
                        type="text"
                        class="form-control"
                        id="privacy_controller_name"
                        name="privacy_controller_name"
                        value="{{ old('privacy_controller_name', (string) ($layer1['controller_name'] ?? '')) }}"
                        data-required-when-unsynced="1"
                        @if(old('privacy_sync_controller_name', $isSynced('controller_name'))) readonly @endif
                    />
                </div>
                <div class="col-12">
                    <input type="hidden" name="privacy_sync_street" value="0" />
                    <div class="form-check mb-2 privacy-sync-row">
                        <input
                            class="form-check-input privacy-layer-sync-toggle"
                            type="checkbox"
                            id="privacy_sync_street"
                            name="privacy_sync_street"
                            value="1"
                            data-target="privacy_street"
                            data-source-value="{{ (string) ($layer1ImprintDefaults['street'] ?? '') }}"
                            @checked(old('privacy_sync_street', $isSynced('street')))
                        />
                        <label class="form-check-label" for="privacy_sync_street">
                            {{ __('messages.legal.privacy.layer1.sync_from_imprint', [], $legalLocale) }}
                        </label>
                    </div>
                    <div class="small text-muted mb-2 privacy-source-indicator" data-sync-toggle="privacy_sync_street">{{ $syncSourceText('street') }}</div>
                    <label for="privacy_street" class="form-label">{{ __('messages.legal.privacy.layer1.label.street', [], $legalLocale) }}</label>
                    <input
                        type="text"
                        class="form-control"
                        id="privacy_street"
                        name="privacy_street"
                        value="{{ old('privacy_street', (string) ($layer1['street'] ?? '')) }}"
                        data-required-when-unsynced="1"
                        @if(old('privacy_sync_street', $isSynced('street'))) readonly @endif
                    />
                </div>
                <div class="col-md-4">
                    <input type="hidden" name="privacy_sync_postal_code" value="0" />
                    <div class="form-check mb-2 privacy-sync-row">
                        <input
                            class="form-check-input privacy-layer-sync-toggle"
                            type="checkbox"
                            id="privacy_sync_postal_code"
                            name="privacy_sync_postal_code"
                            value="1"
                            data-target="privacy_postal_code"
                            data-source-value="{{ (string) ($layer1ImprintDefaults['postal_code'] ?? '') }}"
                            @checked(old('privacy_sync_postal_code', $isSynced('postal_code')))
                        />
                        <label class="form-check-label" for="privacy_sync_postal_code">
                            {{ __('messages.legal.privacy.layer1.sync_from_imprint', [], $legalLocale) }}
                        </label>
                    </div>
                    <div class="small text-muted mb-2 privacy-source-indicator" data-sync-toggle="privacy_sync_postal_code">{{ $syncSourceText('postal_code') }}</div>
                    <label for="privacy_postal_code" class="form-label">{{ __('messages.legal.privacy.layer1.label.postal_code', [], $legalLocale) }}</label>
                    <input
                        type="text"
                        class="form-control"
                        id="privacy_postal_code"
                        name="privacy_postal_code"
                        value="{{ old('privacy_postal_code', (string) ($layer1['postal_code'] ?? '')) }}"
                        data-required-when-unsynced="1"
                        @if(old('privacy_sync_postal_code', $isSynced('postal_code'))) readonly @endif
                    />
                </div>
                <div class="col-md-8">
                    <input type="hidden" name="privacy_sync_city" value="0" />
                    <div class="form-check mb-2 privacy-sync-row">
                        <input
                            class="form-check-input privacy-layer-sync-toggle"
                            type="checkbox"
                            id="privacy_sync_city"
                            name="privacy_sync_city"
                            value="1"
                            data-target="privacy_city"
                            data-source-value="{{ (string) ($layer1ImprintDefaults['city'] ?? '') }}"
                            @checked(old('privacy_sync_city', $isSynced('city')))
                        />
                        <label class="form-check-label" for="privacy_sync_city">
                            {{ __('messages.legal.privacy.layer1.sync_from_imprint', [], $legalLocale) }}
                        </label>
                    </div>
                    <div class="small text-muted mb-2 privacy-source-indicator" data-sync-toggle="privacy_sync_city">{{ $syncSourceText('city') }}</div>
                    <label for="privacy_city" class="form-label">{{ __('messages.legal.privacy.layer1.label.city', [], $legalLocale) }}</label>
                    <input
                        type="text"
                        class="form-control"
                        id="privacy_city"
                        name="privacy_city"
                        value="{{ old('privacy_city', (string) ($layer1['city'] ?? '')) }}"
                        data-required-when-unsynced="1"
                        @if(old('privacy_sync_city', $isSynced('city'))) readonly @endif
                    />
                </div>
                <div class="col-12">
                    <input type="hidden" name="privacy_sync_country" value="0" />
                    <div class="form-check mb-2 privacy-sync-row">
                        <input
                            class="form-check-input privacy-layer-sync-toggle"
                            type="checkbox"
                            id="privacy_sync_country"
                            name="privacy_sync_country"
                            value="1"
                            data-target="privacy_country"
                            data-source-value="{{ (string) ($layer1ImprintDefaults['country'] ?? '') }}"
                            @checked(old('privacy_sync_country', $isSynced('country')))
                        />
                        <label class="form-check-label" for="privacy_sync_country">
                            {{ __('messages.legal.privacy.layer1.sync_from_imprint', [], $legalLocale) }}
                        </label>
                    </div>
                    <div class="small text-muted mb-2 privacy-source-indicator" data-sync-toggle="privacy_sync_country">{{ $syncSourceText('country') }}</div>
                    <label for="privacy_country" class="form-label">{{ __('messages.legal.privacy.layer1.label.country', [], $legalLocale) }}</label>
                    <input
                        type="text"
                        class="form-control"
                        id="privacy_country"
                        name="privacy_country"
                        value="{{ old('privacy_country', (string) ($layer1['country'] ?? 'Deutschland')) }}"
                        data-required-when-unsynced="1"
                        @if(old('privacy_sync_country', $isSynced('country'))) readonly @endif
                    />
                </div>
                <div class="col-md-6">
                    <input type="hidden" name="privacy_sync_email" value="0" />
                    <div class="form-check mb-2 privacy-sync-row">
                        <input
                            class="form-check-input privacy-layer-sync-toggle"
                            type="checkbox"
                            id="privacy_sync_email"
                            name="privacy_sync_email"
                            value="1"
                            data-target="privacy_email"
                            data-source-value="{{ (string) ($layer1ImprintDefaults['email'] ?? '') }}"
                            @checked(old('privacy_sync_email', $isSynced('email')))
                        />
                        <label class="form-check-label" for="privacy_sync_email">
                            {{ __('messages.legal.privacy.layer1.sync_from_imprint', [], $legalLocale) }}
                        </label>
                    </div>
                    <div class="small text-muted mb-2 privacy-source-indicator" data-sync-toggle="privacy_sync_email">{{ $syncSourceText('email') }}</div>
                    <label for="privacy_email" class="form-label">{{ __('messages.legal.privacy.layer1.label.email', [], $legalLocale) }}</label>
                    <input
                        type="email"
                        class="form-control"
                        id="privacy_email"
                        name="privacy_email"
                        value="{{ old('privacy_email', (string) ($layer1['email'] ?? '')) }}"
                        data-required-when-unsynced="1"
                        @if(old('privacy_sync_email', $isSynced('email'))) readonly @endif
                    />
                </div>
                <div class="col-md-6">
                    <input type="hidden" name="privacy_sync_phone" value="0" />
                    <div class="form-check mb-2 privacy-sync-row">
                        <input
                            class="form-check-input privacy-layer-sync-toggle"
                            type="checkbox"
                            id="privacy_sync_phone"
                            name="privacy_sync_phone"
                            value="1"
                            data-target="privacy_phone"
                            data-source-value="{{ (string) ($layer1ImprintDefaults['phone'] ?? '') }}"
                            @checked(old('privacy_sync_phone', $isSynced('phone')))
                        />
                        <label class="form-check-label" for="privacy_sync_phone">
                            {{ __('messages.legal.privacy.layer1.sync_from_imprint', [], $legalLocale) }}
                        </label>
                    </div>
                    <div class="small text-muted mb-2 privacy-source-indicator" data-sync-toggle="privacy_sync_phone">{{ $syncSourceText('phone') }}</div>
                    <label for="privacy_phone" class="form-label">{{ __('messages.legal.privacy.layer1.label.phone_optional', [], $legalLocale) }}</label>
                    <input
                        type="text"
                        class="form-control"
                        id="privacy_phone"
                        name="privacy_phone"
                        value="{{ old('privacy_phone', (string) ($layer1['phone'] ?? '')) }}"
                        @if(old('privacy_sync_phone', $isSynced('phone'))) readonly @endif
                    />
                </div>
                <div class="col-md-6">
                    <label for="privacy_dpo_name" class="form-label">{{ __('messages.legal.privacy.layer1.label.dpo_name_optional', [], $legalLocale) }}</label>
                    <input type="text" class="form-control" id="privacy_dpo_name" name="privacy_dpo_name" value="{{ old('privacy_dpo_name', (string) ($layer1['dpo_name'] ?? '')) }}" />
                </div>
                <div class="col-md-6">
                    <label for="privacy_dpo_contact" class="form-label">{{ __('messages.legal.privacy.layer1.label.dpo_contact_optional', [], $legalLocale) }}</label>
                    <input type="text" class="form-control" id="privacy_dpo_contact" name="privacy_dpo_contact" value="{{ old('privacy_dpo_contact', (string) ($layer1['dpo_contact'] ?? '')) }}" />
                </div>
        </div>
    </div>
</div>

<script>
    (() => {
        const sourceSyncedText = @json(__('messages.legal.privacy.layer1.source.imprint', [], $legalLocale));
        const sourceCustomText = @json(__('messages.legal.privacy.layer1.source.custom', [], $legalLocale));

        const applySyncState = (toggle) => {
            const targetId = toggle.dataset.target;
            const target = document.getElementById(targetId);
            if (!target) {
                return;
            }

            const isSynced = toggle.checked;
            if (isSynced) {
                const sourceValue = toggle.dataset.sourceValue ?? '';
                target.value = sourceValue;
            }

            target.readOnly = isSynced;
            target.classList.toggle('bg-light', isSynced);
            if (target.dataset.requiredWhenUnsynced === '1') {
                target.required = !isSynced;
            }

            const sourceIndicator = document.querySelector(`[data-sync-toggle="${toggle.id}"]`);
            if (sourceIndicator) {
                sourceIndicator.textContent = isSynced ? sourceSyncedText : sourceCustomText;
            }
        };

        const bootstrap = () => {
            document.querySelectorAll('.privacy-layer-sync-toggle').forEach((toggle) => {
                applySyncState(toggle);
                toggle.addEventListener('change', () => applySyncState(toggle));
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootstrap, {once: true});
        } else {
            bootstrap();
        }
    })();
</script>
