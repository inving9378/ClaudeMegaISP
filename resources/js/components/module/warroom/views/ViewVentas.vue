<template>
    <div class="wr-view wr-view-ventas">
        <div class="wr-kpi-grid wr-kpi-grid-3">
            <KpiCard
                label="Clientes nuevos"
                :value="kpis?.clientes_nuevos?.current"
                :previousValue="kpis?.clientes_nuevos?.previous"
                :delta="deltaStr(kpis?.clientes_nuevos?.current, kpis?.clientes_nuevos?.previous)"
                :deltaDirection="deltaDir(kpis?.clientes_nuevos?.current, kpis?.clientes_nuevos?.previous)"
                accent="blue"
                icon="ti-users"
                size="hero"
                :loading="loading"
            />
            <KpiCard
                label="Comisiones pagadas"
                :value="formatCurrency(kpis?.comisiones?.current)"
                :previousValue="formatCurrency(kpis?.comisiones?.previous)"
                :delta="deltaStr(kpis?.comisiones?.current, kpis?.comisiones?.previous)"
                :deltaDirection="deltaDir(kpis?.comisiones?.current, kpis?.comisiones?.previous)"
                accent="purple"
                icon="ti-star"
                size="hero"
                :loading="loading"
            />
            <KpiCard
                label="Embajadores activos"
                :value="kpis?.embajadores_activos"
                accent="green"
                icon="ti-award"
                size="hero"
                :loading="loading"
            />
        </div>

        <div class="wr-kpi-grid wr-kpi-grid-1 mt-3">
            <KpiCard
                label="Referidos captados este mes"
                :value="kpis?.referidos_del_mes"
                accent="blue"
                icon="ti-user-plus"
                :loading="loading"
            />
        </div>

        <!-- Gráfica clientes nuevos semanal — 3 meses comparados -->
        <div class="wr-panel mt-3" v-if="!loading && weeklyChartSeries.length">
            <div class="wr-section-title mb-2">
                <i class="ti ti-chart-line me-1"></i>
                Clientes nuevos por semana — comparativo 3 meses
            </div>
            <apexchart type="line" height="160" :options="weeklyChartOptions" :series="weeklyChartSeries" />
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

const { kpis, loading, fetchKpis } = useKpis('ventas');
const { insights, loading: insightsLoading, source: insightsSource, fetchInsights } = useInsights('ventas');

// ── Gráfica clientes nuevos semanal ──────────────────────────────────────────
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
            formatter: v => `${Math.round(v)}`,
        },
    },
    grid: { borderColor: 'rgba(255,255,255,0.06)', strokeDashArray: 4 },
    tooltip: { theme: 'dark', y: { formatter: v => `${Math.round(v)} clientes` } },
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
