<template>
    <div class="wr-view wr-view-finanzas">
        <div class="wr-kpi-grid wr-kpi-grid-2">
            <KpiCard
                label="MRR (Ingresos recurrentes)"
                :value="formatCurrency(kpis?.mrr?.current)"
                :previousValue="formatCurrency(kpis?.mrr?.previous)"
                :delta="deltaStr(kpis?.mrr?.current, kpis?.mrr?.previous)"
                :deltaDirection="deltaDir(kpis?.mrr?.current, kpis?.mrr?.previous)"
                accent="green"
                icon="ti-chart-line"
                size="hero"
                :loading="loading"
            />
            <KpiCard
                label="Por cobrar"
                :value="formatCurrency(kpis?.por_cobrar?.amount)"
                :delta="kpis?.por_cobrar?.count ? `${kpis.por_cobrar.count} facturas` : null"
                delta-direction="neutral"
                accent="orange"
                icon="ti-receipt"
                size="hero"
                :loading="loading"
            />
            <KpiCard
                label="Tasa de cobro"
                :value="kpis?.tasa_cobro != null ? `${kpis.tasa_cobro}%` : null"
                :delta="kpis?.tasa_cobro != null ? (kpis.tasa_cobro >= 80 ? 'Saludable' : 'Bajo objetivo') : null"
                :deltaDirection="kpis?.tasa_cobro != null ? (kpis.tasa_cobro >= 80 ? 'up' : 'down') : 'neutral'"
                accent="green"
                icon="ti-percent"
                :loading="loading"
            />
            <KpiCard
                label="Cartera vencida"
                :value="formatCurrency(kpis?.cartera_vencida?.amount)"
                :delta="kpis?.cartera_vencida?.count ? `${kpis.cartera_vencida.count} facturas vencidas` : null"
                :deltaDirection="(kpis?.cartera_vencida?.count ?? 0) > 0 ? 'down' : 'neutral'"
                accent="orange"
                icon="ti-alert-triangle"
                :loading="loading"
            />
        </div>

        <!-- Gráfica MRR semanal — 3 meses comparados -->
        <div class="wr-panel mt-3" v-if="!loading && weeklyChartSeries.length">
            <div class="wr-section-title mb-2">
                <i class="ti ti-chart-line me-1"></i>
                MRR semanal — comparativo 3 meses
            </div>
            <apexchart type="line" height="160" :options="weeklyChartOptions" :series="weeklyChartSeries" />
        </div>

        <!-- Top deudores -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title"><i class="ti ti-alert-circle me-1"></i> Top deudores</div>
            <template v-if="loading">
                <q-skeleton v-for="i in 5" :key="i" type="text" class="mb-2" :width="`${90 - i * 8}%`" />
            </template>
            <div v-else-if="!kpis?.top_deudores?.length" class="wr-empty">Sin deudores registrados.</div>
            <table v-else class="wr-table">
                <thead>
                    <tr><th>Cliente</th><th class="text-end">Deuda</th><th class="text-end">Facturas</th></tr>
                </thead>
                <tbody>
                    <tr v-for="d in kpis.top_deudores.slice(0, 8)" :key="d.name">
                        <td>{{ d.name }}</td>
                        <td class="text-end wr-text-danger">{{ formatCurrency(d.deuda) }}</td>
                        <td class="text-end">{{ d.facturas }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Cash flow próximo -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title"><i class="ti ti-calendar-dollar me-1"></i> Cash flow próximas 4 semanas</div>
            <template v-if="loading">
                <q-skeleton v-for="i in 4" :key="i" type="rect" height="24px" class="mb-1" />
            </template>
            <div v-else-if="!kpis?.cashflow_proximo?.length" class="wr-empty">Sin vencimientos próximos.</div>
            <div v-else class="wr-cashflow-list">
                <div v-for="row in kpis.cashflow_proximo" :key="row.due_date" class="wr-cashflow-row">
                    <span class="wr-cashflow-date">{{ formatDate(row.due_date) }}</span>
                    <div class="wr-cashflow-bar-wrap">
                        <div class="wr-cashflow-bar" :style="{ width: cashflowWidth(row.monto) + '%' }"></div>
                    </div>
                    <span class="wr-cashflow-amount">{{ formatCurrency(row.monto) }}</span>
                    <span class="wr-cashflow-count">{{ row.facturas }} fact.</span>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <InsightsBlock :insights="insights" :loading="insightsLoading" :source="insightsSource" />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import KpiCard from '../shared/KpiCard.vue';
import InsightsBlock from '../shared/InsightsBlock.vue';
import { useKpis } from '../composables/useKpis.js';
import { useInsights } from '../composables/useInsights.js';
import { deltaStr, deltaDir, formatCurrency } from '../utils.js';

const props = defineProps({
    period: { type: String, required: true },
});

const { kpis, loading, fetchKpis } = useKpis('finanzas');
const { insights, loading: insightsLoading, source: insightsSource, fetchInsights } = useInsights('finanzas');

const maxDeuda = computed(() => {
    if (!kpis.value?.cashflow_proximo?.length) return 1;
    return Math.max(...kpis.value.cashflow_proximo.map(r => parseFloat(r.monto) || 0), 1);
});

function cashflowWidth(monto) {
    return Math.round((parseFloat(monto) / maxDeuda.value) * 100);
}

function formatDate(d) {
    if (!d) return '';
    const date = new Date(d + 'T00:00:00');
    return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

// ── Gráfica MRR semanal ──────────────────────────────────────────────────────
const MONTHS_ES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
function periodLabel(p) {
    const [y, m] = p.split('-');
    return `${MONTHS_ES[parseInt(m) - 1]} ${y}`;
}

const weeklyChartSeries = computed(() => {
    if (!kpis.value?.weekly_series?.series) return [];
    return kpis.value.weekly_series.series.map(s => ({
        name: periodLabel(s.period),
        data: s.data,
    }));
});

const weeklyChartOptions = computed(() => ({
    chart: { background: 'transparent', toolbar: { show: false }, animations: { enabled: false } },
    theme: { mode: 'dark' },
    colors: ['#1D9E75', '#534AB7', '#6b6b85'],
    stroke: { curve: 'smooth', width: [3, 1.5, 1] },
    xaxis: {
        categories: kpis.value?.weekly_series?.labels ?? ['Sem 1','Sem 2','Sem 3','Sem 4'],
        labels: { style: { colors: '#6b6b85', fontSize: '11px' } },
        axisBorder: { show: false },
        axisTicks: { show: false },
    },
    yaxis: {
        labels: {
            style: { colors: '#6b6b85', fontSize: '10px' },
            formatter: v => v >= 1000 ? `$${(v / 1000).toFixed(1)}k` : `$${v}`,
        },
    },
    grid: { borderColor: 'rgba(255,255,255,0.06)', strokeDashArray: 4 },
    tooltip: { theme: 'dark', y: { formatter: v => `$${v.toLocaleString('es-MX')}` } },
    legend: { labels: { colors: '#9999b0' }, fontSize: '11px' },
    dataLabels: { enabled: false },
    markers: { size: 4, strokeWidth: 0 },
}));

async function load() {
    await Promise.all([fetchKpis(props.period), fetchInsights(props.period)]);
}

onMounted(load);
watch(() => props.period, load);
</script>
