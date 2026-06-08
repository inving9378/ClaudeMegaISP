<template>
    <div class="wr-view wr-view-talento">
        <div v-if="!kpis?.available && !loading" class="wr-empty wr-empty-muted">
            <i class="ti ti-users me-1"></i>
            Módulo Talento no disponible en esta instancia.
        </div>
        <template v-else>
            <!-- Info cards -->
            <div class="wr-kpi-grid wr-kpi-grid-4">
                <KpiCard label="Colaboradores activos"  :value="kpis?.active_colaboradores" accent="blue"   icon="ti-users"   size="hero" :loading="loading" />
                <KpiCard label="Asistencia hoy"         :value="kpis?.checked_in_today"     accent="green"  icon="ti-clock"   size="hero" :loading="loading" />
                <KpiCard label="Órdenes hoy"            :value="kpis?.orders_today"         accent="yellow" icon="ti-tools"   size="hero" :loading="loading" />
                <KpiCard label="OTs validadas hoy"      :value="kpis?.validated_today"      accent="teal"   icon="ti-check"   size="hero" :loading="loading" />
            </div>

            <!-- Alertas -->
            <div v-if="totalAlerts > 0" class="wr-panel mt-3 border-danger-subtle">
                <div class="wr-section-title text-danger"><i class="ti ti-alert-triangle me-1"></i>Alertas activas</div>
                <div class="d-flex gap-3 mt-2 flex-wrap">
                    <div v-if="kpis?.alerts?.credentials" class="badge bg-danger-subtle text-danger fs-6 py-2 px-3">
                        <i class="ti ti-id me-1"></i>{{ kpis.alerts.credentials }} credencial(es) vencida(s)/por vencer
                    </div>
                    <div v-if="kpis?.alerts?.desvios" class="badge bg-warning-subtle text-warning fs-6 py-2 px-3">
                        <i class="ti ti-route-x me-1"></i>{{ kpis.alerts.desvios }} desvío(s) sin notificar
                    </div>
                </div>
            </div>
            <div v-else-if="kpis && !loading" class="wr-panel mt-3">
                <div class="wr-section-title text-success"><i class="ti ti-circle-check me-1"></i>Sin alertas activas</div>
            </div>

            <!-- Gráfica OTs validadas semanal — 3 meses comparados -->
            <div class="wr-panel mt-3" v-if="!loading && weeklyChartSeries.length">
                <div class="wr-section-title mb-2">
                    <i class="ti ti-chart-line me-1"></i>
                    OTs validadas por semana — comparativo 3 meses
                </div>
                <apexchart type="line" height="160" :options="weeklyChartOptions" :series="weeklyChartSeries" />
            </div>

            <!-- Top performers -->
            <div class="wr-panel mt-3" v-if="kpis?.top_performers?.length">
                <div class="wr-section-title"><i class="ti ti-trophy me-1"></i>Top performers del mes</div>
                <table class="table table-sm table-hover mt-2 mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Colaborador</th><th>Unidades validadas</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, i) in kpis.top_performers" :key="i">
                            <td class="fw-bold text-warning">{{ i+1 }}</td>
                            <td class="small">{{ p.name }}</td>
                            <td class="small fw-semibold">{{ p.total_units }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <a href="/talento/dashboard" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-external-link me-1"></i>Abrir dashboard Talento
                </a>
                <a href="/talento/escalafon" target="_blank" class="btn btn-sm btn-outline-secondary ms-2">
                    <i class="ti ti-trophy me-1"></i>Ver escalafón
                </a>
            </div>

            <div class="mt-3">
                <InsightsBlock :insights="insights" :loading="insightsLoading" :source="insightsSource" :status="insightsStatus" />
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, watch, computed } from 'vue';
import KpiCard from '../shared/KpiCard.vue';
import InsightsBlock from '../shared/InsightsBlock.vue';
import { useKpis } from '../composables/useKpis.js';
import { useInsights } from '../composables/useInsights.js';

const props = defineProps({
    period: { type: String, required: true },
});

const { kpis, loading, fetchKpis } = useKpis('talento');
const { insights, loading: insightsLoading, source: insightsSource, status: insightsStatus, fetchInsights } = useInsights('talento');

const totalAlerts = computed(() => {
    if (!kpis.value?.alerts) return 0;
    return (kpis.value.alerts.credentials ?? 0) + (kpis.value.alerts.desvios ?? 0);
});

// ── Gráfica OTs validadas semanal ────────────────────────────────────────────
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
    tooltip: { theme: 'dark', y: { formatter: v => `${Math.round(v)} OTs` } },
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
