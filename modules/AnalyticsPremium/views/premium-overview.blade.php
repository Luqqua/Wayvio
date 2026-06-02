@php
    $sm = app(\Modules\Tiers\Services\SubscriptionManager::class);
    $resolver = app(\Modules\Tiers\Services\TierResolver::class);
    $user = auth()->user();
    $tier = $user ? $sm->getUserTier($user) : null;
    $tierSlug = $resolver->normalizeSlug($tier?->slug);
    $tierLabel = strtoupper($resolver->displayName($tierSlug));
    $analyticsFeatures = $resolver->configForTier($tier)['features']['analytics'] ?? [];
    $hasAnalytics = !empty($analyticsFeatures['enabled']);
@endphp

<style>
    .analytics-shell {background: linear-gradient(135deg, #0d0f1a, #11182b); color: #e6ecff; border: none;}
    .analytics-shell .card-body {background: transparent;}
    .analytics-chip {padding: 6px 12px; border-radius: 999px; background: rgba(255,255,255,0.08); color: #e6ecff; font-size: 12px; letter-spacing: .5px;}
    .analytics-panel {border-radius: 14px; background: #ffffff; box-shadow: 0 12px 30px rgba(0,0,0,0.08); border: 1px solid #eef2f7;}
    .analytics-panel h5 {font-weight: 700; color: #0d1b2a;}
    .analytics-panel .muted {color: #6c757d;}
    .agg-btn {border-radius: 10px !important; border: 1px solid #1f2a44; background: #11182b; color: #e6ecff;}
    .agg-btn.active {background: #3b5bdb; border-color: #3b5bdb;}
    .stat-bar {height: 8px; border-radius: 8px; background: #e9ecef; overflow: hidden;}
    .stat-bar .fill {height: 100%; background: linear-gradient(90deg, #3b5bdb, #33b5e5);}
    .heatmap-grid {display: grid; grid-template-columns: repeat(auto-fit, minmax(16px, 1fr)); gap: 6px;}
    .heat-cell {width: 100%; padding-top: 100%; position: relative; border-radius: 6px;}
    .heat-cell span {position: absolute; inset: 0; border-radius: 6px; display: block;}
    .pill {padding: 6px 10px; border-radius: 999px; background: #f1f3f5; font-size: 12px;}
</style>

<div class="card analytics-shell mt-3">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <div>
                <h4 class="mb-1 text-white">Analytics</h4>
                <p class="mb-0 text-white-50">Engagement, audience, and traffic sources in one place.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="analytics-chip text-uppercase">Tier: {{ $tierLabel }}</span>
                <span class="analytics-chip">Endpoints secured • Live data</span>
            </div>
        </div>

        @if(!$hasAnalytics)
            <div class="alert alert-warning mt-3 mb-0">
                Analytics are not enabled for your current tier. Upgrade to Pro or Business to view devices, geography, referrers, and timelines.
            </div>
        @else
            <div class="d-flex flex-wrap align-items-center gap-2 mt-4">
                <small class="text-uppercase text-white-50 fw-bold">Aggregation</small>
                <div class="btn-group flex-wrap" role="group" aria-label="Aggregation">
                    <button type="button" class="btn agg-btn active" data-range="1d">1 day</button>
                    <button type="button" class="btn agg-btn" data-range="1w">1 week</button>
                    <button type="button" class="btn agg-btn" data-range="1m">1 month</button>
                    <button type="button" class="btn agg-btn" data-range="6m">6 months</button>
                    <button type="button" class="btn agg-btn" data-range="1y">1 year</button>
                </div>
            </div>

            <div id="analytics-content" class="mt-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="analytics-panel h-100 p-3">
                            <div class="d-flex align-items-center justify-content-between">
                                <h5 class="mb-1">Engagement</h5>
                                <span class="pill" id="summary-range">1 month</span>
                            </div>
                            <p class="muted mb-3">Quick indicators for the selected window.</p>
                            <div class="d-flex flex-column gap-3">
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="muted">Recorded events</span>
                                        <strong id="summary-events">-</strong>
                                    </div>
                                    <div class="stat-bar"><div class="fill" style="width:50%"></div></div>
                                </div>
                                <div>
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="muted">Link clicks</span>
                                        <strong id="summary-clicks">-</strong>
                                    </div>
                                    <div class="stat-bar"><div class="fill" style="width:40%; background: linear-gradient(90deg,#20c997,#0abde3);"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="analytics-panel h-100 p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="mb-0">Timeline</h5>
                                <span class="pill muted small">Buckets adjust per range</span>
                            </div>
                            <div id="timeline-bars" class="d-flex align-items-end gap-2" style="min-height:180px;"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="analytics-panel h-100 p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="mb-0">Top Links</h5>
                                <span class="pill muted small">Clicks</span>
                            </div>
                            <div id="links-body" class="d-flex flex-column gap-2"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="analytics-panel h-100 p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="mb-0">Devices & Tech</h5>
                                <span class="pill muted small">Device • Browser • OS</span>
                            </div>
                            <div class="row g-2">
                                <div class="col-12">
                                    <h6 class="mb-1">Devices</h6>
                                    <div id="devices-body" class="d-flex flex-column gap-2"></div>
                                </div>
                                <div class="col-12">
                                    <h6 class="mb-1">Browsers</h6>
                                    <div id="browsers-body" class="d-flex flex-column gap-2"></div>
                                </div>
                                <div class="col-12">
                                    <h6 class="mb-1">Operating Systems</h6>
                                    <div id="os-body" class="d-flex flex-column gap-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="analytics-panel h-100 p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="mb-0">Geography</h5>
                                <span class="pill muted small">Countries</span>
                            </div>
                            <div id="countries-body" class="d-flex flex-column gap-2"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="analytics-panel h-100 p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="mb-0">Referrers</h5>
                                <span class="pill muted small">Top sources</span>
                            </div>
                            <div id="referrers-body" class="d-flex flex-column gap-2"></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="analytics-panel h-100 p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="mb-0">Activity Heatmap</h5>
                                <span class="pill muted small">Intensity by bucket</span>
                            </div>
                            <div id="heatmap-body" class="heatmap-grid"></div>
                        </div>
                    </div>
                </div>
                <div id="analytics-errors" class="alert alert-danger mt-3 d-none"></div>
            </div>
        @endif
    </div>
</div>

@if($hasAnalytics)
<script>
    (() => {
        const features = @json($analyticsFeatures);
        const endpoints = {
            links: '{{ url('/api/analytics/links') }}',
            devices: '{{ url('/api/analytics/devices') }}',
            countries: '{{ url('/api/analytics/countries') }}',
            referrers: '{{ url('/api/analytics/referrers') }}',
            timeline: '{{ url('/api/analytics/timeline') }}',
        };
        const ranges = {
            '1d': 'Last 24 hours',
            '1w': 'Last 7 days',
            '1m': 'Last 30 days',
            '6m': 'Last 6 months',
            '1y': 'Last 12 months',
        };
        const els = {
            range: document.getElementById('summary-range'),
            events: document.getElementById('summary-events'),
            clicks: document.getElementById('summary-clicks'),
            links: document.getElementById('links-body'),
            devices: document.getElementById('devices-body'),
            browsers: document.getElementById('browsers-body'),
            os: document.getElementById('os-body'),
            countries: document.getElementById('countries-body'),
            referrers: document.getElementById('referrers-body'),
            timeline: document.getElementById('timeline-bars'),
            heatmap: document.getElementById('heatmap-body'),
            errors: document.getElementById('analytics-errors'),
        };

        const fetchJson = async (url) => {
            const res = await fetch(url, {headers: {'Accept': 'application/json'}});
            if (!res.ok) throw new Error(`Request failed (${res.status})`);
            return res.json();
        };

        const renderBarList = (target, rows, labelKey = 'label') => {
            if (!rows || !rows.length) {
                target.innerHTML = '<p class="muted mb-0">No data yet.</p>';
                return;
            }
            const max = Math.max(...rows.map(r => Number(r.total) || 0)) || 1;
            target.innerHTML = rows.map((row, idx) => {
                const total = Number(row.total) || 0;
                const pct = Math.round((total / max) * 100);
                return `
                    <div class="d-flex flex-column gap-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-capitalize">${row[labelKey] ?? 'n/a'}</span>
                            <strong>${total.toLocaleString()}</strong>
                        </div>
                        <div class="stat-bar"><div class="fill" style="width:${pct}%;"></div></div>
                    </div>
                `;
            }).join('');
        };

        const renderLinks = (links) => {
            if (!links || !links.length) {
                els.links.innerHTML = '<p class="muted mb-0">No link clicks yet.</p>';
                return;
            }
            const max = Math.max(...links.map(l => Number(l.clicks) || 0)) || 1;
            els.links.innerHTML = links.map(link => {
                const clicks = Number(link.clicks) || 0;
                const pct = Math.round((clicks / max) * 100);
                return `
                    <div class="d-flex flex-column gap-1">
                        <div class="d-flex justify-content-between align-items-center">
                            <span>${link.name || 'Untitled link'}</span>
                            <strong>${clicks.toLocaleString()}</strong>
                        </div>
                        <div class="stat-bar"><div class="fill" style="width:${pct}%; background: linear-gradient(90deg,#20c997,#0abde3);"></div></div>
                    </div>
                `;
            }).join('');
        };

        const renderTimeline = (data) => {
            if (!data || !data.length) {
                els.timeline.innerHTML = '<p class="muted mb-0">No events in this window.</p>';
                return;
            }
            const max = Math.max(...data.map(item => Number(item.total) || 0)) || 1;
            els.timeline.innerHTML = data.map(item => {
                const value = Number(item.total) || 0;
                const height = 20 + Math.round((value / max) * 120);
                return `<div class="flex-grow-1 text-center">
                    <div style="height:${height}px; background: linear-gradient(180deg,#3b5bdb,#33b5e5); border-radius:10px 10px 4px 4px;"></div>
                    <small class="d-block mt-1 text-muted">${item.bucket}</small>
                </div>`;
            }).join('');
        };

        const renderHeatmap = (data) => {
            if (!data || !data.length) {
                els.heatmap.innerHTML = '<p class="muted mb-0">Heatmap will appear once events are recorded.</p>';
                return;
            }
            const max = Math.max(...data.map(item => Number(item.total) || 0)) || 1;
            els.heatmap.innerHTML = data.map(item => {
                const value = Number(item.total) || 0;
                const intensity = Math.max(10, Math.round((value / max) * 100));
                const color = `hsl(220, 70%, ${100 - intensity / 2}%)`;
                return `<div class="heat-cell" title="${item.bucket}: ${value}">
                    <span style="background:${color}; opacity:0.9;"></span>
                </div>`;
            }).join('');
        };

        const setStatus = (msg) => {
            if (!msg) {
                els.errors.classList.add('d-none');
                els.errors.textContent = '';
                return;
            }
            els.errors.classList.remove('d-none');
            els.errors.textContent = msg;
        };

        const buttons = document.querySelectorAll('.agg-btn');
        buttons.forEach(btn => {
            btn.addEventListener('click', () => {
                buttons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                state.range = btn.dataset.range;
                els.range.textContent = ranges[state.range] || 'Custom range';
                refresh();
            });
        });

        const state = { range: '1d' };
        els.range.textContent = ranges[state.range];

        const refresh = async () => {
            setStatus('');
            try {
                const premiumFallback = Promise.resolve([]);
                const [links, devicesPayload, countries, referrers, timeline] = await Promise.all([
                    fetchJson(endpoints.links),
                    fetchJson(endpoints.devices),
                    features.geo ? fetchJson(endpoints.countries) : premiumFallback,
                    features.referrer ? fetchJson(endpoints.referrers) : premiumFallback,
                    features.time_series ? fetchJson(`${endpoints.timeline}?range=${state.range}`) : premiumFallback
                ]);

                const totalClicks = (links || []).reduce((sum, l) => sum + (Number(l.clicks) || 0), 0);
                const totalEvents = (timeline || []).reduce((sum, t) => sum + (Number(t.total) || 0), 0);
                els.clicks.textContent = totalClicks.toLocaleString();
                els.events.textContent = totalEvents.toLocaleString();

                renderLinks(links);
                renderBarList(els.devices, devicesPayload.devices || [], 'device_type');
                renderBarList(els.browsers, devicesPayload.browsers || [], 'browser');
                renderBarList(els.os, devicesPayload.os || [], 'operating_system');
                renderBarList(els.countries, countries, 'country');
                renderBarList(els.referrers, referrers, 'referrer');
                renderTimeline(timeline);
                renderHeatmap(timeline);

                if (!features.geo) {
                    els.countries.innerHTML = '<p class="muted mb-0">Business required for geo analytics.</p>';
                }
                if (!features.referrer) {
                    els.referrers.innerHTML = '<p class="muted mb-0">Upgrade to view referrers.</p>';
                }
                if (!features.time_series) {
                    els.timeline.innerHTML = '<p class="muted mb-0">Upgrade to view timelines.</p>';
                    els.heatmap.innerHTML = '<p class="muted mb-0">Upgrade to view heatmaps.</p>';
                }
            } catch (e) {
                setStatus(e.message || 'Failed to load analytics.');
            }
        };

        refresh();
    })();
</script>
@endif
