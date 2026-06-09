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

const MONTHS_ES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

// Convierte el weekly_series de KpiController al formato que espera WarroomLineSeries
// Input:  { labels: ['Sem 1','Sem 2','Sem 3','Sem 4'], series: [{period:'2026-06', data:[n,...]},...] }
// Output: { labels: [...], series: [{nombre: 'Jun 2026', datos: [...]},...] }
export function transformWeeklySeries(ws) {
    if (!ws?.series?.length) return { labels: [], series: [] };
    return {
        labels: ws.labels ?? [],
        series: ws.series.map(s => {
            const [y, m] = (s.period ?? '').split('-');
            const label  = m ? `${MONTHS_ES[parseInt(m, 10) - 1]} ${y}` : (s.period ?? '?');
            return { nombre: label, datos: s.data ?? [] };
        }),
    };
}
