import React from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    LabelList,
    Legend,
    Line,
    LineChart,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

const DEFAULT_COLORS = ['#3a57e8', '#08b1ba', '#1aa053', '#f16a1b', '#e64566', '#6c757d', '#5f3dc4', '#0f172a'];
const themeDefaults = { surface: '#ffffff', text: '#0f172a', muted: '#475569', border: '#e2e8f0' };

const dateFormatter = new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' });
const monthFormatter = new Intl.DateTimeFormat(undefined, { month: 'short', year: '2-digit' });
const hourFormatter = new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' });

function formatTick(unit) {
    return (value) => {
        if (unit === 'label') return value;
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return value;
        if (unit === 'hour') return hourFormatter.format(date);
        if (unit === 'month') return monthFormatter.format(date);
        return dateFormatter.format(date);
    };
}

function truncate(text, limit = 20) {
    if (!text) return '';
    return text.length > limit ? `${text.slice(0, limit - 1)}…` : text;
}

function ellipsize(text, limit) {
    if (limit <= 1) return '…';
    if (text.length <= limit) return text;
    return `${text.slice(0, limit - 1)}…`;
}

function wrapLabel(text, maxCharsPerLine = 16, maxLines = 2) {
    const safeText = `${text ?? ''}`.trim();
    if (!safeText) return [''];

    const tokens = safeText.split(/[\s/_-]+/).filter(Boolean);
    const parts = tokens.length ? tokens : [safeText];
    const lines = [];
    let current = '';
    let truncated = false;

    const pushLine = (line) => {
        if (!line) return;
        if (lines.length < maxLines) {
            lines.push(line);
            return;
        }
        truncated = true;
    };

    for (const part of parts) {
        if (truncated) break;
        let remaining = part;

        while (remaining.length > maxCharsPerLine) {
            if (current) {
                pushLine(current);
                current = '';
                if (truncated) break;
            }
            pushLine(remaining.slice(0, maxCharsPerLine));
            remaining = remaining.slice(maxCharsPerLine);
            if (truncated) break;
        }
        if (truncated) break;

        const next = current ? `${current} ${remaining}` : remaining;
        if (next.length > maxCharsPerLine) {
            if (current) pushLine(current);
            current = remaining;
        } else {
            current = next;
        }
    }

    if (!truncated && current) {
        pushLine(current);
    }

    if (truncated && lines.length) {
        lines[lines.length - 1] = ellipsize(lines[lines.length - 1], maxCharsPerLine);
    }

    return lines.length ? lines : [''];
}

function formatCount(value) {
    const num = Number(value);
    if (!Number.isFinite(num)) return value;
    return Math.round(num).toLocaleString();
}

function DefaultTooltip({ active, payload, label, suffix, theme }) {
    if (!active || !payload?.length) return null;
    const surface = theme?.surface || themeDefaults.surface;
    const text = theme?.text || themeDefaults.text;
    const muted = theme?.muted || themeDefaults.muted;
    const border = theme?.border || themeDefaults.border;
    return (
        <div
            className="rounded p-2 shadow-sm"
            style={{ backgroundColor: surface, color: text, border: `1px solid ${border}` }}
        >
            <div className="small" style={{ color: muted }}>
                {label}
            </div>
            {payload.map((item) => (
                <div key={item.dataKey} className="small fw-semibold" style={{ color: text }}>
                    {item.name || item.dataKey}: {formatCount(item.value)} {suffix || ''}
                </div>
            ))}
        </div>
    );
}

function HBarTooltip({ active, payload, theme }) {
    if (!active || !payload?.length) return null;
    const item = payload[0];
    const label = item?.payload?.name || item?.name || '';
    const surface = theme?.surface || themeDefaults.surface;
    const text = theme?.text || themeDefaults.text;
    const muted = theme?.muted || themeDefaults.muted;
    const border = theme?.border || themeDefaults.border;

    return (
        <div
            className="rounded p-2 shadow-sm"
            style={{ backgroundColor: surface, color: text, border: `1px solid ${border}` }}
        >
            <div className="small fw-semibold" style={{ color: text }}>
                {label}
            </div>
            <div className="small" style={{ color: muted }}>
                {formatCount(item?.value)}
            </div>
        </div>
    );
}

function PieTooltip({ active, payload, theme }) {
    if (!active || !payload?.length) return null;
    const item = payload[0];
    const surface = theme?.surface || themeDefaults.surface;
    const text = theme?.text || themeDefaults.text;
    const muted = theme?.muted || themeDefaults.muted;
    const border = theme?.border || themeDefaults.border;
    return (
        <div
            className="rounded p-2 shadow-sm"
            style={{ backgroundColor: surface, color: text, border: `1px solid ${border}` }}
        >
            <div className="small fw-semibold" style={{ color: text }}>
                {item.name}
            </div>
            <div className="small" style={{ color: muted }}>
                {formatCount(item.value)}
            </div>
        </div>
    );
}

function readThemeTokens() {
    if (typeof window === 'undefined' || !document.body) {
        return { surface: '#ffffff', text: '#0f172a', muted: '#475569', border: '#e2e8f0' };
    }
    const bodyStyles = getComputedStyle(document.body);
    const sidebar = document.querySelector('.sidebar');
    const sidebarBg = sidebar ? getComputedStyle(sidebar).backgroundColor : null;
    // Border from a temporary probe
    const probe = document.createElement('div');
    probe.className = 'border';
    probe.style.cssText = 'position:absolute;left:-9999px;top:-9999px;height:0;width:0;';
    document.body.appendChild(probe);
    const border = getComputedStyle(probe).borderTopColor || '#e2e8f0';
    probe.remove();

    return {
        surface: sidebarBg || '#ffffff',
        text: (bodyStyles.getPropertyValue('color') || '').trim() || '#0f172a',
        muted: (bodyStyles.getPropertyValue('--bs-secondary-color') || '').trim() || '#475569',
        border,
    };
}

function readChartPalette() {
    if (typeof window === 'undefined' || !document.documentElement) {
        return DEFAULT_COLORS;
    }

    const rootStyles = getComputedStyle(document.documentElement);
    const readVar = (name, fallback) => {
        const value = (rootStyles.getPropertyValue(name) || '').trim();
        return value || fallback;
    };

    return [
        readVar('--bs-primary', DEFAULT_COLORS[0]),
        readVar('--bs-info', DEFAULT_COLORS[1]),
        readVar('--bs-success', DEFAULT_COLORS[2]),
        readVar('--bs-warning', DEFAULT_COLORS[3]),
        readVar('--bs-danger', DEFAULT_COLORS[4]),
        readVar('--bs-secondary', DEFAULT_COLORS[5]),
        readVar('--bs-indigo', DEFAULT_COLORS[6]),
        readVar('--bs-dark', DEFAULT_COLORS[7]),
    ];
}

export function AnalyticsGraph({
    type,
    data = [],
    height = 220,
    unit,
    primaryKey = 'value',
    secondaryKey = 'secondaryValue',
    colors = null,
}) {
    const theme = readThemeTokens();
    const palette = Array.isArray(colors) && colors.length ? colors : readChartPalette();
    const isTouch = typeof window !== 'undefined' && ('ontouchstart' in window || navigator.maxTouchPoints > 0);
    const tickColor = theme.muted;
    const gridColor = theme.border;
    const labelColor = theme.text;
    const labelFontSize = 11;
    const horizontalPadding = 12;
    const paddedContainerStyle = { paddingLeft: horizontalPadding, paddingRight: horizontalPadding };

    const graphHeight = Math.max(height, 180);
    if (!data || data.length === 0) {
        return (
            <div className="d-flex align-items-center justify-content-center small" style={{ minHeight: 180, color: tickColor }}>
                Keine Daten vorhanden
            </div>
        );
    }

    const getColor = (index) => palette[index % palette.length];

    if (type === 'line') {
        return (
            <div style={paddedContainerStyle}>
                <ResponsiveContainer width="100%" height={graphHeight}>
                    <LineChart data={data} margin={{ top: 12, left: 12, right: 12, bottom: 4 }}>
                        <CartesianGrid strokeDasharray="4 4" stroke={gridColor} />
                        <XAxis
                            dataKey="ts"
                            tickFormatter={formatTick(unit)}
                            minTickGap={12}
                            tick={{ fontSize: 12, fill: tickColor }}
                        />
                        <YAxis
                            tick={{ fontSize: 12, fill: tickColor }}
                            tickFormatter={formatCount}
                            allowDecimals={false}
                        />
                        <Tooltip content={<DefaultTooltip suffix="" theme={theme} />} labelFormatter={formatTick(unit)} />
                        <Line
                            type="linear"
                            dataKey={primaryKey}
                            stroke={palette[0]}
                            strokeWidth={3}
                            dot={false}
                            activeDot={{ r: 5 }}
                        >
                            <LabelList
                                dataKey={primaryKey}
                                position="top"
                                formatter={formatCount}
                                fill={labelColor}
                                style={{ fontSize: labelFontSize }}
                            />
                        </Line>
                    </LineChart>
                </ResponsiveContainer>
            </div>
        );
    }

    if (type === 'bar') {
        return (
            <div style={paddedContainerStyle}>
                <ResponsiveContainer width="100%" height={graphHeight}>
                    <BarChart data={data} margin={{ top: 12, left: 12, right: 12, bottom: 4 }} barSize={24}>
                        <XAxis dataKey="ts" tickFormatter={formatTick(unit)} tick={{ fontSize: 12, fill: tickColor }} />
                        <YAxis
                            tick={false}
                            tickLine={false}
                            axisLine={false}
                            tickFormatter={formatCount}
                            allowDecimals={false}
                            width={0}
                        />
                        <Tooltip content={<DefaultTooltip suffix="" theme={theme} />} labelFormatter={formatTick(unit)} />
                        <Bar dataKey={primaryKey} radius={[6, 6, 0, 0]} fill={palette[0]}>
                            <LabelList
                                dataKey={primaryKey}
                                position="top"
                                formatter={formatCount}
                                fill={labelColor}
                                style={{ fontSize: labelFontSize }}
                            />
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </div>
        );
    }

    if (type === 'hbar') {
        const maxLabelLines = 2;
        const labelPadding = 16;
        const approxCharWidth = labelFontSize * 0.62;
        const minAxisWidth = 96;
        const maxAxisWidth = 220;
        const maxLabelLength = data.reduce((max, item) => {
            const name = item?.name ?? '';
            return Math.max(max, String(name).length);
        }, 0);
        const maxAllowedChars = Math.max(8, Math.floor((maxAxisWidth - labelPadding) / approxCharWidth));
        const maxCharsPerLine = Math.min(maxAllowedChars, Math.max(6, maxLabelLength));
        const wrappedLabels = data.map((item) => wrapLabel(item?.name ?? '', maxCharsPerLine, maxLabelLines));
        const maxLineLength = wrappedLabels.reduce((max, lines) => {
            const lineMax = lines.reduce((lineMaxValue, line) => Math.max(lineMaxValue, line.length), 0);
            return Math.max(max, lineMax);
        }, 0);
        const computedAxisWidth = Math.ceil(maxLineLength * approxCharWidth) + labelPadding;
        const axisWidth = Math.min(maxAxisWidth, Math.max(minAxisWidth, computedAxisWidth));
        const tickLineHeight = Math.round(labelFontSize * 1.2);

        const HBarAxisTick = ({ x, y, payload }) => {
            const value = `${payload?.value ?? ''}`;
            const lines = wrapLabel(value, maxCharsPerLine, maxLabelLines);
            const startY = y - ((lines.length - 1) * tickLineHeight) / 2;
            const tickX = x - 6;

            return (
                <text x={tickX} y={startY} textAnchor="end" fill={tickColor} fontSize={labelFontSize}>
                    {lines.map((line, idx) => (
                        <tspan key={`${payload?.index ?? 'tick'}-${idx}`} x={tickX} dy={idx === 0 ? 0 : tickLineHeight}>
                            {line}
                        </tspan>
                    ))}
                </text>
            );
        };

        return (
            <div style={paddedContainerStyle}>
                <ResponsiveContainer width="100%" height={graphHeight}>
                    <BarChart
                        layout="vertical"
                        data={data}
                        margin={{ top: 8, right: 16, left: 16, bottom: 8 }}
                        barSize={22}
                    >
                        <XAxis
                            type="number"
                            tick={{ fontSize: 12, fill: tickColor }}
                            tickFormatter={formatCount}
                            allowDecimals={false}
                        />
                        <YAxis
                            dataKey="name"
                            type="category"
                            width={axisWidth}
                            tick={HBarAxisTick}
                            interval={0}
                            tickLine={false}
                            axisLine={false}
                        />
                        <Tooltip trigger={isTouch ? 'click' : 'hover'} content={<HBarTooltip theme={theme} />} />
                        <Bar dataKey={primaryKey} radius={[0, 6, 6, 0]}>
                            {data.map((_, idx) => (
                                <Cell key={idx} fill={getColor(idx)} />
                            ))}
                            <LabelList
                                dataKey={primaryKey}
                                position="right"
                                formatter={formatCount}
                                fill={labelColor}
                                style={{ fontSize: labelFontSize }}
                            />
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </div>
        );
    }

    if (type === 'donut' || type === 'pie') {
        const safeData = Array.isArray(data) ? data : [];
        const hasData = safeData.length > 0;
        const chartData = hasData ? safeData : [{ name: 'No data', [primaryKey]: 1, isPlaceholder: true }];

        const innerRadius = type === 'donut' ? '55%' : 0;
        const outerRadius = '86%';
        const pieMargin = { top: 12, right: 12, bottom: 12, left: 12 };

        const legendItems = hasData
            ? safeData.map((item, idx) => {
                  const val = Number(item[primaryKey]) || 0;
                  return { name: item.name || 'Unknown', value: val, color: getColor(idx) };
              })
            : [];
        const legendTotal = legendItems.reduce((sum, item) => sum + item.value, 0);
        const pieHeight = Math.max(height, 260);

        return (
            <div className="d-flex flex-column flex-md-row align-items-md-start gap-3 w-100" style={paddedContainerStyle}>
                <div className="flex-grow-1 w-100" style={{ minWidth: 180, height: pieHeight }}>
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart margin={pieMargin}>
                            <Pie
                                data={chartData}
                                dataKey={primaryKey}
                                nameKey="name"
                                cx="50%"
                                cy="50%"
                                innerRadius={innerRadius}
                                outerRadius={outerRadius}
                                paddingAngle={0}
                                stroke="none"
                                labelLine={false}
                                isAnimationActive={false}
                            >
                                {chartData.map((item, idx) => (
                                    <Cell key={idx} fill={item.isPlaceholder ? '#e2e8f0' : getColor(idx)} />
                                ))}
                            </Pie>
                            <Tooltip content={<PieTooltip theme={theme} />} />
                        </PieChart>
                    </ResponsiveContainer>
                </div>
                <div className="w-100 flex-shrink-0" style={{ maxWidth: 220 }}>
                    {legendItems.length ? (
                        <ul className="list-unstyled small mb-0 d-grid" style={{ gap: '0.5rem' }}>
                            {legendItems.map((item, idx) => (
                                <li key={idx} className="d-flex align-items-center justify-content-between gap-2">
                                    <span className="d-inline-flex align-items-center gap-2 text-truncate" style={{ maxWidth: 140 }}>
                                        <span
                                            className="d-inline-block rounded-circle flex-shrink-0"
                                            style={{ backgroundColor: item.color, width: 12, height: 12 }}
                                        />
                                        <span className="text-truncate" title={item.name}>{truncate(item.name, 26)}</span>
                                    </span>
                                    <span className="flex-shrink-0 fw-semibold">
                                        {item.value.toLocaleString()}
                                        {legendTotal > 0 ? ` (${Math.round((item.value / legendTotal) * 100)}%)` : ''}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <div className="alert alert-light mb-0 py-2 small">No data available</div>
                    )}
                </div>
            </div>
        );
    }

    if (type === 'dual-line') {
        return (
            <div style={paddedContainerStyle}>
                <ResponsiveContainer width="100%" height={graphHeight}>
                    <LineChart data={data} margin={{ top: 12, left: 12, right: 12, bottom: 4 }}>
                        <CartesianGrid strokeDasharray="4 4" stroke={gridColor} />
                        <XAxis dataKey="ts" tickFormatter={formatTick(unit)} tick={{ fontSize: 12, fill: tickColor }} />
                        <YAxis
                            tick={{ fontSize: 12, fill: tickColor }}
                            tickFormatter={formatCount}
                            allowDecimals={false}
                        />
                        <Tooltip content={<DefaultTooltip suffix="" theme={theme} />} labelFormatter={formatTick(unit)} />
                        <Legend />
                        <Line
                            type="linear"
                            name="Unique"
                            dataKey={primaryKey}
                            stroke={palette[0]}
                            strokeWidth={3}
                            dot={false}
                            activeDot={{ r: 5 }}
                        >
                            <LabelList
                                dataKey={primaryKey}
                                position="top"
                                formatter={formatCount}
                                fill={labelColor}
                                style={{ fontSize: labelFontSize }}
                            />
                        </Line>
                        <Line
                            type="linear"
                            name="Returning"
                            dataKey={secondaryKey}
                            stroke={palette[1]}
                            strokeWidth={3}
                            dot={false}
                            activeDot={{ r: 5 }}
                        >
                            <LabelList
                                dataKey={secondaryKey}
                                position="top"
                                formatter={formatCount}
                                fill={labelColor}
                                style={{ fontSize: labelFontSize }}
                            />
                        </Line>
                    </LineChart>
                </ResponsiveContainer>
            </div>
        );
    }

    if (type === 'sparkline') {
        return (
            <div style={paddedContainerStyle}>
                <ResponsiveContainer width="100%" height={graphHeight}>
                    <LineChart data={data} margin={{ top: 8, left: 8, right: 8, bottom: 4 }}>
                        <XAxis dataKey="ts" hide />
                        <YAxis hide domain={['dataMin - 1', 'dataMax + 1']} />
                        <Tooltip content={<DefaultTooltip suffix="Sessions" theme={theme} />} labelFormatter={formatTick(unit)} />
                        <Line type="linear" dataKey={primaryKey} stroke={palette[0]} strokeWidth={2} dot={false} />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        );
    }

    return null;
}

export default AnalyticsGraph;
