import React from 'react';
import { createRoot } from 'react-dom/client';
import AnalyticsCard from './components/AnalyticsCard';
import AnalyticsGraph from './components/AnalyticsGraph';
import { aggregateByRange } from './utils/aggregateByRange';

let _i18n = {};
function t(key) {
    return _i18n[key] ?? key;
}

const fallback = (value, ...keys) => {
    if (value !== undefined && value !== null) return value;
    for (const key of keys) {
        if (key !== undefined && key !== null) return key;
    }
    return null;
};

const compactFormatter = new Intl.NumberFormat(undefined, {
    notation: 'compact',
    maximumFractionDigits: 1,
});

function parseRangeDays(range) {
    if (typeof range === 'number') return range;
    if (typeof range === 'string' && range.endsWith('d')) {
        const num = Number.parseInt(range.replace('d', ''), 10);
        return Number.isFinite(num) ? num : 0;
    }
    const asNum = Number(range);
    return Number.isFinite(asNum) ? asNum : 0;
}

function normalizeSeries(items = []) {
    return items
        .map((item) => ({
            ts: item.ts || item.timestamp || item.date || item.label,
            value: Number(item.value ?? item.count ?? item.total ?? item.views ?? 0),
        }))
        .filter((entry) => entry.ts && Number.isFinite(entry.value));
}

function collectRawEvents(aggregates, metricKeys) {
    const keys = Array.isArray(metricKeys) ? metricKeys : [metricKeys];
    const events = Array.isArray(aggregates?.events) ? aggregates.events : [];

    const eventMatches = events
        .filter((event) => keys.includes(event?.event_type) || keys.includes(event?.type) || keys.includes(event?.metric))
        .map((event) => ({
            ts: event.ts || event.occurred_at || event.date,
            value: Number(event.value ?? 1),
        }))
        .filter((entry) => entry.ts && Number.isFinite(entry.value));
    if (eventMatches.length) return eventMatches;

    const containers = [aggregates?.timeseries, aggregates?.timeline, aggregates?.series, aggregates];
    for (const container of containers) {
        if (!container) continue;
        for (const key of keys) {
            const candidate = container?.[key] || container?.[`${key}_series`] || container?.[`${key}_timeseries`];
            if (Array.isArray(candidate) && candidate.length) {
                return normalizeSeries(candidate);
            }
        }
    }

    return [];
}

function buildSeries(aggregates, metricKeys, rangeDays) {
    const events = collectRawEvents(aggregates, metricKeys);
    const aggregated = aggregateByRange(events, rangeDays || 1);
    const points = aggregated.points.map((point) => ({ ts: point.ts, value: point.value }));
    return { unit: aggregated.unit, points };
}

function toNumber(value) {
    const num = Number(value);
    return Number.isFinite(num) ? num : 0;
}

function formatCount(value) {
    const num = Number(value);
    return Number.isFinite(num) ? num.toLocaleString() : null;
}

function formatCompact(value) {
    const num = Number(value);
    return Number.isFinite(num) ? compactFormatter.format(num) : null;
}

function formatPercent(value) {
    const num = Number(value);
    return Number.isFinite(num) ? `${Math.round(num)}%` : null;
}

function formatSeriesLabel(value, unit) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    if (unit === 'hour') {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    if (unit === 'month') {
        return date.toLocaleDateString([], { month: 'short', year: '2-digit' });
    }
    return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function cleanUtmPart(value) {
    if (value === null || value === undefined) return null;
    const trimmed = value.toString().trim();
    if (!trimmed) return null;
    return trimmed.toLowerCase() === 'unknown' ? null : trimmed;
}

function escapeCsvValue(value) {
    if (value === null || value === undefined) return '';
    const stringValue = String(value);
    if (/[",\n\r]/.test(stringValue)) {
        return `"${stringValue.replace(/"/g, '""')}"`;
    }
    return stringValue;
}

function buildAnalyticsCsvRows(payload) {
    const rows = [['section', 'label', 'value']];
    const range = payload?.range ?? '';
    if (range) {
        rows.push(['meta', 'range', range]);
    }

    const aggregates = payload?.aggregates || {};
    const summary = aggregates?.summary || {};
    const viewsCount = fallback(summary.views, summary.page_views, summary.total_views);
    const uniqueCount = fallback(summary.unique_visitors, summary.visitors_unique);
    const clickCount = fallback(summary.clicks, summary.link_clicks);

    if (viewsCount !== null && viewsCount !== undefined) rows.push(['summary', 'page_views', viewsCount]);
    if (uniqueCount !== null && uniqueCount !== undefined) rows.push(['summary', 'unique_visitors', uniqueCount]);
    if (clickCount !== null && clickCount !== undefined) rows.push(['summary', 'link_clicks', clickCount]);
    if (
        viewsCount !== null
        && viewsCount !== undefined
        && clickCount !== null
        && clickCount !== undefined
        && Number(viewsCount) > 0
    ) {
        rows.push(['summary', 'ctr_percent', Math.round((Number(clickCount) / Number(viewsCount)) * 100)]);
    }

    const rangeDays = parseRangeDays(range);
    const viewsSeries = buildSeries(aggregates, ['views', 'page_views', 'view'], rangeDays);
    viewsSeries.points.forEach((point) => {
        rows.push(['views_over_time', point.ts, point.value]);
    });

    const topLinks = aggregates?.top_links || [];
    topLinks.forEach((link) => {
        const label = link.title || link.name || (link.link_id ? `Link #${link.link_id}` : 'Untitled');
        const value = Number(link.clicks ?? link.count ?? 0);
        rows.push(['top_links', label, value]);
    });

    const referrers = aggregates?.referrers || [];
    referrers.forEach((ref) => {
        const label = ref.domain || ref.label || 'Unknown';
        rows.push(['referrers', label, Number(ref.count ?? 0)]);
    });

    const countries = aggregates?.geo?.countries || aggregates?.countries || [];
    countries.forEach((country) => {
        const label = country.name || country.code || 'Unknown';
        rows.push(['countries', label, Number(country.count ?? 0)]);
    });

    const utmCombos = (aggregates?.utm_sets || []).map((row) => {
        const parts = [
            cleanUtmPart(row.source),
            cleanUtmPart(row.medium),
            cleanUtmPart(row.campaign),
            cleanUtmPart(row.id ?? row.utm_id),
            cleanUtmPart(row.term),
            cleanUtmPart(row.content),
        ].filter(Boolean);
        return {
            name: parts.join(' / ') || 'Unknown',
            value: Number(row.count ?? 0),
        };
    });
    utmCombos.forEach((row) => {
        rows.push(['utm_combinations', row.name, row.value]);
    });

    return rows;
}

function downloadAnalyticsCsv(payload) {
    const rows = buildAnalyticsCsvRows(payload);
    const csv = rows.map((row) => row.map(escapeCsvValue).join(',')).join('\n');
    const range = payload?.range ? String(payload.range) : 'export';
    const safeRange = range.replace(/[^a-zA-Z0-9-_]+/g, '_');
    const filename = `analytics-${safeRange || 'export'}.csv`;

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function buildShareRows(items, labelGetter, valueGetter, limit = 6) {
    const prepared = (items || [])
        .map((item) => ({
            label: labelGetter(item),
            value: toNumber(valueGetter(item)),
        }))
        .filter((row) => row.label && row.value > 0)
        .sort((a, b) => b.value - a.value);

    const topRows = prepared.slice(0, limit);
    const total = topRows.reduce((sum, row) => sum + row.value, 0);

    return topRows.map((row) => ({
        ...row,
        percent: total > 0 ? Math.max(2, Math.round((row.value / total) * 100)) : 0,
    }));
}

function computeMomentum(points) {
    if (!Array.isArray(points) || points.length < 2) {
        return { change: null, current: 0, baseline: 0, comparisonPoints: 0 };
    }

    const current = toNumber(points[points.length - 1]?.value);
    const previousPoints = points.slice(0, -1).map((point) => toNumber(point?.value));
    const comparisonPoints = previousPoints.length;

    if (!comparisonPoints) {
        return { change: null, current, baseline: 0, comparisonPoints: 0 };
    }

    const baseline = previousPoints.reduce((sum, value) => sum + value, 0) / comparisonPoints;

    if (baseline <= 0) {
        if (current > 0) {
            return { change: 100, current, baseline, comparisonPoints };
        }
        return { change: 0, current, baseline, comparisonPoints };
    }

    return {
        change: ((current - baseline) / baseline) * 100,
        current,
        baseline,
        comparisonPoints,
    };
}

function bucketLabel(unit) {
    if (unit === 'hour') return 'hour';
    if (unit === 'week') return 'week';
    if (unit === 'month') return 'month';
    return 'day';
}

function momentumMeta(momentum, unit = 'day') {
    if (!Number.isFinite(momentum?.change)) {
        return { value: '—', label: t('momentumBuilds'), tone: 'text-muted', icon: 'bi-dash' };
    }
    const currentBucket = bucketLabel(unit);
    const explainer = `${currentBucket}`;

    const change = momentum.change;
    if (change > 0.5) {
        return {
            value: `+${Math.abs(change).toFixed(change >= 10 ? 0 : 1)}%`,
            label: explainer,
            tone: 'text-success',
            icon: 'bi-arrow-up-right',
        };
    }
    if (change < -0.5) {
        return {
            value: `-${Math.abs(change).toFixed(change <= -10 ? 0 : 1)}%`,
            label: explainer,
            tone: 'text-danger',
            icon: 'bi-arrow-down-right',
        };
    }
    return { value: '0%', label: explainer, tone: 'text-muted', icon: 'bi-arrow-left-right' };
}

function TableList({ rows, columns, emptyText }) {
    if (!rows.length) {
        return <div className="alert alert-light mb-0 py-3 small">{emptyText}</div>;
    }

    return (
        <div className="table-responsive">
            <table className="table table-striped table-hover table-sm align-middle mb-0">
                <thead>
                    <tr>
                        {columns.map((col) => (
                            <th
                                key={col.key}
                                className={`${col.align === 'right' ? 'text-end' : 'text-start'} text-uppercase text-muted small fw-semibold`}
                            >
                                {col.label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, idx) => (
                        <tr key={idx}>
                            {columns.map((col) => (
                                <td
                                    key={col.key}
                                    className={col.align === 'right' ? 'text-end' : 'text-start'}
                                    title={row[col.key]}
                                >
                                    {row[col.key] ?? '—'}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function KpiTile({ title, value, subtitle, icon, softClass, iconClass, footer = null }) {
    return (
        <div className="card border-0 shadow-sm h-100">
            <div className="card-body d-flex flex-column h-100">
                <div className="d-flex align-items-center gap-3 flex-grow-1">
                    <span className={`avatar-50 rounded d-inline-flex align-items-center justify-content-center flex-shrink-0 ${softClass}`}>
                        <i className={`bi ${icon} fs-5 ${iconClass}`}></i>
                    </span>
                    <div className="flex-grow-1">
                        <p className="text-uppercase text-muted small mb-1">{title}</p>
                        <h4 className="mb-1">{value ?? '—'}</h4>
                        <small className="text-muted d-block analytics-kpi-caption">{subtitle || '\u00a0'}</small>
                    </div>
                </div>
                {footer ? <div className="mt-auto pt-2 text-end">{footer}</div> : null}
            </div>
        </div>
    );
}

function OverviewMetric({ title, value, subtitle, softClass, icon }) {
    return (
        <div className={`rounded p-3 h-100 ${softClass}`}>
            <div className="d-flex align-items-center justify-content-between gap-2 mb-2">
                <p className="text-uppercase small fw-semibold mb-0">{title}</p>
                <i className={`bi ${icon}`}></i>
            </div>
            <h5 className="mb-1">{value ?? '—'}</h5>
            {subtitle ? <small className="d-block opacity-75">{subtitle}</small> : null}
        </div>
    );
}

function ProgressList({ rows, emptyText, barClass }) {
    if (!rows.length) {
        return <p className="text-muted mb-0 small">{emptyText}</p>;
    }

    return (
        <div className="list-group list-group-flush analytics-progress-list">
            {rows.map((row, idx) => (
                <div className="list-group-item px-0" key={`${row.label}-${idx}`}>
                    <div className="d-flex align-items-center justify-content-between gap-3 mb-2">
                        <span className="small fw-semibold text-truncate" title={row.label}>{row.label}</span>
                        <span className="small text-muted">{row.value.toLocaleString()}</span>
                    </div>
                    <div className="progress" style={{ height: '0.45rem' }}>
                        <div
                            className={`progress-bar ${barClass}`}
                            role="progressbar"
                            aria-valuemin="0"
                            aria-valuemax="100"
                            aria-valuenow={row.percent}
                            style={{ width: `${row.percent}%` }}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

function AnalyticsDashboard({ aggregates, features, range, tierLabel, tierLevel }) {
    const MAX_PIE_ITEMS = 8;
    const MAX_BAR_ITEMS = 6;
    const availableFromBasic = t('availableFromBasic');
    const availableFromPro = t('availableFromPro');
    const rangeDays = parseRangeDays(range);
    const summary = aggregates?.summary || {};
    const enabledFeatures = Array.isArray(features) ? features : [];

    const hasViews = enabledFeatures.includes('views');
    const hasClicks = enabledFeatures.includes('clicks');
    const hasUniqueVisitors = enabledFeatures.includes('unique_visitors');
    const hasTopLinks = enabledFeatures.includes('top_links');
    const hasReferrers = enabledFeatures.includes('referrers');
    const hasGeo = enabledFeatures.includes('geo');
    const hasUtm = enabledFeatures.includes('utm');

    const viewsCount = hasViews ? fallback(summary.views, summary.page_views, summary.total_views) : null;
    const uniqueCount = hasUniqueVisitors ? fallback(summary.unique_visitors, summary.visitors_unique) : null;
    const clickCount = hasClicks ? fallback(summary.clicks, summary.link_clicks) : null;

    const viewsSeries = buildSeries(aggregates, ['views', 'page_views', 'view'], rangeDays);
    const viewsSeriesSafe = viewsSeries.points.length
        ? viewsSeries
        : viewsCount !== null && viewsCount !== undefined
            ? { unit: 'day', points: [{ ts: new Date().toISOString(), value: toNumber(viewsCount) }] }
            : { unit: viewsSeries.unit, points: [] };

    const topLinks = hasTopLinks ? (aggregates?.top_links || []).slice(0, 12) : [];
    const topLinkGraph = topLinks
        .map((link) => ({
            name: link.title || link.name || (link.link_id ? `Link #${link.link_id}` : t('untitled')),
            value: toNumber(link.clicks ?? link.count),
        }))
        .sort((a, b) => b.value - a.value);
    const topLinkGraphLimited = topLinkGraph.slice(0, MAX_BAR_ITEMS);

    const referrers = hasReferrers ? aggregates?.referrers || [] : [];
    const referrerGraph = referrers
        .map((ref) => ({
            name: ref.domain || ref.label || t('unknown'),
            value: toNumber(ref.count),
        }))
        .sort((a, b) => b.value - a.value);
    const referrerGraphLimited = referrerGraph.slice(0, MAX_PIE_ITEMS);

    const countries = hasGeo ? aggregates?.geo?.countries || aggregates?.countries || [] : [];
    const countryGraph = countries
        .map((country) => ({
            name: country.name || country.code || t('unknown'),
            value: toNumber(country.count),
        }))
        .sort((a, b) => b.value - a.value);
    const countryGraphLimited = countryGraph.slice(0, MAX_PIE_ITEMS);

    const utmCombos = (hasUtm ? aggregates?.utm_sets || [] : [])
        .map((row) => {
            const parts = [
                cleanUtmPart(row.source),
                cleanUtmPart(row.medium),
                cleanUtmPart(row.campaign),
                cleanUtmPart(row.id ?? row.utm_id),
                cleanUtmPart(row.term),
                cleanUtmPart(row.content),
            ].filter(Boolean);
            return {
                name: parts.join(' / ') || t('unknown'),
                value: toNumber(row.count),
            };
        })
        .sort((a, b) => b.value - a.value);
    const utmBarGraphLimited = utmCombos.slice(0, MAX_BAR_ITEMS);

    const viewsValue = formatCount(viewsCount);
    const clicksValue = hasClicks ? formatCount(clickCount) : null;
    const ctrValue = hasClicks
        && viewsCount !== null
        && viewsCount !== undefined
        && clickCount !== null
        && clickCount !== undefined
        && Number(viewsCount) > 0
        ? formatPercent((Number(clickCount) / Number(viewsCount)) * 100)
        : null;

    const topCountry = countryGraph[0] || null;
    const topCountryLabel = hasGeo ? topCountry?.name || null : availableFromBasic;
    const topCountryCount = topCountry ? formatCount(topCountry.value) : null;

    const topLink = topLinkGraph[0] || null;
    const topReferrer = referrerGraph[0] || null;
    const topLinkClicks = topLink ? formatCount(topLink.value) : null;
    const topReferrerClicks = topReferrer ? formatCount(topReferrer.value) : null;

    const topLinkShares = buildShareRows(
        topLinks,
        (link) => link.title || link.name || (link.link_id ? `Link #${link.link_id}` : 'Untitled'),
        (link) => link.clicks ?? link.count,
    );
    const referrerShares = buildShareRows(
        referrers,
        (ref) => ref.domain || ref.label || 'Unknown',
        (ref) => ref.count,
    );
    const countryShares = buildShareRows(
        countries,
        (country) => country.name || country.code || 'Unknown',
        (country) => country.count,
    );

    const momentum = momentumMeta(computeMomentum(viewsSeriesSafe.points), viewsSeriesSafe.unit);
    const graphPlaceholder = <div className="alert alert-light mb-0 py-3 small">{t('noDataRange')}</div>;
    const renderDbIpAttribution = () => (
        <a
            href="https://db-ip.com"
            className="small text-muted text-decoration-none"
            style={{ fontSize: '0.72rem', opacity: 0.75 }}
            target="_blank"
            rel="noopener noreferrer"
        >
            IP Geolocation by DB-IP
        </a>
    );
    const visibilitySubtitle = hasUniqueVisitors && uniqueCount
        ? `${formatCount(uniqueCount)} ${t('uniqueVisitors')}`
        : null;
    const pageViewsSubtitle = hasUniqueVisitors
        ? (uniqueCount ? `${formatCount(uniqueCount)} ${t('uniqueVisitors')}` : t('uniqueVisitorsUnavailable'))
        : availableFromPro;
    const topCountrySubtitle = hasGeo
        ? (topCountryCount ? `${topCountryCount} ${t('visits')}` : t('noGeoData'))
        : availableFromBasic;

    return (
        <div className="analytics-dashboard-wrap pb-4">
            <div className="row g-3">
                <div className="col-12">
                    <div className="card border-0 shadow-sm analytics-overview-card">
                        <div className="card-body p-4">
                            <div className="d-flex flex-column flex-xl-row align-items-xl-center justify-content-between gap-3">
                                <div>
                                    <p className="text-uppercase text-primary small fw-semibold mb-1">{t('performanceOverview')}</p>
                                    <h5 className="mb-1">{t('analyticsCockpit')}</h5>
                                    <p className="text-muted mb-0">{t('liveSummary')}</p>
                                </div>
                                <div className="d-flex flex-wrap gap-2">
                                    <span className="badge rounded-pill bg-soft-primary text-primary">{t('tier')}: {tierLabel || 'Standard'}</span>
                                    <span className="badge rounded-pill bg-soft-info text-info">Range: {String(range || '').toUpperCase()}</span>
                                    <span className="badge rounded-pill bg-soft-success text-success">{hasViews ? t('trackingActive') : availableFromBasic}</span>
                                </div>
                            </div>
                            <div className="row g-3 mt-1">
                                <div className="col-lg-4">
                                    <OverviewMetric
                                        title={t('sichtbarkeit')}
                                        value={formatCompact(viewsCount)}
                                        subtitle={visibilitySubtitle}
                                        softClass="bg-soft-primary text-primary"
                                        icon="bi-eye"
                                    />
                                </div>
                                <div className="col-lg-4">
                                    <OverviewMetric
                                        title={t('conversion')}
                                        value={ctrValue || '—'}
                                        subtitle={null}
                                        softClass="bg-soft-success text-success"
                                        icon="bi-graph-up-arrow"
                                    />
                                </div>
                                <div className="col-lg-4">
                                    <OverviewMetric
                                        title={t('momentum')}
                                        value={<span className={momentum.tone}><i className={`bi ${momentum.icon} me-1`}></i>{momentum.value}</span>}
                                        subtitle={null}
                                        softClass="bg-soft-info text-info"
                                        icon="bi-activity"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="row g-3 mt-0">
                <div className="col-md-6 col-xl-3">
                    <KpiTile
                        title={t('pageViews')}
                        value={viewsValue}
                        subtitle={pageViewsSubtitle}
                        icon="bi-eye"
                        softClass="bg-soft-primary"
                        iconClass="text-primary"
                    />
                </div>
                <div className="col-md-6 col-xl-3">
                    <KpiTile
                        title={t('linkClicks')}
                        value={clicksValue}
                        subtitle={t('trackedCtaActivity')}
                        icon="bi-cursor"
                        softClass="bg-soft-info"
                        iconClass="text-info"
                    />
                </div>
                <div className="col-md-6 col-xl-3">
                    <KpiTile
                        title={t('ctr')}
                        value={ctrValue}
                        subtitle={t('clicksDividedByPageViews')}
                        icon="bi-bullseye"
                        softClass="bg-soft-success"
                        iconClass="text-success"
                    />
                </div>
                <div className="col-md-6 col-xl-3">
                    <KpiTile
                        title={t('topCountry')}
                        value={topCountryLabel}
                        subtitle={topCountrySubtitle}
                        icon="bi-globe2"
                        softClass="bg-soft-warning"
                        iconClass="text-warning"
                        footer={renderDbIpAttribution()}
                    />
                </div>
            </div>

            <div className="row g-3 mt-0">
                <div className="col-xl-4">
                    <div className="card border-0 shadow-sm h-100">
                        <div className="card-body">
                            <div className="d-flex align-items-center justify-content-between gap-3 mb-3">
                                <div>
                                    <p className="text-uppercase text-muted small mb-1">{t('topLinksSnapshot')}</p>
                                    <h6 className="mb-0">{t('highestClickConcentration')}</h6>
                                </div>
                                <span className="badge rounded-pill bg-soft-primary text-primary">{topLink ? topLinkClicks : '—'}</span>
                            </div>
                            <ProgressList
                                rows={topLinkShares}
                                emptyText={hasTopLinks ? t('noLinkClickData') : availableFromBasic}
                                barClass="bg-primary"
                            />
                        </div>
                    </div>
                </div>
                <div className="col-xl-4">
                    <div className="card border-0 shadow-sm h-100">
                        <div className="card-body">
                            <div className="d-flex align-items-center justify-content-between gap-3 mb-3">
                                <div>
                                    <p className="text-uppercase text-muted small mb-1">{t('referrerShare')}</p>
                                    <h6 className="mb-0">{t('acquisitionChannels')}</h6>
                                </div>
                                <span className="badge rounded-pill bg-soft-info text-info">{topReferrer ? topReferrerClicks : '—'}</span>
                            </div>
                            <ProgressList
                                rows={referrerShares}
                                emptyText={hasReferrers ? t('noReferrerDataRange') : availableFromBasic}
                                barClass="bg-info"
                            />
                        </div>
                    </div>
                </div>
                <div className="col-xl-4">
                    <div className="card border-0 shadow-sm h-100">
                        <div className="card-body d-flex flex-column h-100">
                            <div className="d-flex align-items-center justify-content-between gap-3 mb-3">
                                <div>
                                    <p className="text-uppercase text-muted small mb-1">{t('countryShare')}</p>
                                    <h6 className="mb-0">{t('geographicConcentration')}</h6>
                                </div>
                                <span className="badge rounded-pill bg-soft-warning text-warning">{topCountryCount || '—'}</span>
                            </div>
                            <ProgressList
                                rows={countryShares}
                                emptyText={hasGeo ? t('noGeographicData') : availableFromBasic}
                                barClass="bg-warning"
                            />
                            <div className="mt-auto pt-2 text-end">
                                {renderDbIpAttribution()}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div className="row g-3 mt-0">
                <div className="col-12">
                    <AnalyticsCard
                        title={t('pageViewsOverTime')}
                        description={t('trafficVolume')}
                        numbers={
                            <TableList
                                rows={viewsSeriesSafe.points.map((point) => ({
                                    date: formatSeriesLabel(point.ts, viewsSeriesSafe.unit),
                                    views: toNumber(point.value).toLocaleString(),
                                }))}
                                columns={[
                                    { key: 'date', label: t('date') },
                                    { key: 'views', label: t('views'), align: 'right' },
                                ]}
                                emptyText={t('noTimeSeriesData')}
                            />
                        }
                        graph={
                            viewsSeriesSafe.points.length ? (
                                <AnalyticsGraph type="line" data={viewsSeriesSafe.points} unit={viewsSeriesSafe.unit} />
                            ) : (
                                graphPlaceholder
                            )
                        }
                        lockedMessage={!hasViews ? availableFromBasic : null}
                    />
                </div>
            </div>

            <div className="row g-3 mt-0">
                <div className="col-xl-6">
                    <AnalyticsCard
                        title={t('linkClickRanking')}
                        description={t('bestPerformingLinks')}
                        numbers={
                            <TableList
                                rows={topLinks.map((link) => ({
                                    name: link.title || link.name || (link.link_id ? `Link #${link.link_id}` : t('untitled')),
                                    clicks: toNumber(link.clicks ?? link.count).toLocaleString(),
                                }))}
                                columns={[
                                    { key: 'name', label: t('link') },
                                    { key: 'clicks', label: t('clicks'), align: 'right' },
                                ]}
                                emptyText={t('noClickData')}
                            />
                        }
                        graph={topLinkGraphLimited.length ? <AnalyticsGraph type="hbar" data={topLinkGraphLimited} /> : graphPlaceholder}
                        lockedMessage={!hasTopLinks ? availableFromBasic : null}
                    />
                </div>
                <div className="col-xl-6">
                    <AnalyticsCard
                        title={t('countryDistribution')}
                        description={t('whereVisitorsLocated')}
                        numbers={
                            <TableList
                                rows={countries.map((country) => ({
                                    country: country.name || country.code || t('unknown'),
                                    views: toNumber(country.count).toLocaleString(),
                                }))}
                                columns={[
                                    { key: 'country', label: t('country') },
                                    { key: 'views', label: t('visits'), align: 'right' },
                                ]}
                                emptyText={t('noCountryData')}
                            />
                        }
                        graph={countryGraphLimited.length ? <AnalyticsGraph type="donut" data={countryGraphLimited} /> : graphPlaceholder}
                        footer={renderDbIpAttribution()}
                        lockedMessage={!hasGeo ? availableFromBasic : null}
                    />
                </div>
            </div>

            <div className="row g-3 mt-0">
                <div className="col-xl-6">
                    <AnalyticsCard
                        title={t('referrerBreakdown')}
                        description={t('topExternalSources')}
                        numbers={
                            <TableList
                                rows={referrers.map((ref) => ({
                                    domain: ref.domain || ref.label || t('unknown'),
                                    views: toNumber(ref.count).toLocaleString(),
                                }))}
                                columns={[
                                    { key: 'domain', label: 'Domain' },
                                    { key: 'views', label: t('visits'), align: 'right' },
                                ]}
                                emptyText={t('noReferrerData')}
                            />
                        }
                        graph={referrerGraphLimited.length ? <AnalyticsGraph type="donut" data={referrerGraphLimited} /> : graphPlaceholder}
                        lockedMessage={!hasReferrers ? availableFromBasic : null}
                    />
                </div>
                <div className="col-xl-6">
                    <AnalyticsCard
                        title={t('utmCombinations')}
                        description={t('utmCombinationsDesc')}
                        numbers={
                            <TableList
                                rows={utmCombos.map((row) => ({
                                    label: row.name,
                                    views: row.value.toLocaleString(),
                                }))}
                                columns={[
                                    { key: 'label', label: t('utmCombination') },
                                    { key: 'views', label: t('views'), align: 'right' },
                                ]}
                                emptyText={t('noUtmData')}
                            />
                        }
                        graph={utmBarGraphLimited.length ? <AnalyticsGraph type="hbar" data={utmBarGraphLimited} /> : graphPlaceholder}
                        lockedMessage={!hasUtm ? availableFromPro : null}
                    />
                </div>
            </div>
        </div>
    );
}

function mountAnalytics() {
    const el = document.getElementById('analytics-dashboard-root');
    if (!el) return;

    const dataset = el.dataset || {};
    const payload = window.__LS_ANALYTICS__ || {};
    _i18n = payload.translations || {};
    const props = {
        aggregates: payload.aggregates || {},
        features: payload.features || [],
        range: payload.range || dataset.range || '7d',
        tierLabel: payload.tierLabel || dataset.tierLabel || null,
        tierLevel: payload.tierLevel || dataset.tierLevel || null,
    };

    const downloadBtn = document.getElementById('analytics-download-csv');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', () => downloadAnalyticsCsv(props));
    }

    const root = createRoot(el);
    root.render(<AnalyticsDashboard {...props} />);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAnalytics);
} else {
    mountAnalytics();
}
