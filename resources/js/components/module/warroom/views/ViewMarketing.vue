<template>
    <div class="wr-view wr-view-marketing">
        <div class="wr-kpi-grid wr-kpi-grid-2">
            <KpiCard
                label="Mensajes WhatsApp enviados"
                :value="kpis?.mensajes_enviados"
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
        </div>

        <div class="wr-panel mt-3">
            <div class="wr-section-title"><i class="ti ti-chart-bubble me-1"></i> Canal de mensajes</div>
            <div class="wr-empty wr-empty-muted mt-2">
                <i class="ti ti-plug-connected me-1"></i>
                Métricas de apertura y entrega disponibles en próxima integración con Evolution API analytics.
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

const { kpis, loading, fetchKpis } = useKpis('marketing');
const { insights, loading: insightsLoading, source: insightsSource, fetchInsights } = useInsights('marketing');

async function load() {
    await Promise.all([fetchKpis(props.period), fetchInsights(props.period)]);
}

onMounted(load);
watch(() => props.period, load);
</script>
