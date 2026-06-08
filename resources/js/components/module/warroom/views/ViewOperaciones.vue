<template>
    <div class="wr-view wr-view-operaciones">
        <div class="wr-kpi-grid wr-kpi-grid-2">
            <KpiCard
                label="Tickets del mes"
                :value="kpis?.tickets?.current"
                :previousValue="kpis?.tickets?.previous"
                :delta="deltaStr(kpis?.tickets?.current, kpis?.tickets?.previous)"
                :deltaDirection="deltaDir(kpis?.tickets?.current, kpis?.tickets?.previous, true)"
                accent="orange"
                icon="ti-tools"
                size="hero"
                :loading="loading"
            />
            <KpiCard
                label="Tiempo prom. resolución"
                :value="kpis?.tiempo_promedio?.current != null ? formatHours(kpis.tiempo_promedio.current) : null"
                :previousValue="kpis?.tiempo_promedio?.previous != null ? formatHours(kpis.tiempo_promedio.previous) : null"
                :delta="deltaStr(kpis?.tiempo_promedio?.current, kpis?.tiempo_promedio?.previous)"
                :deltaDirection="deltaDir(kpis?.tiempo_promedio?.current, kpis?.tiempo_promedio?.previous, true)"
                accent="blue"
                icon="ti-clock"
                :loading="loading"
            />
            <KpiCard
                label="Pendientes (ToDo + En progreso)"
                :value="kpis?.tickets_pendientes?.current"
                :previousValue="kpis?.tickets_pendientes?.previous"
                :delta="deltaStr(kpis?.tickets_pendientes?.current, kpis?.tickets_pendientes?.previous)"
                :deltaDirection="deltaDir(kpis?.tickets_pendientes?.current, kpis?.tickets_pendientes?.previous, true)"
                accent="orange"
                icon="ti-clock-pause"
                :loading="loading"
            />
            <KpiCard
                label="Cerrados este mes"
                :value="kpis?.tickets_cerrados?.current"
                :previousValue="kpis?.tickets_cerrados?.previous"
                :delta="deltaStr(kpis?.tickets_cerrados?.current, kpis?.tickets_cerrados?.previous)"
                :deltaDirection="deltaDir(kpis?.tickets_cerrados?.current, kpis?.tickets_cerrados?.previous)"
                accent="green"
                icon="ti-circle-check"
                :loading="loading"
            />
        </div>

        <!-- Gráfica tickets cerrados semanales — 3 meses comparados -->
        <div class="wr-panel mt-3" v-if="!loading && weeklyChartSeries.length">
            <div class="wr-section-title mb-2">
                <i class="ti ti-chart-line me-1"></i>
                Tickets cerrados por semana — comparativo 3 meses
            </div>
            <apexchart type="line" height="160" :options="weeklyChartOptions" :series="weeklyChartSeries" />
        </div>

        <!-- Por estado -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title"><i class="ti ti-chart-bar me-1"></i> Tickets por estado</div>
            <template v-if="loading">
                <q-skeleton v-for="i in 4" :key="i" type="text" class="mb-2" :width="`${80 - i * 10}%`" />
            </template>
            <div v-else-if="!kpis?.by_status || !Object.keys(kpis.by_status).length" class="wr-empty">Sin datos.</div>
            <div v-else class="wr-status-bars">
                <div v-for="(count, status) in kpis.by_status" :key="status" class="wr-status-row">
                    <span class="wr-status-label">{{ status }}</span>
                    <div class="wr-status-bar-wrap">
                        <div class="wr-status-bar" :style="{ width: statusWidth(count) + '%' }"></div>
                    </div>
                    <span class="wr-status-count">{{ count }}</span>
                </div>
            </div>
        </div>

        <!-- Por prioridad -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title"><i class="ti ti-flag me-1"></i> Tickets por prioridad</div>
            <template v-if="loading">
                <q-skeleton v-for="i in 3" :key="i" type="text" class="mb-2" :width="`${70 - i * 10}%`" />
            </template>
            <div v-else-if="!kpis?.by_priority || !Object.keys(kpis.by_priority).length" class="wr-empty">Sin datos.</div>
            <div v-else class="wr-priority-chips">
                <div v-for="(count, prio) in kpis.by_priority" :key="prio" class="wr-priority-chip">
                    <span class="wr-prio-label">{{ prio || 'Sin prioridad' }}</span>
                    <span class="wr-prio-count">{{ count }}</span>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <InsightsBlock :insights="insights" :loading="insightsLoading" :source="insightsSource" :status="insightsStatus" />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import KpiCard from '../shared/KpiCard.vue';
import InsightsBlock from '../shared/InsightsBlock.vue';
import { useKpis } from '../composables/useKpis.js';
import { useInsights } from '../composables/useInsights.js';
import { deltaStr } from '../utils.js';

const props = defineProps({
    period: { type: String, required: true },
});

const { kpis, loading, fetchKpis } = useKpis('operaciones');
const { insights, loading: insightsLoading, source: insightsSource, status: insightsStatus, fetchInsights } = useInsights('operaciones');

function deltaDir(current, previous, invert = false) {
    if (!previous) return 'neutral';
    const up = current >= previous;
    return invert ? (up ? 'down' : 'up') : (up ? 'up' : 'down');
}

function formatHours(h) {
    if (h == null || h === 0) return '—';
    if (h >= 48) return `${Math.round(h / 24)}d`;
    return `${Math.round(h)}h`;
}

const maxStatusCount = computed(() => {
    if (!kpis.value?.by_status) return 1;
    return Math.max(...Object.values(kpis.value.by_status), 1);
});

function statusWidth(count) {
    return Math.round((count / maxStatusCount.value) * 100);
}

// ── Gráfica tickets cerrados semanal ─────────────────────────────────────────
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
    tooltip: { theme: 'dark', y: { formatter: v => `${Math.round(v)} tickets` } },
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
