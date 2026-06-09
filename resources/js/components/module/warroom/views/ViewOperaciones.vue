<template>
    <div class="wr-view wr-view-operaciones">

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

        <!-- ── Tickets cerrados semanales ────────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title mb-2">
                <i class="ti ti-chart-line me-1"></i>
                Tickets cerrados por semana — comparativo 3 meses
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

        <!-- ── Por estado ────────────────────────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title mb-2">
                <i class="ti ti-chart-bar me-1"></i>
                Tickets por estado
            </div>
            <template v-if="loading">
                <q-skeleton v-for="i in 4" :key="i" type="text" class="mb-2" :width="`${80 - i * 10}%`" />
            </template>
            <div v-else-if="!kpis?.by_status || !Object.keys(kpis.by_status).length" class="wr-empty-muted">Sin datos.</div>
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

        <!-- ── Por prioridad ─────────────────────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title mb-2">
                <i class="ti ti-flag me-1"></i>
                Tickets por prioridad
            </div>
            <template v-if="loading">
                <q-skeleton v-for="i in 3" :key="i" type="text" class="mb-2" :width="`${70 - i * 10}%`" />
            </template>
            <div v-else-if="!kpis?.by_priority || !Object.keys(kpis.by_priority).length" class="wr-empty-muted">Sin datos.</div>
            <div v-else class="wr-priority-chips">
                <div v-for="(count, prio) in kpis.by_priority" :key="prio" class="wr-priority-chip">
                    <span class="wr-prio-label">{{ prio || 'Sin prioridad' }}</span>
                    <span class="wr-prio-count">{{ count }}</span>
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
import { deltaStr, transformWeeklySeries } from '../utils.js';

const props = defineProps({
    period: { type: String, required: true },
});

const now  = new Date();
const from = ref(props.period + '-01');
const to   = ref(now.toISOString().slice(0, 10));
const granularidad = ref('semana');

const { kpis, loading, fetchKpis } = useKpis('operaciones');
const { insights, loading: insightsLoading, source: insightsSource, status: insightsStatus, fetchInsights } = useInsights('operaciones');

const serieData   = computed(() => transformWeeklySeries(kpis.value?.weekly_series));
const serieLabels = computed(() => serieData.value.labels);
const serieSeries = computed(() => serieData.value.series);

const maxStatusCount = computed(() => {
    if (!kpis.value?.by_status) return 1;
    return Math.max(...Object.values(kpis.value.by_status), 1);
});

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

function statusWidth(count) {
    return Math.round((count / maxStatusCount.value) * 100);
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
