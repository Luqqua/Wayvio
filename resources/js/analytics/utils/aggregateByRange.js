/**
 * @typedef {Object} RawEvent
 * @property {string|number|Date} ts Timestamp for the event (ISO string, unix ms, or Date)
 * @property {number} [value] Numeric value for the event (defaults to 1 when missing)
 * @property {number} [count] Alternate numeric field to fall back to
 */

/**
 * @typedef {Object} AggregatedPoint
 * @property {"hour"|"day"|"week"|"month"} unit
 * @property {{ ts: string, value: number }[]} points
 */

/**
 * Aggregates arbitrary events into day/week/month buckets based on the selected range.
 * Backend contracts stay untouched; this purely reshapes existing payloads for charts.
 *
 * @param {RawEvent[]} data Array of raw events with timestamps
 * @param {number} range Number of days represented by the dataset
 * @returns {AggregatedPoint}
 */
export function aggregateByRange(data, range) {
    const normalizedRange = Number.isFinite(range) ? range : 0;
    const unit = normalizedRange <= 1 ? 'hour' : normalizedRange <= 30 ? 'day' : normalizedRange <= 90 ? 'week' : 'month';
    const buckets = new Map();

    data.forEach((item) => {
        const rawTs = item?.ts ?? item?.timestamp ?? item?.occurred_at ?? item?.date;
        const numericValue = Number(item?.value ?? item?.count ?? 1);
        if (!rawTs || Number.isNaN(numericValue)) {
            return;
        }

        const date = new Date(rawTs);
        if (Number.isNaN(date.getTime())) {
            return;
        }

        const bucketKey = formatBucket(date, unit);
        buckets.set(bucketKey, (buckets.get(bucketKey) || 0) + numericValue);
    });

    const sorted = Array.from(buckets.entries())
        .map(([key, value]) => ({ ts: key, value }))
        .sort((a, b) => new Date(a.ts).getTime() - new Date(b.ts).getTime());

    return { unit, points: sorted };
}

function formatBucket(date, unit) {
    if (unit === 'hour') {
        return formatHour(startOfHour(date));
    }
    if (unit === 'week') {
        return formatDate(startOfWeek(date));
    }
    if (unit === 'month') {
        return formatMonthStart(date);
    }
    return formatDate(startOfDay(date));
}

function startOfDay(date) {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    return d;
}

function startOfWeek(date) {
    const d = startOfDay(date);
    // Treat Monday as start of week for consistent grouping
    const day = d.getDay();
    const diff = (day === 0 ? -6 : 1) - day;
    d.setDate(d.getDate() + diff);
    return d;
}

function startOfHour(date) {
    const d = new Date(date);
    d.setMinutes(0, 0, 0);
    return d;
}

function formatDate(date) {
    return date.toISOString().split('T')[0];
}

function formatHour(date) {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    const hour = `${date.getHours()}`.padStart(2, '0');
    return `${year}-${month}-${day}T${hour}:00:00Z`;
}

function formatMonthStart(date) {
    const d = startOfDay(date);
    d.setDate(1);
    const year = d.getFullYear();
    const month = `${d.getMonth() + 1}`.padStart(2, '0');
    return `${year}-${month}-01`;
}
