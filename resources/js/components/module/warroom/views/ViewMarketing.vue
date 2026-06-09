<template>
    <div class="wr-view wr-view-marketing">

        <!-- ── Controles unificados ──────────────────────────────────────────── -->
        <WarroomViewControls
            v-model:from="from"
            v-model:to="to"
            v-model:granularidad="granularidad"
            :loading="loading"
            @refresh="load"
            @update:from="load"
            @update:to="load"
        />

        <!-- ── Leads captados semanales ──────────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title mb-2">
                <i class="ti ti-chart-line me-1"></i>
                Leads captados por semana — comparativo 3 meses
            </div>
            <warroom-line-series
                :series="serieSeries"
                :labels="serieLabels"
                :loading="loading"
                v-model:granularidad="granularidad"
                :show-controls="false"
            />
        </div>

        <!-- ── KPIs ──────────────────────────────────────────────────────────── -->
        <div class="wr-kpi-grid wr-kpi-grid-2 mt-3">
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

        <!-- ── Canal de mensajes ─────────────────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title mb-2">
                <i class="ti ti-chart-bubble me-1"></i>
                Canal de mensajes — publicaciones este mes
            </div>
            <template v-if="loading">
                <q-skeleton v-for="i in 4" :key="i" type="text" class="mb-2" :width="`${75 - i * 8}%`" />
            </template>
            <div v-else-if="!kpis?.canal_desglose?.length" class="wr-empty-muted mt-2">
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

        <!-- ── Insights ──────────────────────────────────────────────────────── -->
        <div class="mt-3">
            <InsightsBlock :insights="insights" :loading="insightsLoading" :source="insightsSource" :status="insightsStatus" />
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import KpiCard from '../shared/KpiCard.vue';
import InsightsBlock from '../shared/InsightsBlock.vue';
import WarroomViewControls from '../shared/WarroomViewControls.vue';
import WarroomLineSeries from './WarroomLineSeries.vue';
import { useKpis } from '../composables/useKpis.js';
import { useInsights } from '../composables/useInsights.js';
import { deltaStr, deltaDir, transformWeeklySeries } from '../utils.js';

const props = defineProps({
    period: { type: String, required: true },
});

const now  = new Date();
const from = ref(props.period + '-01');
const to   = ref(now.toISOString().slice(0, 10));
const granularidad = ref('semana');

const { kpis, loading, fetchKpis } = useKpis('marketing');
const { insights, loading: insightsLoading, source: insightsSource, status: insightsStatus, fetchInsights } = useInsights('marketing');

const serieData   = computed(() => transformWeeklySeries(kpis.value?.weekly_series));
const serieLabels = computed(() => serieData.value.labels);
const serieSeries = computed(() => serieData.value.series);

const conversionLabel = computed(() => {
    const captados = kpis.value?.leads_captados ?? 0;
    const ganados  = kpis.value?.leads_ganados  ?? 0;
    if (!captados) return null;
    return `${Math.round((ganados / captados) * 100)}% conversión`;
});

const CANAL_ICONS = {
    whatsapp:  'ti-brand-whatsapp',
    facebook:  'ti-brand-facebook',
    instagram: 'ti-brand-instagram',
    email:     'ti-mail',
    sms:       'ti-message',
    voice:     'ti-phone',
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

async function load() {
    const period = from.value.slice(0, 7);
    await Promise.all([fetchKpis(period), fetchInsights(period)]);
}

watch(() => props.period, (val) => {
    from.value = val + '-01';
    load();
});

onMounted(load);
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
