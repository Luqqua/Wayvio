@php
    $layer3 = (array) ($privacyLayerModel['layer3'] ?? []);
    $embedModeValue = old('privacy_embed_mode', (string) ($layer3['embed_mode'] ?? 'auto'));
    $legalLocale = $privacyLocale ?? app()->getLocale();
@endphp

<div class="card border imprint-card">
    <div class="card-body">
        <h5 class="mb-2">{{ __('messages.legal.privacy.layer3.title', [], $legalLocale) }}</h5>
        <p class="text-muted mb-3">{{ __('messages.legal.privacy.layer3.description', [], $legalLocale) }}</p>

        <div class="form-check mb-3">
                <input class="form-check-input" type="radio" name="privacy_embed_mode" id="privacy_embed_mode_auto" value="auto" @checked($embedModeValue === 'auto')>
                <label class="form-check-label" for="privacy_embed_mode_auto">
                    {{ __('messages.legal.privacy.layer3.option.auto', [], $legalLocale) }}
                </label>
            </div>

            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="privacy_embed_mode" id="privacy_embed_mode_self" value="self" @checked($embedModeValue === 'self')>
                <label class="form-check-label" for="privacy_embed_mode_self">
                    {{ __('messages.legal.privacy.layer3.option.self', [], $legalLocale) }}
                </label>
            </div>

    </div>
</div>
