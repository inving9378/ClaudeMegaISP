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

async function load() {
    await Promise.all([fetchKpis(props.period), fetchInsights(props.period)]);
}

onMounted(load);
watch(() => props.period, load);
</script>
