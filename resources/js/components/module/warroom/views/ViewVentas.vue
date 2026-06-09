<template>
    <div class="wr-view wr-view-ventas">

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

        <!-- ── Clientes nuevos semanales ─────────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title mb-2">
                <i class="ti ti-chart-line me-1"></i>
                Clientes nuevos por semana — comparativo 3 meses
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
        <div class="wr-kpi-grid wr-kpi-grid-3 mt-3">
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
import { deltaStr, deltaDir, formatCurrency, transformWeeklySeries } from '../utils.js';

const props = defineProps({
    period: { type: String, required: true },
});

const now  = new Date();
const from = ref(props.period + '-01');
const to   = ref(now.toISOString().slice(0, 10));
const granularidad = ref('semana');

const { kpis, loading, fetchKpis } = useKpis('ventas');
const { insights, loading: insightsLoading, source: insightsSource, status: insightsStatus, fetchInsights } = useInsights('ventas');

const serieData   = computed(() => transformWeeklySeries(kpis.value?.weekly_series));
const serieLabels = computed(() => serieData.value.labels);
const serieSeries = computed(() => serieData.value.series);

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
