@php
    $captchaVerifier = app(\App\Services\Security\CaptchaVerifier::class);
    $captchaProvider = $captchaVerifier->provider();
    $captchaSiteKey = $captchaVerifier->siteKey($context ?? null);
    $turnstileAction = isset($context) ? $captchaVerifier->turnstileAction($context) : null;
    $deferCaptchaLoad = isset($deferCaptchaLoad) && $deferCaptchaLoad === true;
@endphp

@if($captchaProvider && $captchaSiteKey)
<div class="col-lg-12 mb-3">
  @if($captchaProvider === 'hcaptcha')
    <div class="h-captcha" data-sitekey="{{ $captchaSiteKey }}"></div>
    <script src="https://hcaptcha.com/1/api.js" async defer></script>
  @elseif($captchaProvider === 'turnstile')
    @if($deferCaptchaLoad)
      <div
        data-captcha-deferred-root
        hidden
      >
        <div
          data-captcha-turnstile-container
          data-sitekey="{{ $captchaSiteKey }}"
          @if($turnstileAction) data-action="{{ $turnstileAction }}" @endif
        ></div>
      </div>
    @else
      <div
        class="cf-turnstile"
        data-sitekey="{{ $captchaSiteKey }}"
        @if($turnstileAction) data-action="{{ $turnstileAction }}" @endif
      ></div>
      <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
  @else
    <div class="g-recaptcha" data-sitekey="{{ $captchaSiteKey }}"></div>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  @endif
</div>
@endif
