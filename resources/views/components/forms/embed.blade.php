@props([
    'formKey' => null,
    'hub' => null,
    'context' => null,
    'title' => null,
    'description' => null,
])

@php
    $formKey = app(\App\Services\Forms\FormCatalog::class)->normalizeKey((string) ($formKey ?? ''));
    $catalog = app(\App\Services\Forms\FormCatalog::class);
    $definition = $catalog->get($formKey);
    $hubModel = $hub ?? null;
    $context = (string) ($context ?? ($definition['source_context'] ?? ''));
    $formsAccess = app(\App\Services\Forms\FormsAccess::class);
    $botProtectionVerifier = app(\App\Services\Forms\FormsBotProtectionVerifier::class);
    $captchaContext = 'forms_' . $formKey;
    $captchaProvider = $botProtectionVerifier->provider();
    $isStudioPreview = request()->boolean('studio_preview');
    $skipCaptchaForPreview = $captchaProvider === 'turnstile' && $isStudioPreview;
    $hasRenderableContext = $definition
        && $hubModel instanceof \App\Models\User
        && $context === ($definition['source_context'] ?? null);
    $formsAllowedForHub = $hasRenderableContext ? $formsAccess->formsAllowedForHub($hubModel) : false;
    $canRender = $hasRenderableContext && $formsAllowedForHub;
    $isTierLocked = $hasRenderableContext && !$formsAllowedForHub;
    $honeypotField = (string) config('forms.honeypot_field', 'wayvio_company');
    $formDomId = 'wayvio-form-' . $formKey . '-' . ($hubModel?->id ?? '0') . '-' . substr(hash('sha256', (string) microtime(true)), 0, 8);
    $customTitle = trim(strip_tags((string) ($title ?? '')));
    $customDescription = trim(strip_tags((string) ($description ?? '')));
    $profileUrl = null;
    $profileOwner = null;
    if ($hubModel instanceof \App\Models\User) {
        $domainResolver = app(\App\Services\Domains\DomainUrlResolver::class);
        $profileOwner = $domainResolver->ownerForPageUser($hubModel);
        $profileUrl = $domainResolver->profileUrlForEditor($profileOwner, $hubModel);
    }
    $privacyUrl = $profileUrl ? rtrim($profileUrl, '/') . '/privacy' : '#';
    $formLocale = trim((string) app()->getLocale());
    $hubLocale = $hubModel instanceof \App\Models\User ? trim((string) ($hubModel->locale ?? '')) : '';
    $ownerLocale = $profileOwner instanceof \App\Models\User ? trim((string) ($profileOwner->locale ?? '')) : '';
    if ($context !== 'imprint') {
        if ($hubLocale !== '') {
            $formLocale = $hubLocale;
        } elseif ($ownerLocale !== '') {
            $formLocale = $ownerLocale;
        }
    }
    if ($formLocale === '') {
        $formLocale = trim((string) config('app.fallback_locale', 'en'));
    }
    if ($formLocale === '') {
        $formLocale = 'en';
    }
    $resolvedTitle = $formKey === 'imprint_contact'
        ? __('messages.block.title.hub_contact_form', [], $formLocale)
        : ($customTitle !== '' ? $customTitle : __($definition['title'] ?? 'Contact', [], $formLocale));
    $resolvedDescription = $formKey === 'imprint_contact'
        ? ''
        : $customDescription;
    $nameLabel = trans('validation.attributes.name', [], $formLocale);
    $emailLabel = trans('validation.attributes.email', [], $formLocale);
    $subjectLabel = trans('validation.attributes.subject', [], $formLocale);
    $messageLabel = trans('validation.attributes.message', [], $formLocale);
    if (!is_string($nameLabel) || trim($nameLabel) === '' || $nameLabel === 'validation.attributes.name') {
        $nameLabel = __('Name', [], $formLocale);
    }
    if (!is_string($emailLabel) || trim($emailLabel) === '' || $emailLabel === 'validation.attributes.email') {
        $emailLabel = __('Email', [], $formLocale);
    }
    if (!is_string($subjectLabel) || trim($subjectLabel) === '' || $subjectLabel === 'validation.attributes.subject') {
        $subjectLabel = __('Subject', [], $formLocale);
    }
    if (!is_string($messageLabel) || trim($messageLabel) === '' || $messageLabel === 'validation.attributes.message') {
        $messageLabel = __('Message', [], $formLocale);
    }
    $tierLockedMessage = __('messages.forms.tier_locked.basic', [], $formLocale);
@endphp

@if($canRender)
  <section class="wayvio-form-embed" aria-labelledby="{{ $formDomId }}-title">
    <h2 id="{{ $formDomId }}-title" class="wayvio-form-title">{{ $resolvedTitle }}</h2>
    @if($resolvedDescription !== '')
      <p class="wayvio-form-description">{{ $resolvedDescription }}</p>
    @endif

    @if(session('forms_success_' . $formKey))
      <div class="wayvio-form-alert wayvio-form-alert-success">{{ session('forms_success_' . $formKey) }}</div>
    @elseif(isset($errors) && $errors->any())
      <div class="wayvio-form-alert wayvio-form-alert-error">{{ $errors->first() }}</div>
    @endif

    <form
      method="POST"
      action="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('forms.submit', now()->addHour(), ['hub' => $hubModel->id, 'formKey' => $formKey], false) }}"
      autocomplete="off"
      data-autofill-allow="true"
      novalidate
    >
      <input type="hidden" name="source_context" value="{{ $context }}">
      <input type="hidden" name="_forms_started_at" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) time()) }}">
      <div class="wayvio-form-honeypot" aria-hidden="true">
        <label for="{{ $formDomId }}-hp">Company</label>
        <input id="{{ $formDomId }}-hp" type="text" name="{{ $honeypotField }}" tabindex="-1" autocomplete="off">
      </div>

      <div class="wayvio-form-row">
        <label for="{{ $formDomId }}-name">{{ $nameLabel }}</label>
        <input id="{{ $formDomId }}-name" type="text" name="name" value="{{ old('name') }}" maxlength="120" autocomplete="name">
      </div>

      <div class="wayvio-form-row">
        <label for="{{ $formDomId }}-email">{{ $emailLabel }}*</label>
        <input id="{{ $formDomId }}-email" type="text" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" inputmode="email" autocapitalize="none" autocorrect="off" spellcheck="false" aria-required="true">
      </div>

      <div class="wayvio-form-row">
        <label for="{{ $formDomId }}-subject">{{ $subjectLabel }}</label>
        <input id="{{ $formDomId }}-subject" type="text" name="subject" value="{{ old('subject') }}" maxlength="160">
      </div>

      <div class="wayvio-form-row">
        <label for="{{ $formDomId }}-message">{{ $messageLabel }}*</label>
        <textarea id="{{ $formDomId }}-message" name="message" maxlength="4000" rows="5" required>{{ old('message') }}</textarea>
      </div>

      <p class="wayvio-form-consent">
        {{ __('By submitting, you accept our', [], $formLocale) }}
        <a href="{{ $privacyUrl }}">{{ __('privacy policy', [], $formLocale) }}</a>.
        {{ __('Your data will be used exclusively to process your request.', [], $formLocale) }}
      </p>

      @if(!$skipCaptchaForPreview)
        @if($captchaProvider === 'cap')
          @include('components.forms.cap')
        @elseif($captchaProvider === 'turnstile')
          @include('auth.partials.captcha', ['context' => $captchaContext])
        @endif
      @endif

      <button type="submit" class="wayvio-form-submit">
        <span class="wayvio-form-submit-label">{{ __($definition['submit_label'] ?? 'Submit', [], $formLocale) }}</span>
        <span class="wayvio-form-submit-spinner" aria-hidden="true"></span>
      </button>
    </form>
  </section>

  @once
    <style>
      .wayvio-form-embed {
        width: min(100%, 520px);
        margin: 28px auto 0;
        padding-top: 22px;
        border-top: 1px solid rgba(0, 0, 0, 0.08);
        color: #222;
        text-align: left;
      }
      .wayvio-form-title {
        margin: 0 0 6px;
        font-size: var(--ls-type-section-size, 18px);
        line-height: 1.25;
        font-weight: 650;
      }
      .wayvio-form-description {
        margin: 0 0 16px;
        color: #555;
        font-size: 14px;
        line-height: 1.5;
      }
      .wayvio-form-row {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin-bottom: 12px;
      }
      .wayvio-form-row label,
      .wayvio-form-consent {
        font-size: 14px;
        line-height: 1.4;
        font-weight: 600;
      }
      .wayvio-form-row input,
      .wayvio-form-row textarea {
        width: 100%;
        box-sizing: border-box;
        border: 1px solid rgba(0, 0, 0, 0.18);
        border-radius: 8px;
        padding: 10px 12px;
        background: #fff;
        color: #111;
        font: inherit;
      }
      .wayvio-form-row textarea {
        resize: vertical;
        min-height: 120px;
      }
      .wayvio-form-consent {
        margin: 0 0 12px;
        font-weight: 500;
      }
      .wayvio-form-consent a {
        color: inherit;
        text-decoration: underline;
      }
      .wayvio-form-embed .col-lg-12.mb-3 {
        margin: 0 0 12px !important;
        padding: 0;
      }
      .wayvio-form-submit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 42px;
        border: 0;
        border-radius: 8px;
        padding: 10px 16px;
        background: #111;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
      }
      .wayvio-form-submit-spinner {
        display: none;
        width: 14px;
        height: 14px;
        border: 2px solid rgba(255, 255, 255, 0.35);
        border-top-color: #ffffff;
        border-radius: 50%;
        animation: wayvio-form-spin 0.8s linear infinite;
      }
      .wayvio-form-submit.is-loading .wayvio-form-submit-spinner {
        display: inline-block;
      }
      .wayvio-form-submit.is-loading {
        cursor: wait;
      }
      @keyframes wayvio-form-spin {
        to {
          transform: rotate(360deg);
        }
      }
      .wayvio-form-alert {
        margin-bottom: 14px;
        border-radius: 8px;
        padding: 10px 12px;
        font-size: 14px;
        line-height: 1.4;
      }
      .wayvio-form-alert-success {
        background: #e8f8ef;
        color: #155b31;
      }
      .wayvio-form-alert-error {
        background: #fff1e8;
        color: #8a2f0a;
      }
      .wayvio-form-honeypot {
        position: absolute;
        left: -10000px;
        width: 1px;
        height: 1px;
        overflow: hidden;
      }
    </style>
  @endonce

  @once
    <script>
      (function () {
        var forms = document.querySelectorAll('.wayvio-form-embed form');
        if (!forms.length) {
          return;
        }

        forms.forEach(function (form) {
          if (form.getAttribute('data-wayvio-submit-spinner-bound') === '1') {
            return;
          }
          form.setAttribute('data-wayvio-submit-spinner-bound', '1');

          form.addEventListener('submit', function () {
            var submitButton = form.querySelector('button[type="submit"]');
            if (!submitButton) {
              return;
            }
            submitButton.classList.add('is-loading');
            submitButton.setAttribute('aria-busy', 'true');
            submitButton.disabled = true;
          });
        });
      })();
    </script>
  @endonce
@elseif($isTierLocked)
  <section class="wayvio-form-locked" aria-live="polite">
    <h2 class="wayvio-form-locked-title">{{ $resolvedTitle }}</h2>
    <p class="wayvio-form-locked-message">{{ $tierLockedMessage }}</p>
  </section>

  @once
    <style>
      .wayvio-form-locked {
        width: min(100%, 520px);
        margin: 28px auto 0;
        padding: 14px 16px;
        border-radius: 12px;
        border: 1px solid var(--ls-form-field-border, rgba(0, 0, 0, 0.22));
        background: var(--ls-form-field-bg, rgba(0, 0, 0, 0.05));
        color: var(--ls-form-text, #222);
        text-align: left;
      }
      .wayvio-form-locked-title {
        margin: 0 0 6px;
        font-size: var(--ls-type-section-size, 18px);
        line-height: 1.25;
        font-weight: 650;
        color: inherit;
      }
      .wayvio-form-locked-message {
        margin: 0;
        color: var(--ls-form-muted, #555);
        font-size: var(--ls-type-small-size, 14px);
        line-height: 1.5;
      }
    </style>
  @endonce
@endif
