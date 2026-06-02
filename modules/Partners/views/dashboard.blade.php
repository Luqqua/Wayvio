@extends('layouts.sidebar')

@php
  $summary = $summary ?? [];
  $referrals = collect($referrals ?? []);
  $ledgerRows = collect($ledger ?? []);
  $payoutRows = collect($payouts ?? []);
  $inviteCodes = collect($invite_codes ?? []);
  $timeseries = collect($timeseries ?? []);
  $partnerInfo = $partner ?? [];
  $currency = strtoupper($ledgerRows->first()['currency'] ?? 'USD');
  $approvedCents = (int) ($summary['approved_cents'] ?? 0);
  $pendingCents = (int) ($summary['pending_cents'] ?? 0);
  $pendingHoldCents = (int) ($summary['pending_hold_cents'] ?? 0);
  $pendingSecondPaymentCents = (int) ($summary['pending_second_payment_cents'] ?? 0);
  $pendingSecondPaymentUsers = max(0, (int) ($summary['pending_second_payment_users'] ?? 0));
  $pendingReadyCents = (int) ($summary['pending_ready_cents'] ?? 0);
  $holdDays = max(1, (int) ($summary['commission_pending_days'] ?? 121));
  $minimumPaymentsRequired = max(1, (int) ($summary['minimum_successful_payments_for_payout'] ?? 2));
  $payoutPipelineTotal = $approvedCents + $pendingCents;
  $approvedShare = $payoutPipelineTotal > 0 ? (int) round(($approvedCents / $payoutPipelineTotal) * 100) : 0;
  $pendingShare = $payoutPipelineTotal > 0 ? max(0, 100 - $approvedShare) : 0;
  $trendSeries = $timeseries->map(fn ($point) => [
      'date' => (string) ($point['date'] ?? ''),
      'net_cents' => (int) ($point['net_cents'] ?? 0),
  ])->values()->all();
  $connectStatus = strtolower((string) ($partnerInfo['connect_status'] ?? 'not_started'));
  $connectReady = (bool) ($partnerInfo['connect_ready'] ?? false);
  $connectBadgeClass = match ($connectStatus) {
      'ready' => 'bg-soft-success text-success',
      'restricted' => 'bg-soft-danger text-danger',
      'pending' => 'bg-soft-warning text-warning',
      default => 'bg-soft-secondary text-secondary',
  };
  $connectLabel = match ($connectStatus) {
      'ready' => 'Ready',
      'restricted' => 'Restricted',
      'pending' => 'Onboarding pending',
      default => 'Not started',
  };
  $connectRequirements = is_array($partnerInfo['connect_requirements'] ?? null)
      ? ($partnerInfo['connect_requirements'] ?? [])
      : [];
  $connectDueItems = collect($connectRequirements['currently_due'] ?? [])
      ->merge($connectRequirements['past_due'] ?? [])
      ->filter()
      ->values();
  $onboardingStartedAt = $partnerInfo['onboarding_started_at'] ?? null;
  $activatedAt = $partnerInfo['activated_at'] ?? null;
  $connectCountry = is_array($connect_country ?? null) ? ($connect_country ?? []) : [];
  $allowedOnboardingCountries = collect($connectCountry['allowed'] ?? [])
      ->map(fn ($country) => strtoupper(trim((string) $country)))
      ->filter(fn ($country) => preg_match('/^[A-Z]{2}$/', $country) === 1)
      ->unique()
      ->values();
  if ($allowedOnboardingCountries->isEmpty()) {
      $allowedOnboardingCountries = collect(['DE', 'AT']);
  }
  $countryLabels = [
      'DE' => 'Germany',
      'AT' => 'Austria',
  ];
  $legalCountry = strtoupper((string) ($partnerInfo['legal_country'] ?? ($connectCountry['selected'] ?? '')));
  $legalCountry = $legalCountry !== '' ? $legalCountry : null;
  $legalCountryLocked = (bool) ($partnerInfo['legal_country_locked'] ?? ($connectCountry['locked'] ?? false));
  $formatMoney = static function (?int $cents) use ($currency): string {
      if ($cents === null) {
          return '—';
      }

      return number_format($cents / 100, 2) . ' ' . $currency;
  };
  $statusBadgeClass = static function (?string $status): string {
      $value = strtolower((string) $status);

      if (in_array($value, ['pending', 'queued', 'review'], true)) {
          return 'bg-soft-warning text-warning';
      }

      if (in_array($value, ['approved', 'paid', 'active', 'released'], true)) {
          return 'bg-soft-success text-success';
      }

      if (in_array($value, ['refund', 'failed', 'canceled', 'expired', 'voided', 'reversed', 'chargeback'], true)) {
          return 'bg-soft-danger text-danger';
      }

      return 'bg-soft-secondary text-secondary';
  };
@endphp

@section('content')
<div class="container-fluid content-inner mt-n5 py-0 partner-dashboard-wrap ls-consistent-spacing">
  <div class="row g-3">
    <div class="col-12">
      <div class="card rounded border-0 shadow-sm overflow-hidden">
        <div class="card-body p-4">
          <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-4">
            <div>
              <p class="text-uppercase text-primary small fw-semibold mb-1">Partner workspace</p>
              <h3 class="mb-1">Partner Dashboard</h3>
              <p class="text-muted mb-0">Track referral performance, commission status, and payout readiness without exposing customer data.</p>
              <div class="d-flex flex-wrap gap-2 mt-3">
                <span class="badge rounded-pill bg-soft-primary text-primary">Referral analytics</span>
                <span class="badge rounded-pill bg-soft-success text-success">Payout ready: {{ $formatMoney($approvedCents) }}</span>
                <span class="badge rounded-pill bg-soft-warning text-warning">Pending: {{ $formatMoney($pendingCents) }}</span>
              </div>
              <small class="text-muted d-block mt-2">
                Release rules: {{ $holdDays }}-day hold and at least {{ $minimumPaymentsRequired }} successful payments per referred user.
              </small>
              <small class="text-muted d-block">
                Once a referred user reaches payment #{{ $minimumPaymentsRequired }}, future commissions from that user only follow the hold window.
              </small>
            </div>
            <div class="partner-referral-panel bg-soft-primary rounded p-3">
              <p class="text-uppercase text-primary small fw-semibold mb-1">Primary referral link</p>
              <div class="input-group input-group-sm">
                <input type="text" class="form-control partner-copy-input" readonly value="{{ $partnerInfo['primary_referral_url'] ?? '' }}">
                <button type="button" class="btn btn-primary partner-copy-btn">Copy</button>
              </div>
              <small class="text-muted d-block mt-2">Recommended next review: {{ $summary['next_payout_date'] ?? ($partnerInfo['next_payout_date'] ?? '—') }}</small>
              <div class="border-top pt-3 mt-3">
                <div class="d-flex align-items-center justify-content-between gap-2">
                  <p class="text-uppercase text-muted small fw-semibold mb-0">Stripe payout onboarding</p>
                  <span id="partner-connect-badge" class="badge rounded-pill {{ $connectBadgeClass }}">{{ $connectLabel }}</span>
                </div>
                @if($connectDueItems->isNotEmpty())
                  <small id="partner-connect-hint" class="text-danger d-block mt-2">
                    {{ $connectDueItems->count() }} requirement(s) need attention.
                  </small>
                @else
                  <small id="partner-connect-hint" class="text-muted d-block mt-2">
                    @if($connectReady)
                      Payout account is active.
                    @elseif($onboardingStartedAt)
                      Onboarding started {{ \Illuminate\Support\Carbon::parse($onboardingStartedAt)->diffForHumans() }}.
                    @else
                      Start onboarding to receive Stripe payouts.
                    @endif
                  </small>
                @endif
                <div class="mt-3">
                  <label for="partner-legal-country" class="form-label form-label-sm mb-1">Legal business country</label>
                  <select
                    id="partner-legal-country"
                    class="form-select form-select-sm"
                    @if(!$legalCountryLocked) required @endif
                    @if($legalCountryLocked) disabled @endif
                  >
                    @if(!$legalCountryLocked)
                      <option value="">Select country</option>
                    @endif
                    @foreach($allowedOnboardingCountries as $countryCode)
                      <option value="{{ $countryCode }}" @selected($legalCountry === $countryCode)>
                        {{ $countryLabels[$countryCode] ?? $countryCode }}
                      </option>
                    @endforeach
                  </select>
                  @if($legalCountryLocked)
                    <small class="text-muted d-block mt-1">Country is locked after onboarding starts. Contact support for changes.</small>
                  @else
                    <small class="text-muted d-block mt-1">Required before first Stripe onboarding start.</small>
                  @endif
                </div>
                <div class="d-flex gap-2 mt-3">
                  <button type="button" class="btn btn-sm btn-primary" id="partner-onboarding-start">
                    {{ $onboardingStartedAt ? 'Resume onboarding' : 'Start onboarding' }}
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" id="partner-onboarding-refresh">Refresh status</button>
                </div>
                @if($activatedAt)
                  <small class="text-muted d-block mt-2">Activated: {{ \Illuminate\Support\Carbon::parse($activatedAt)->toDayDateTimeString() }}</small>
                @endif
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-4">
    <div class="col-md-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="avatar-50 rounded d-inline-flex align-items-center justify-content-center bg-soft-primary">
            <i class="bi bi-people text-primary fs-5"></i>
          </span>
          <div>
            <p class="text-uppercase text-muted small mb-1">Referred Users</p>
            <h4 class="mb-1">{{ (int) ($summary['referred_total'] ?? 0) }}</h4>
            <small class="text-muted">{{ (int) ($summary['referred_active'] ?? 0) }} active</small>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="avatar-50 rounded d-inline-flex align-items-center justify-content-center bg-soft-info">
            <i class="bi bi-calendar-event text-info fs-5"></i>
          </span>
          <div>
            <p class="text-uppercase text-muted small mb-1">New This Month</p>
            <h4 class="mb-1">{{ (int) ($summary['new_this_month'] ?? 0) }}</h4>
            <small class="text-muted">Fresh attributions in the current month</small>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="avatar-50 rounded d-inline-flex align-items-center justify-content-center bg-soft-success">
            <i class="bi bi-cash-coin text-success fs-5"></i>
          </span>
          <div>
            <p class="text-uppercase text-muted small mb-1">Approved</p>
            <h4 class="mb-1 text-success">{{ $formatMoney($approvedCents) }}</h4>
            <small class="text-muted">Ready for payout</small>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6 col-xl-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body d-flex align-items-center gap-3">
          <span class="avatar-50 rounded d-inline-flex align-items-center justify-content-center bg-soft-warning">
            <i class="bi bi-hourglass-split text-warning fs-5"></i>
          </span>
          <div>
            <p class="text-uppercase text-muted small mb-1">Pending</p>
            <h4 class="mb-1 text-warning">{{ $formatMoney($pendingCents) }}</h4>
            <small class="text-muted d-block">Hold window: {{ $formatMoney($pendingHoldCents) }}</small>
            <small class="text-muted d-block">
              Referrals waiting for payment #{{ $minimumPaymentsRequired }}: {{ $pendingSecondPaymentUsers }} ({{ $formatMoney($pendingSecondPaymentCents) }})
            </small>
            @if($pendingReadyCents > 0)
              <small class="text-muted d-block">Ready after approval run: {{ $formatMoney($pendingReadyCents) }}</small>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-4">
    <div class="col-xl-8">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
            <div>
              <p class="text-uppercase text-muted small mb-1">Commission Trend</p>
              <h6 class="mb-0">Last 30 days</h6>
            </div>
            <small class="text-muted">Net commission entries by day</small>
          </div>
          <div id="partner-trend-chart" class="partner-trend-chart"></div>
          <p id="partner-trend-empty" class="text-muted mb-0 small {{ $timeseries->isEmpty() ? '' : 'd-none' }}">No commission data yet.</p>
        </div>
      </div>
    </div>
    <div class="col-xl-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <div>
              <p class="text-uppercase text-muted small mb-1">Invite Code</p>
              <h6 class="mb-0">Fixed partner code</h6>
            </div>
          </div>
          <div class="bg-soft-light rounded p-3 mb-3">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
              <span class="small fw-semibold text-body">Payout readiness</span>
              <span class="small text-muted">{{ $formatMoney($approvedCents) }}</span>
            </div>
            <div class="progress mb-2" style="height: 0.55rem;">
              <div class="progress-bar bg-success" style="width: {{ $approvedShare }}%;"></div>
              <div class="progress-bar bg-warning" style="width: {{ $pendingShare }}%;"></div>
            </div>
            <div class="d-flex align-items-center justify-content-between gap-2 small text-muted">
              <span>Approved {{ $formatMoney($approvedCents) }}</span>
              <span>Pending {{ $formatMoney($pendingCents) }}</span>
            </div>
            <small class="text-muted d-block mt-2">
              Hold: {{ $formatMoney($pendingHoldCents) }} | Waiting for payment #{{ $minimumPaymentsRequired }}: {{ $pendingSecondPaymentUsers }} referrals ({{ $formatMoney($pendingSecondPaymentCents) }})
            </small>
          </div>
          <div class="list-group list-group-flush partner-code-list">
            @forelse($inviteCodes as $code)
              <div class="list-group-item px-0 d-flex align-items-center justify-content-between gap-3">
                <div>
                  <strong>{{ $code['code'] }}</strong>
                  <small class="text-muted d-block">{{ (int) ($code['uses_count'] ?? 0) }} uses</small>
                </div>
                <button type="button" class="btn btn-sm btn-soft-primary partner-inline-copy" data-copy="{{ $code['referral_url'] }}">Copy link</button>
              </div>
            @empty
              <p class="text-muted mb-0">No active codes.</p>
            @endforelse
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-4">
    <div class="col-xl-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <div>
              <p class="text-uppercase text-muted small mb-1">Referred Users</p>
              <h6 class="mb-0">Anonymized view</h6>
            </div>
            <small class="text-muted">No names or email addresses</small>
          </div>
          <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
              <thead>
                <tr>
                  <th>User</th>
                  <th>Status</th>
                  <th>Plan</th>
                  <th>Commission</th>
                </tr>
              </thead>
              <tbody>
                @forelse($referrals as $row)
                  <tr>
                    <td>{{ $row['user_label'] }}</td>
                    <td>{{ $row['status'] }}</td>
                    <td>{{ $row['plan_name'] }}</td>
                    <td>
                      {{ $formatMoney($row['commission_cents']) }}
                      @if(!empty($row['commission_status']))
                        <span class="badge rounded-pill {{ $statusBadgeClass($row['commission_status']) }}">{{ $row['commission_status'] }}</span>
                      @endif
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-muted">No referred users yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-6">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <div>
              <p class="text-uppercase text-muted small mb-1">Payouts</p>
              <h6 class="mb-0">Manual payout batches</h6>
            </div>
            <small class="text-muted">Minimum threshold: {{ $formatMoney((int) ($partnerInfo['payout_minimum_cents'] ?? 0)) }}</small>
          </div>
          <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Amount</th>
                  <th>Reference</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($payoutRows as $row)
                  <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['paid_at'] ?? $row['created_at'])->toFormattedDateString() }}</td>
                    <td>{{ $formatMoney((int) ($row['net_amount_cents'] ?? 0)) }}</td>
                    <td>{{ $row['reference'] ?? '—' }}</td>
                    <td><span class="badge rounded-pill {{ $statusBadgeClass($row['status']) }}">{{ $row['status'] }}</span></td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="4" class="text-muted">No payouts yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mt-4">
    <div class="col-12">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
            <div>
              <p class="text-uppercase text-muted small mb-1">Commission Ledger</p>
              <h6 class="mb-0">Every commission, refund, and adjustment</h6>
            </div>
            <small class="text-muted">Audit trail</small>
          </div>
          <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Type</th>
                  <th>User</th>
                  <th>Gross</th>
                  <th>Commission</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                @forelse($ledgerRows as $row)
                  <tr>
                    <td>{{ \Illuminate\Support\Carbon::parse($row['created_at'])->toFormattedDateString() }}</td>
                    <td>{{ ucfirst($row['entry_type']) }}</td>
                    <td>{{ $row['user_label'] }}</td>
                    <td>{{ $formatMoney((int) ($row['gross_amount_cents'] ?? 0)) }}</td>
                    <td>{{ $formatMoney((int) ($row['commission_amount_cents'] ?? 0)) }}</td>
                    <td><span class="badge rounded-pill {{ $statusBadgeClass($row['status']) }}">{{ $row['status'] }}</span></td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="text-muted">No ledger entries yet.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
  .partner-dashboard-wrap .card {
    border-radius: 1rem;
  }

  .partner-referral-panel {
    width: min(100%, 26rem);
  }

  .partner-trend-chart {
    height: 20rem;
  }

  @media (max-width: 1199px) {
    .partner-referral-panel {
      width: 100%;
    }
  }
</style>

<script>
  (() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const onboardingStartUrl = '{{ route('partner.onboarding.start') }}';
    const onboardingStatusUrl = '{{ route('partner.onboarding.status') }}';
    const trendSeries = @json($trendSeries);
    const trendCurrency = @json($currency);
    const allowedOnboardingCountries = @json($allowedOnboardingCountries->values()->all());
    const legalCountryLocked = @json($legalCountryLocked);

    const renderTrendChart = () => {
      const chartRoot = document.getElementById('partner-trend-chart');
      const emptyState = document.getElementById('partner-trend-empty');

      if (!chartRoot) {
        return;
      }

      const points = Array.isArray(trendSeries)
        ? trendSeries
          .map((point) => ({
            x: `${String(point?.date || '')}T00:00:00Z`,
            y: Number(point?.net_cents || 0) / 100,
          }))
          .filter((point) => point.x.length > 10)
        : [];

      if (!points.length || typeof ApexCharts === 'undefined') {
        if (emptyState) {
          emptyState.classList.remove('d-none');
        }
        chartRoot.innerHTML = '';
        return;
      }

      if (emptyState) {
        emptyState.classList.add('d-none');
      }

      if (window.__partnerTrendChart && typeof window.__partnerTrendChart.destroy === 'function') {
        window.__partnerTrendChart.destroy();
      }

      const options = {
        chart: {
          type: 'line',
          height: 320,
          toolbar: { show: false },
          zoom: { enabled: false },
        },
        series: [
          {
            name: 'Net commission',
            data: points,
          },
        ],
        colors: ['#0f3d3e'],
        stroke: {
          curve: 'straight',
          width: 3,
        },
        markers: {
          size: 0,
          hover: {
            size: 5,
          },
        },
        dataLabels: {
          enabled: false,
        },
        xaxis: {
          type: 'datetime',
          labels: {
            datetimeUTC: true,
          },
        },
        yaxis: {
          labels: {
            formatter: (value) => `${Number(value).toFixed(2)} ${trendCurrency}`,
          },
        },
        grid: {
          borderColor: '#e2e8f0',
          strokeDashArray: 4,
        },
        tooltip: {
          x: {
            format: 'dd MMM yyyy',
          },
          y: {
            formatter: (value) => `${Number(value).toFixed(2)} ${trendCurrency}`,
          },
        },
      };

      window.__partnerTrendChart = new ApexCharts(chartRoot, options);
      window.__partnerTrendChart.render();
    };

    const copy = async (value) => {
      if (!value) {
        return;
      }

      try {
        await navigator.clipboard.writeText(value);
      } catch (_err) {
        const temp = document.createElement('textarea');
        temp.value = value;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        document.body.removeChild(temp);
      }
    };

    const connectBadge = document.getElementById('partner-connect-badge');
    const connectHint = document.getElementById('partner-connect-hint');
    const startButton = document.getElementById('partner-onboarding-start');
    const refreshButton = document.getElementById('partner-onboarding-refresh');
    const legalCountrySelect = document.getElementById('partner-legal-country');
    const allowedOnboardingCountrySet = new Set(
      (Array.isArray(allowedOnboardingCountries) ? allowedOnboardingCountries : [])
        .map((country) => String(country || '').trim().toUpperCase())
        .filter((country) => /^[A-Z]{2}$/.test(country)),
    );

    const selectedLegalCountry = () => String(legalCountrySelect?.value || '').trim().toUpperCase();

    const syncStartButtonState = () => {
      if (!startButton || legalCountryLocked || !legalCountrySelect) {
        return;
      }

      const country = selectedLegalCountry();
      startButton.disabled = !country || !allowedOnboardingCountrySet.has(country);
    };

    legalCountrySelect?.addEventListener('change', syncStartButtonState);
    syncStartButtonState();

    const applyConnectStatus = (payload) => {
      if (!connectBadge || !connectHint || !payload) {
        return;
      }

      const status = String(payload.connect_status || '').toLowerCase();
      const requirements = Array.isArray(payload.requirements)
        ? payload.requirements
        : [];

      connectBadge.classList.remove(
        'bg-soft-success', 'text-success',
        'bg-soft-warning', 'text-warning',
        'bg-soft-danger', 'text-danger',
        'bg-soft-secondary', 'text-secondary',
      );

      if (status === 'ready') {
        connectBadge.textContent = 'Ready';
        connectBadge.classList.add('bg-soft-success', 'text-success');
        connectHint.classList.remove('text-danger');
        connectHint.classList.add('text-muted');
        connectHint.textContent = 'Payout account is active.';
        return;
      }

      if (status === 'restricted') {
        connectBadge.textContent = 'Restricted';
        connectBadge.classList.add('bg-soft-danger', 'text-danger');
        connectHint.classList.remove('text-muted');
        connectHint.classList.add('text-danger');
        connectHint.textContent = `${requirements.length || 0} requirement(s) need attention.`;
        return;
      }

      if (status === 'pending') {
        connectBadge.textContent = 'Onboarding pending';
        connectBadge.classList.add('bg-soft-warning', 'text-warning');
        connectHint.classList.remove('text-danger');
        connectHint.classList.add('text-muted');
        connectHint.textContent = 'Onboarding is in progress.';
        return;
      }

      connectBadge.textContent = 'Not started';
      connectBadge.classList.add('bg-soft-secondary', 'text-secondary');
      connectHint.classList.remove('text-danger');
      connectHint.classList.add('text-muted');
      connectHint.textContent = 'Start onboarding to receive Stripe payouts.';
    };

    const refreshConnectStatus = async () => {
      const response = await fetch(onboardingStatusUrl, {
        method: 'GET',
        headers: {
          Accept: 'application/json',
        },
      });

      const payload = await response.json();
      if (!response.ok) {
        throw new Error(payload?.message || payload?.error || 'Unable to refresh onboarding status.');
      }

      applyConnectStatus(payload);
      return payload;
    };

    document.querySelector('.partner-copy-btn')?.addEventListener('click', async () => {
      await copy(document.querySelector('.partner-copy-input')?.value || '');
    });

    document.querySelectorAll('.partner-inline-copy').forEach((button) => {
      button.addEventListener('click', async () => {
        await copy(button.dataset.copy || '');
      });
    });

    startButton?.addEventListener('click', async () => {
      try {
        const country = selectedLegalCountry();
        if (!legalCountryLocked && !country) {
          legalCountrySelect?.reportValidity?.();
          throw new Error('Please select your legal business country.');
        }

        if (country && !allowedOnboardingCountrySet.has(country)) {
          throw new Error('Selected country is not allowed for onboarding.');
        }

        const response = await fetch(onboardingStartUrl, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': csrf,
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          body: JSON.stringify(country ? { country } : {}),
        });

        const payload = await response.json();
        if (!response.ok || !payload?.onboarding_url) {
          throw new Error(payload?.message || payload?.error || 'Unable to start onboarding.');
        }

        window.location.assign(payload.onboarding_url);
      } catch (error) {
        if (connectHint) {
          connectHint.classList.remove('text-muted');
          connectHint.classList.add('text-danger');
          connectHint.textContent = error.message;
        }
      }
    });

    refreshButton?.addEventListener('click', async () => {
      try {
        await refreshConnectStatus();
      } catch (error) {
        if (connectHint) {
          connectHint.classList.remove('text-muted');
          connectHint.classList.add('text-danger');
          connectHint.textContent = error.message;
        }
      }
    });

    window.addEventListener('load', renderTrendChart, { once: true });
  })();
</script>
@endsection
