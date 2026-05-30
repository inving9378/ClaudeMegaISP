<template>
    <div class="wr-view wr-view-red">
        <div class="wr-kpi-grid wr-kpi-grid-2">
            <KpiCard
                label="Clientes activos en red"
                :value="kpis?.clientes_activos"
                accent="green"
                icon="ti-network"
                size="hero"
                :loading="loading"
            />
            <KpiCard
                label="Tickets sin internet (mes)"
                :value="kpis?.tickets_sin_internet"
                accent="orange"
                icon="ti-wifi-off"
                size="hero"
                :loading="loading"
            />
        </div>

        <div class="wr-panel mt-3">
            <div class="wr-section-title"><i class="ti ti-server me-1"></i> Estado de infraestructura</div>
            <div class="wr-empty wr-empty-muted mt-2">
                <i class="ti ti-plug-connected me-1"></i>
                Integración con OLTs y MikroTik disponible en próxima versión.
            </div>
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

const props = defineProps({
    period: { type: String, required: true },
});

const { kpis, loading, fetchKpis } = useKpis('red');
const { insights, loading: insightsLoading, source: insightsSource, fetchInsights } = useInsights('red');

async function load() {
    await Promise.all([fetchKpis(props.period), fetchInsights(props.period)]);
}

onMounted(load);
watch(() => props.period, load);
</script>
