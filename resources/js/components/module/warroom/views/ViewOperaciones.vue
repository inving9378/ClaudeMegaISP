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
                label="Por estado"
                :value="ticketsLabel"
                accent="orange"
                icon="ti-chart-pie"
                :loading="loading"
            />
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
import { deltaStr } from '../utils.js';

const props = defineProps({
    period: { type: String, required: true },
});

const { kpis, loading, fetchKpis } = useKpis('operaciones');
const { insights, loading: insightsLoading, source: insightsSource, fetchInsights } = useInsights('operaciones');

function deltaDir(current, previous, invert = false) {
    if (!previous) return 'neutral';
    const up = current >= previous;
    return invert ? (up ? 'down' : 'up') : (up ? 'up' : 'down');
}

const ticketsLabel = computed(() => {
    if (!kpis.value?.by_status) return null;
    const done = kpis.value.by_status['Done'] ?? 0;
    const total = Object.values(kpis.value.by_status).reduce((a, b) => a + b, 0);
    return `${done}/${total} resueltos`;
});

const maxStatusCount = computed(() => {
    if (!kpis.value?.by_status) return 1;
    return Math.max(...Object.values(kpis.value.by_status), 1);
});

function statusWidth(count) {
    return Math.round((count / maxStatusCount.value) * 100);
}

async function load() {
    await Promise.all([fetchKpis(props.period), fetchInsights(props.period)]);
}

onMounted(load);
watch(() => props.period, load);
</script>
