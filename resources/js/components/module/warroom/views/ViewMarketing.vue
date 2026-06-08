<template>
    <div class="wr-view wr-view-marketing">
        <div class="wr-kpi-grid wr-kpi-grid-2">
            <KpiCard
                label="Publicaciones enviadas"
                :value="kpis?.mensajes_enviados?.current"
                :previousValue="kpis?.mensajes_enviados?.previous"
                :delta="deltaStr(kpis?.mensajes_enviados?.current, kpis?.mensajes_enviados?.previous)"
                :deltaDirection="deltaDir(kpis?.mensajes_enviados?.current, kpis?.mensajes_enviados?.previous)"
                accent="pink"
                icon="ti-brand-whatsapp"
                size="hero"
                :loading="loading"
            />
            <KpiCard
                label="Campañas activas"
                :value="kpis?.campanias_activas"
                accent="pink"
                icon="ti-speakerphone"
                size="hero"
                :loading="loading"
            />
            <KpiCard
                label="Leads captados"
                :value="kpis?.leads_captados"
                accent="pink"
                icon="ti-user-plus"
                :loading="loading"
            />
            <KpiCard
                label="Leads ganados (won)"
                :value="kpis?.leads_ganados"
                :delta="conversionLabel"
                delta-direction="neutral"
                accent="green"
                icon="ti-trophy"
                :loading="loading"
            />
        </div>

        <!-- Canal de mensajes — desglose real por canal configurado -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title"><i class="ti ti-chart-bubble me-1"></i> Canal de mensajes — publicaciones este mes</div>
            <template v-if="loading">
                <q-skeleton v-for="i in 4" :key="i" type="text" class="mb-2" :width="`${75 - i * 8}%`" />
            </template>
            <div v-else-if="!kpis?.canal_desglose?.length" class="wr-empty wr-empty-muted mt-2">
                <i class="ti ti-plug-connected me-1"></i>
                Sin canales configurados.
            </div>
            <div v-else>
                <div v-for="canal in kpis.canal_desglose" :key="canal.code" class="wr-canal-row">
                    <i :class="`ti ${canalIcon(canal.code)} wr-canal-icon`"></i>
                    <span class="wr-canal-name">{{ canal.name }}</span>
                    <div class="wr-status-bar-wrap">
                        <div class="wr-status-bar" :style="{ width: canalBarWidth(canal.publicaciones) + '%' }"></div>
                    </div>
                    <span class="wr-canal-count">{{ canal.publicaciones }}</span>
                </div>
                <div class="wr-empty-muted mt-2" style="font-size:11px;">
                    <i class="ti ti-info-circle me-1"></i>
                    Métricas de apertura/entrega requieren integración Evolution API analytics (tarea separada).
                </div>
            </div>
        </div>

        <!-- Gráfica leads captados semanal — 3 meses comparados -->
        <div class="wr-panel mt-3" v-if="!loading && weeklyChartSeries.length">
            <div class="wr-section-title mb-2">
                <i class="ti ti-chart-line me-1"></i>
                Leads captados por semana — comparativo 3 meses
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
import { deltaStr, deltaDir } from '../utils.js';

const props = defineProps({
    period: { type: String, required: true },
});

const { kpis, loading, fetchKpis } = useKpis('marketing');
const { insights, loading: insightsLoading, source: insightsSource, fetchInsights } = useInsights('marketing');

const conversionLabel = computed(() => {
    const captados = kpis.value?.leads_captados ?? 0;
    const ganados  = kpis.value?.leads_ganados  ?? 0;
    if (!captados) return null;
    return `${Math.round((ganados / captados) * 100)}% conversión`;
});

// ── Canal de mensajes ────────────────────────────────────────────────────────
const CANAL_ICONS = {
    whatsapp: 'ti-brand-whatsapp',
    facebook: 'ti-brand-facebook',
    instagram: 'ti-brand-instagram',
    email:    'ti-mail',
    sms:      'ti-message',
    voice:    'ti-phone',
};

function canalIcon(code) {
    return CANAL_ICONS[code] ?? 'ti-speakerphone';
}

const maxCanalPubs = computed(() => {
    if (!kpis.value?.canal_desglose?.length) return 1;
    return Math.max(...kpis.value.canal_desglose.map(c => c.publicaciones), 1);
});

function canalBarWidth(pubs) {
    return Math.round((pubs / maxCanalPubs.value) * 100);
}

// ── Gráfica leads captados semanal ───────────────────────────────────────────
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
    tooltip: { theme: 'dark', y: { formatter: v => `${Math.round(v)} leads` } },
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

<style scoped>
.wr-canal-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 12px;
}
.wr-canal-row:last-of-type { border-bottom: none; }
.wr-canal-icon { color: var(--wr-text-muted); font-size: 14px; flex-shrink: 0; }
.wr-canal-name { min-width: 130px; flex-shrink: 0; color: var(--wr-text); }
.wr-canal-count { width: 28px; text-align: right; flex-shrink: 0; color: #fff; font-weight: 500; }
</style>
