{{-- MODULE: Public pricing page with Stripe checkout CTAs --}}
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ env('APP_NAME') }} {{ __('Pricing') }}</title>
  <link rel="stylesheet" href="{{ asset('assets/css/core/libs.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/hope-ui.min.css?v=2.0.0') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/custom.min.css?v=2.0.0') }}">
  <link rel="stylesheet" href="{{ asset('assets/external-dependencies/bootstrap-icons.css') }}">
  <style>
    body {background: #0d0f1a; color: #e6ecff;}
    .hero {padding: 60px 0;}
    .card-plan {border: 1px solid rgba(255,255,255,0.06); background: rgba(17,24,43,0.8); color: #e6ecff; border-radius: 16px;}
    .card-plan .price {font-size: 32px; font-weight: 700;}
    .feature {display: flex; align-items: center; gap: 8px;}
    .btn-ghost {border: 1px solid #3b5bdb; color: #e6ecff;}
  </style>
</head>
<body>
  <div class="container hero">
    <div class="text-center mb-5">
      <h1 class="mb-2">{{ __('Choose your plan') }}</h1>
      <p class="text-muted">{{ __('Start free, then unlock analytics, custom domains, and premium support with a monthly Stripe subscription you can cancel every month.') }}</p>
    </div>

    <div class="row g-3">
      @foreach($tiers as $tier)
        @php
          $isFree = ($tier->slug ?? '') === 'free';
          $price = $tier->price_1m ? number_format($tier->price_1m/100, 2) : '0.00';
          $isTop = ($tier->slug ?? '') === config('tiers.admin_tier_slug', 'business');
          $isAgency = ($tier->slug ?? '') === 'agency';
          $agencyIncludedHubs = max(2, (int) ($tier->included_hubs ?? 2));
          $agencyExtraHubPrice = max(0, (int) ($tier->extra_hub_price_1m ?? 900));
        @endphp
        <div class="col-md-4">
          <div class="card card-plan p-3 h-100">
            <div class="d-flex justify-content-between align-items-center">
              <h4 class="mb-0">{{ $tier->name }}</h4>
              @if($isTop) <span class="badge bg-primary">{{ __('Best value') }}</span> @endif
            </div>
            <p class="text-muted small mt-2">{{ $tier->description }}</p>
            <div class="price mb-1">${{ $price }} <span class="text-muted small">{{ __('/mo') }}</span></div>
            @if($isAgency)
              <div class="text-muted small mb-3">{{ __('Includes :included hubs, then +$:price per extra hub up to 10 total. Need more? Contact support.', ['included' => $agencyIncludedHubs, 'price' => number_format($agencyExtraHubPrice / 100, 2)]) }}</div>
            @else
              <div class="mb-3"></div>
            @endif
            <div class="mb-3">
              <div class="feature"><i class="bi bi-check-circle text-success"></i> {{ __('Pages:') }} {{ $tier->max_pages ?? 1 }}</div>
              <div class="feature"><i class="bi bi-check-circle text-success"></i> {{ __('Links per page:') }} {{ $tier->max_links_per_page ?? 10 }}</div>
              <div class="feature"><i class="bi bi-check-circle {{ $tier->analytics_enabled ? 'text-success' : 'text-muted' }}"></i> {{ __('Analytics') }}</div>
              @if($tier->analytics_enabled)
                <div class="feature"><i class="bi bi-clock-history text-success"></i> {{ __('Analytics history:') }} {{ $tier->analytics_history_days ? __(':days days', ['days' => $tier->analytics_history_days]) : __('N/A') }}</div>
              @endif
              <div class="feature"><i class="bi bi-check-circle {{ $tier->custom_domain_enabled ? 'text-success' : 'text-muted' }}"></i> {{ __('Custom domains') }}</div>
            </div>
            @if($isFree)
              <a class="btn btn-ghost w-100" href="{{ url('/register') }}">{{ __('Start free') }}</a>
            @elseif($tier->id)
              <button class="btn btn-primary w-100 checkout-btn" data-tier="{{ $tier->id }}">{{ __('Start :plan monthly', ['plan' => $tier->name]) }}</button>
            @else
              <div class="alert alert-warning mb-0">{{ __('Plan :slug is not synchronized. Please run CLI sync.', ['slug' => $tier->slug]) }}</div>
            @endif
          </div>
        </div>
      @endforeach
    </div>

    <div class="alert alert-danger mt-4 d-none" id="pricing-errors"></div>
  </div>

  <script>
    (() => {
      const errorBox = document.getElementById('pricing-errors');
      const showError = (msg) => {
        errorBox.textContent = msg || @json(__('Unable to start checkout.'));
        errorBox.classList.remove('d-none');
      };
      document.querySelectorAll('.checkout-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
          try {
            const res = await fetch("{{ route('checkout.session') }}", {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
              },
              body: JSON.stringify({ tier_id: btn.dataset.tier })
            });
            const data = await res.json();
            if (!res.ok || !data.url) throw new Error(data.error || @json(__('Checkout failed.')));
            window.location.href = data.url;
          } catch (e) {
            showError(e.message);
          }
        });
      });
    })();
  </script>
</body>
</html>
