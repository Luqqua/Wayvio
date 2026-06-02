@extends('layouts.sidebar')

@php
    $summary = $aggregates['summary'] ?? [];
    $viewsCount = $summary['views'] ?? $summary['page_views'] ?? $summary['total_views'] ?? null;
    $clickCount = $summary['clicks'] ?? $summary['link_clicks'] ?? null;
    $rangeOptions = isset($rangeOptions) ? $rangeOptions : config('analytics.ranges', ['7d', '30d']);
    $rangeLabel = strtoupper((string) $range);
    $analyticsBundleVersion = @filemtime(public_path('js/analytics-dashboard.js')) ?: time();
@endphp

@section('content')
<div class="container-fluid content-inner mt-n5 py-0 analytics-dashboard-page ls-consistent-spacing">
  <div class="row gx-3 gy-4">
    <div class="col-12">
      <div class="card rounded border-0 shadow-sm overflow-hidden analytics-shell-card">
        <div class="card-body p-4 p-xl-5">
          <div class="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-4">
            <div>
              <p class="text-uppercase text-primary small fw-semibold mb-1">{{ __('Dashboard') }}</p>
              <h3 class="mb-1">{{ __('Analytics Center') }}</h3>
              <p class="text-muted mb-0">{{ __('Track traffic, conversion, and acquisition quality in one Hope UI dashboard surface.') }}</p>
              @if(!empty($analyticsSiteSlug))
                <p class="text-muted mt-2 mb-0 small">{{ __('Context:') }} {{ '@'.$analyticsSiteSlug }}</p>
              @endif
              <div class="d-flex flex-wrap gap-2 mt-3">
                <span class="badge rounded-pill bg-soft-primary text-primary">{{ __('Views') }}: {{ number_format((int) ($viewsCount ?? 0)) }}</span>
                <span class="badge rounded-pill bg-soft-info text-info">{{ __('Clicks') }}: {{ number_format((int) ($clickCount ?? 0)) }}</span>
                <span class="badge rounded-pill bg-soft-warning text-warning">{{ __('Tier') }}: {{ $tierLabel }}</span>
              </div>
            </div>
            <div class="analytics-control-panel bg-soft-primary rounded p-3">
              <p class="text-uppercase text-primary small fw-semibold mb-2">{{ __('Range & Export') }}</p>
              <form method="get" action="{{ url('/dashboard/analytics') }}" class="d-flex flex-column gap-2">
                <select
                  name="range"
                  id="range"
                  class="form-select form-select-sm"
                  aria-label="{{ __('Range') }}"
                  onchange="this.form.submit()"
                >
                  @foreach($rangeOptions as $option)
                    <option value="{{ $option }}" @selected($range === $option)>{{ strtoupper($option) }}</option>
                  @endforeach
                </select>
                <button
                  type="button"
                  class="btn btn-primary btn-sm d-inline-flex align-items-center justify-content-center gap-1"
                  id="analytics-download-csv"
                >
                  <i class="bi bi-download"></i>
                  {{ __('Download') }}
                </button>
              </form>
              <small class="text-muted d-block mt-2">{{ __('Selected range') }}: {{ $rangeLabel }}</small>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div id="analytics-dashboard-root" class="mt-4" data-tier-level="{{ $tierLevel }}"></div>
</div>

<style>
  .analytics-dashboard-page .analytics-shell-card,
  .analytics-dashboard-page .analytics-dashboard-wrap .card {
    border-radius: 1rem;
  }

  .analytics-dashboard-page .analytics-control-panel {
    width: min(100%, 22rem);
  }

  .analytics-dashboard-page .analytics-card-toggle .nav-link {
    padding: 0.3rem 0.75rem;
    font-size: 0.75rem;
    line-height: 1.2;
  }

  .analytics-dashboard-page .analytics-card-toggle .nav-link:not(.active) {
    background-color: rgba(var(--bs-primary-rgb), 0.08);
    color: var(--bs-primary);
  }

  .analytics-dashboard-page .analytics-progress-list .list-group-item {
    border: 0;
    border-bottom: 1px dashed var(--bs-border-color, rgba(0, 0, 0, 0.12));
  }

  .analytics-dashboard-page .analytics-progress-list .list-group-item:last-child {
    border-bottom: 0;
  }

  .analytics-dashboard-page .analytics-kpi-caption {
    min-height: 1rem;
  }

  @media (max-width: 1199px) {
    .analytics-dashboard-page .analytics-control-panel {
      width: 100%;
    }
  }
</style>
@endsection

@push('sidebar-scripts')
<script>
window.__LS_ANALYTICS__ = {!! json_encode([
    'aggregates' => $aggregates,
    'features' => $features,
    'range' => $range,
    'tierLabel' => $tierLabel,
    'tierLevel' => $tierLevel,
    'translations' => [
        'analyticsCockpit' => __('Analytics cockpit'),
        'performanceOverview' => __('Performance overview'),
        'liveSummary' => __('Live summary for the selected range with conversion and acquisition signals.'),
        'trackingActive' => __('Tracking active'),
        'sichtbarkeit' => __('Sichtbarkeit'),
        'conversion' => __('Conversion'),
        'momentum' => __('Momentum'),
        'pageViews' => __('Page Views'),
        'linkClicks' => __('Link Clicks'),
        'trackedCtaActivity' => __('Tracked CTA activity'),
        'ctr' => __('CTR'),
        'clicksDividedByPageViews' => __('Clicks divided by page views'),
        'topCountry' => __('Top Country'),
        'uniqueVisitors' => __('unique visitors'),
        'uniqueVisitorsUnavailable' => __('Unique visitors unavailable'),
        'noGeoData' => __('No geo data for this range.'),
        'visits' => __('visits'),
        'topLinksSnapshot' => __('Top Links Snapshot'),
        'highestClickConcentration' => __('Highest click concentration'),
        'noLinkClickData' => __('No link click data yet.'),
        'referrerShare' => __('Referrer Share'),
        'acquisitionChannels' => __('Acquisition channels'),
        'noReferrerDataRange' => __('No referrer data for this range.'),
        'countryShare' => __('Country Share'),
        'geographicConcentration' => __('Geographic concentration'),
        'noGeographicData' => __('No geographic data for this range.'),
        'noDataRange' => __('No data available for this range.'),
        'pageViewsOverTime' => __('Page Views Over Time'),
        'trafficVolume' => __('Traffic volume across the selected window'),
        'date' => __('Date'),
        'noTimeSeriesData' => __('No time series data for this range.'),
        'linkClickRanking' => __('Link Click Ranking'),
        'bestPerformingLinks' => __('Best-performing links by click volume'),
        'link' => __('Link'),
        'noClickData' => __('No click data yet.'),
        'countryDistribution' => __('Country Distribution'),
        'whereVisitorsLocated' => __('Where your visitors are located'),
        'country' => __('Country'),
        'noCountryData' => __('No country data available.'),
        'referrerBreakdown' => __('Referrer Breakdown'),
        'topExternalSources' => __('Top external traffic sources'),
        'noReferrerData' => __('No referrer data available.'),
        'utmCombinations' => __('UTM Combinations'),
        'utmCombinationsDesc' => __('Campaign, source, medium, term, and content combinations'),
        'utmCombination' => __('UTM Combination'),
        'noUtmData' => __('No UTM data yet.'),
        'untitled' => __('Untitled'),
        'unknown' => __('Unknown'),
        'momentumBuilds' => __('Momentum builds once at least two points exist.'),
        'availableFromBasic' => __('Available from Basic'),
        'availableFromPro' => __('Available from Pro'),
        'tier' => __('Tier'),
        'views' => __('Views'),
        'clicks' => __('Clicks'),
    ],
]) !!};
</script>
<script src="{{ asset('js/analytics-dashboard.js') }}?v={{ $analyticsBundleVersion }}" defer></script>
@endpush
