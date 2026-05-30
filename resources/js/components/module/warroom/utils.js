export function pctChange(current, previous) {
    if (!previous || previous === 0) return null;
    return ((current - previous) / Math.abs(previous)) * 100;
}

export function deltaStr(current, previous) {
    const pct = pctChange(current, previous);
    if (pct === null) return null;
    const sign = pct >= 0 ? '+' : '';
    return `${sign}${pct.toFixed(1)}%`;
}

export function deltaDir(current, previous) {
    const pct = pctChange(current, previous);
    if (pct === null) return 'neutral';
    return pct >= 0 ? 'up' : 'down';
}

export function formatCurrency(value) {
    if (value === null || value === undefined) return null;
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(value);
}

export function formatNumber(value) {
    if (value === null || value === undefined) return null;
    return new Intl.NumberFormat('es-MX').format(value);
}
