@extends('layouts.sidebar')

@php
  $metaEditLocked = !($canCustomize ?? false);
  $metaRequiredTierLabel = (string) ($requiredTierLabel ?? 'Pro');
  $subscriptionDashboardUrl = url('/dashboard/subscription');
@endphp

@section('content')
<style>
  .ls-meta-sections {
    border-top: 1px solid #eef1f4;
  }

  .ls-meta-section {
    padding: 18px 0;
  }

  .ls-meta-section + .ls-meta-section {
    border-top: 1px solid #eef1f4;
  }

  .ls-meta-section-title {
    margin-bottom: 12px;
  }

  .ls-meta-footer {
    border-top: 1px solid #eef1f4;
    margin-top: 18px;
    padding-top: 16px;
  }
</style>

<div class="conatiner-fluid content-inner mt-n5 pt-0 pb-4 ls-consistent-spacing">
  <div class="row">
    <div class="col-lg-12">
      <div class="card rounded">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
              <h4 class="mb-1">
                {{ __('Meta tags') }}
                @if($metaEditLocked)
                  <span class="badge bg-secondary ms-2">{{ __('Locked') }}</span>
                @endif
              </h4>
              <p class="mb-0 text-muted">{{ __('Override SEO and social meta for your page.') }}</p>
            </div>
          </div>

          @if($metaEditLocked)
            <div class="alert alert-warning">
              {{ __('Available from :tier.', ['tier' => $metaRequiredTierLabel]) }}
              @if($hasStoredOverrides ?? false)
                <div class="mt-1 mb-0 small">{{ __('Saved Pro meta values remain stored internally and are published again only after an upgrade.') }}</div>
              @endif
            </div>
          @endif

          @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
          @endif

          @if($errors->any())
            <div class="alert alert-danger">
              <ul class="mb-0">
                @foreach ($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          <form id="meta-save-form" method="POST" action="{{ route('meta.save') }}" class="d-none" @if($metaEditLocked) data-meta-upsell-form @endif>
            @csrf
          </form>

            <div class="ls-meta-sections">
              <section class="ls-meta-section">
                <h6 class="ls-meta-section-title">{{ __('SEO basics') }}</h6>
                <div class="mb-3">
                  <label class="form-label">{{ __('Page title') }}</label>
                  <input type="text" name="title" class="form-control" form="meta-save-form" maxlength="150" value="{{ old('title', $formValues['title'] ?? '') }}" placeholder="{{ $defaults['title'] }}" {{ $metaEditLocked ? 'disabled' : '' }}>
                  <small class="text-muted">{{ __('Leave blank to use the default pattern.') }}</small>
                </div>

                <div class="mb-3">
                  <label class="form-label">{{ __('Description') }}</label>
                  <textarea name="description" class="form-control" form="meta-save-form" rows="3" maxlength="300" placeholder="{{ $defaults['description'] }}" {{ $metaEditLocked ? 'disabled' : '' }}>{{ old('description', $formValues['description'] ?? '') }}</textarea>
                </div>

                <div class="mb-0">
                  <label class="form-label">{{ __('SEO keywords') }}</label>
                  <input type="text" name="keywords" class="form-control" form="meta-save-form" maxlength="300" value="{{ old('keywords', $formValues['keywords'] ?? '') }}" placeholder="{{ $defaults['keywords'] }}" {{ $metaEditLocked ? 'disabled' : '' }}>
                  <small class="text-muted">{{ __('Comma-separated keywords.') }}</small>
                </div>
              </section>

              <section class="ls-meta-section">
                <h6 class="ls-meta-section-title">{{ __('Social sharing') }}</h6>
                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">{{ __('Open Graph title') }}</label>
                      <input type="text" name="og_title" class="form-control" form="meta-save-form" maxlength="150" value="{{ old('og_title', $formValues['og_title'] ?? '') }}" placeholder="{{ $defaults['title'] }}" {{ $metaEditLocked ? 'disabled' : '' }}>
                      <small class="text-muted">{{ __('If empty, uses page title.') }}</small>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-3">
                      <label class="form-label">{{ __('Open Graph description') }}</label>
                      <input type="text" name="og_description" class="form-control" form="meta-save-form" maxlength="300" value="{{ old('og_description', $formValues['og_description'] ?? '') }}" placeholder="{{ $defaults['description'] }}" {{ $metaEditLocked ? 'disabled' : '' }}>
                      <small class="text-muted">{{ __('If empty, uses description.') }}</small>
                    </div>
                  </div>
                </div>

                <div class="row">
                  <div class="col-md-6">
                    <div class="mb-0">
                      <label class="form-label">{{ __('Twitter card type') }}</label>
                      @php
                        $twitterCardValue = old('twitter_card', $formValues['twitter_card'] ?? '');
                      @endphp
                      <select name="twitter_card" class="form-select" form="meta-save-form" {{ $metaEditLocked ? 'disabled' : '' }}>
                        <option value="" {{ $twitterCardValue === '' ? 'selected' : '' }}>{{ __('Default') }} ({{ $defaults['twitter_card'] }})</option>
                        <option value="summary" {{ $twitterCardValue === 'summary' ? 'selected' : '' }}>summary</option>
                        <option value="summary_large_image" {{ $twitterCardValue === 'summary_large_image' ? 'selected' : '' }}>summary_large_image</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="mb-0 mt-3 mt-md-0">
                      <label class="form-label">{{ __('OG locale') }}</label>
                      <input type="text" name="og_locale" class="form-control" form="meta-save-form" maxlength="20" value="{{ old('og_locale', $formValues['og_locale'] ?? '') }}" placeholder="{{ $defaults['og_locale'] }}" {{ $metaEditLocked ? 'disabled' : '' }}>
                    </div>
                  </div>
                </div>
              </section>

              <section class="ls-meta-section">
                <h6 class="ls-meta-section-title">{{ __('Favicon') }}</h6>
                @if($metaEditLocked)
                  <p class="text-muted mb-0 small">{{ __('Available from :tier.', ['tier' => $metaRequiredTierLabel]) }}</p>
                @else
                  @php
                    $__faviconUrl = userFaviconExists($user->id) ? userFaviconUrl($user->id) : null;
                    $__faviconLimit = config('media.upload_limits.favicon', ['max_kb' => 512, 'max_width' => 256, 'max_height' => 256]);
                    $__faviconLimitMb = max(1, (int) ceil(max(1, (int) ($__faviconLimit['max_kb'] ?? 512)) / 1024));
                  @endphp
                  <div class="border rounded p-3 mb-3" id="favicon-current-preview">
                    <p class="text-uppercase text-muted small mb-2">{{ __('Preview') }}</p>
                    @if($__faviconUrl)
                      <div class="d-flex align-items-center gap-3 flex-wrap">
                        <img src="{{ $__faviconUrl }}" alt="Favicon" style="width:48px;height:48px;object-fit:contain;border:1px solid #dee2e6;border-radius:4px;padding:4px;background:#fff;">
                        <div>
                          <p class="text-muted small mb-2">{{ __('Custom favicon is active.') }}</p>
                          <button type="submit" form="delete-favicon-form" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-trash-fill"></i> {{ __('Remove') }}
                          </button>
                        </div>
                      </div>
                    @else
                      <div class="d-inline-flex align-items-center gap-2 px-3 py-2 border rounded bg-light">
                        <span style="width:32px;height:32px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #dee2e6;border-radius:4px;background:#fff;color:#6c757d;font-size:12px;">ICO</span>
                        <span class="text-muted small">{{ __('No custom favicon uploaded yet.') }}</span>
                      </div>
                    @endif
                  </div>
                  <form action="{{ route('meta.favicon.save') }}" method="POST" enctype="multipart/form-data" id="favicon-upload-form">
                    @csrf
                    @if(request()->query('edit_user'))
                      <input type="hidden" name="edit_user" value="{{ request()->query('edit_user') }}">
                    @endif
                    <div class="mb-3">
                      <label class="form-label">{{ __('Upload favicon') }}</label>
                      <input type="file" name="favicon" id="favicon-file-input" class="form-control" accept="image/png,image/jpeg,image/webp" data-autofill-allow="true">
                      <div id="favicon-preview" class="mt-2" style="display:none;">
                        <img id="favicon-preview-img" src="" alt="{{ __('Preview') }}" style="width:32px;height:32px;object-fit:contain;border:1px solid #dee2e6;border-radius:4px;">
                        <span id="favicon-preview-name" class="ms-2 small text-muted"></span>
                      </div>
                      <small class="text-muted">{{ __('PNG, JPG or WebP · max :mb MB · max 256×256 px', ['mb' => $__faviconLimitMb]) }}</small>
                    </div>
                    <button type="submit" class="btn btn-sm btn-secondary">{{ __('Upload favicon') }}</button>
                  </form>
                  <script>
                  (() => {
                    const fileInput = document.getElementById('favicon-file-input');
                    const preview = document.getElementById('favicon-preview');
                    const previewImg = document.getElementById('favicon-preview-img');
                    const previewName = document.getElementById('favicon-preview-name');
                    if (!fileInput || !preview || !previewImg || !previewName) return;
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
                  })();
                  </script>
                  @if($__faviconUrl)
                    <form id="delete-favicon-form" method="POST" action="{{ route('meta.favicon.delete') }}" class="d-none">
                      @csrf
                      @method('DELETE')
                      @if(request()->query('edit_user'))
                        <input type="hidden" name="edit_user" value="{{ request()->query('edit_user') }}">
                      @endif
                    </form>
                  @endif
                @endif
              </section>

              <section class="ls-meta-section">
                <h6 class="ls-meta-section-title">{{ __('Crawling & canonical') }}</h6>
                <div class="row">
                  <div class="col-md-4">
                    <div class="mb-3 mb-md-0">
                      <label class="form-label">{{ __('Robots') }}</label>
                      @php
                        $robotsValue = old('robots', $formValues['robots'] ?? '');
                      @endphp
                      <select name="robots" class="form-select" form="meta-save-form" {{ $metaEditLocked ? 'disabled' : '' }}>
                        <option value="" {{ $robotsValue === '' ? 'selected' : '' }}>{{ __('Default') }} ({{ $defaults['robots'] }})</option>
                        <option value="index,follow" {{ $robotsValue === 'index,follow' ? 'selected' : '' }}>index,follow</option>
                        <option value="noindex,follow" {{ $robotsValue === 'noindex,follow' ? 'selected' : '' }}>noindex,follow</option>
                        <option value="index,nofollow" {{ $robotsValue === 'index,nofollow' ? 'selected' : '' }}>index,nofollow</option>
                        <option value="noindex,nofollow" {{ $robotsValue === 'noindex,nofollow' ? 'selected' : '' }}>noindex,nofollow</option>
                      </select>
                    </div>
                  </div>

                  <div class="col-md-8">
                    <div class="mb-0 mt-3 mt-md-0">
                      <label class="form-label">{{ __('Canonical URL') }}</label>
                      <input type="text" name="canonical_url" class="form-control" form="meta-save-form" maxlength="255" value="{{ old('canonical_url', $formValues['canonical_url'] ?? '') }}" placeholder="https://example.com/@username" autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false" inputmode="url" aria-autocomplete="none" data-lpignore="true" data-1p-ignore="true" readonly onfocus="this.removeAttribute('readonly')" onpointerdown="this.removeAttribute('readonly')" ontouchstart="this.removeAttribute('readonly')" onblur="this.setAttribute('readonly', 'readonly')" {{ $metaEditLocked ? 'disabled' : '' }}>
                    </div>
                  </div>
                </div>
              </section>
            </div>

            <div class="d-flex justify-content-between align-items-center ls-meta-footer">
              <small class="text-muted">
                @if($metaEditLocked)
                  {{ __('Available from :tier.', ['tier' => $metaRequiredTierLabel]) }}
                @else
                  {{ __('Clear a field to fall back to defaults.') }}
                @endif
              </small>
              @if($metaEditLocked)
                <button type="button" class="btn btn-primary" data-upsell-redirect>{{ __('Upgrade in subscription') }}</button>
              @else
                <button type="submit" form="meta-save-form" class="btn btn-primary">{{ __('Save meta tags') }}</button>
              @endif
            </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@if($metaEditLocked)
@push('sidebar-scripts')
<script>
(() => {
  const subscriptionUrl = @json($subscriptionDashboardUrl);
  const handleRedirect = (event) => {
    event.preventDefault();
    window.location.assign(subscriptionUrl);
  };

  document.querySelectorAll('[data-upsell-redirect]').forEach((button) => {
    button.addEventListener('click', handleRedirect);
  });
})();
</script>
@endpush
@endif
