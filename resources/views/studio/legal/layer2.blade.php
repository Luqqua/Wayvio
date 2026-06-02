@php
    $layer2 = (array) ($privacyLayerModel['layer2'] ?? []);
    $legalLocale = $privacyLocale ?? app()->getLocale();
    $layer2TextValue = old(
        'privacy_user_text',
        (string) ($privacyEditableText ?? ($layer2['user_privacy_text'] ?? ''))
    );
@endphp

<div class="card border imprint-card">
    <div class="card-body">
        <h5 class="mb-2">{{ __('messages.legal.privacy.layer2.title', [], $legalLocale) }}</h5>
        <p class="text-muted mb-3">{{ __('messages.legal.privacy.layer2.description', [], $legalLocale) }}</p>
        <div class="alert alert-info mb-3" role="alert">
            <strong>{{ __('messages.legal.privacy.layer2.notice_title', [], $legalLocale) }}</strong>
            {{ __('messages.legal.privacy.layer2.notice_text', [], $legalLocale) }}
            <a href="#privacy-layer1" class="alert-link">{{ __('messages.legal.privacy.layer2.notice_cta', [], $legalLocale) }}</a>
        </div>

        <label for="privacy_user_text" class="form-label">{{ __('messages.legal.privacy.layer2.label.privacy_text_section2', [], $legalLocale) }}</label>
        <textarea
            class="form-control"
            id="privacy_user_text"
            name="privacy_user_text"
            rows="14"
        >{{ $layer2TextValue }}</textarea>

        <div class="pt-3 d-flex flex-wrap gap-2">
            <button type="submit" name="layer2_action" value="regenerate" class="btn btn-outline-secondary">{{ __('messages.legal.privacy.layer2.action.regenerate', [], $legalLocale) }}</button>
        </div>
        <p class="text-muted small mt-2 mb-0">{{ __('messages.legal.privacy.layer2.locale_hint', [], $legalLocale) }}</p>
    </div>
</div>
