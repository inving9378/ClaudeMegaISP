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

        <div class="mt-3">
            <InsightsBlock :insights="insights" :loading="insightsLoading" :source="insightsSource" />
        </div>
    </div>
</template>

<script setup>
import { onMounted, watch } from 'vue';
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

async function load() {
    await Promise.all([fetchKpis(props.period), fetchInsights(props.period)]);
}

onMounted(load);
watch(() => props.period, load);
</script>
