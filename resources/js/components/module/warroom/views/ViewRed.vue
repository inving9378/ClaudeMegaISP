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
                label="ONUs en línea"
                :value="kpis?.onus?.online != null ? `${kpis.onus.online} / ${kpis.onus.total}` : null"
                :delta="kpis?.onus?.pct_up != null ? `${kpis.onus.pct_up}% uptime` : null"
                :deltaDirection="onusDeltaDir"
                accent="green"
                icon="ti-wifi"
                size="hero"
                :loading="loading"
            />
            <KpiCard
                label="ONUs caídas"
                :value="kpis?.onus?.offline"
                :delta="kpis?.onus?.offline > 0 ? 'Requieren revisión' : 'Sin incidencias'"
                :deltaDirection="(kpis?.onus?.offline ?? 0) > 0 ? 'down' : 'neutral'"
                accent="orange"
                icon="ti-wifi-off"
                :loading="loading"
            />
            <KpiCard
                label="OLTs activas"
                :value="kpis?.olts?.activas != null ? `${kpis.olts.activas} / ${kpis.olts.total}` : null"
                accent="blue"
                icon="ti-server"
                :loading="loading"
            />
        </div>

        <!-- Desglose ONUs por estado -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title"><i class="ti ti-server me-1"></i> Estado de ONUs en red</div>
            <template v-if="loading">
                <q-skeleton v-for="i in 3" :key="i" type="text" class="mb-2" :width="`${80 - i * 15}%`" />
            </template>
            <div v-else-if="!kpis?.onus" class="wr-empty">Sin datos de OLT disponibles.</div>
            <div v-else class="wr-status-bars">
                <div class="wr-status-row">
                    <span class="wr-status-label" style="color: var(--wr-green)">Online</span>
                    <div class="wr-status-bar-wrap">
                        <div class="wr-status-bar" style="background: var(--wr-green)" :style="{ width: onuBarWidth(kpis.onus.online) + '%' }"></div>
                    </div>
                    <span class="wr-status-count">{{ kpis.onus.online }}</span>
                </div>
                <div class="wr-status-row">
                    <span class="wr-status-label" style="color: var(--wr-orange)">Offline / Falla</span>
                    <div class="wr-status-bar-wrap">
                        <div class="wr-status-bar" style="background: var(--wr-orange)" :style="{ width: onuBarWidth(kpis.onus.offline) + '%' }"></div>
                    </div>
                    <span class="wr-status-count">{{ kpis.onus.offline }}</span>
                </div>
            </div>

            <!-- Tickets sin internet -->
            <div v-if="!loading && kpis?.tickets_sin_internet != null" class="mt-3">
                <div class="wr-section-title mb-2"><i class="ti ti-alert-triangle me-1"></i> Tickets sin servicio (mes)</div>
                <div class="wr-kpi-grid wr-kpi-grid-1">
                    <KpiCard
                        label="Tickets sin internet reportados"
                        :value="kpis.tickets_sin_internet"
                        accent="orange"
                        icon="ti-plug-x"
                        :loading="loading"
                    />
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

const props = defineProps({
    period: { type: String, required: true },
});

const { kpis, loading, fetchKpis } = useKpis('red');
const { insights, loading: insightsLoading, source: insightsSource, fetchInsights } = useInsights('red');

const onusDeltaDir = computed(() => {
    const pct = kpis.value?.onus?.pct_up ?? 0;
    if (pct >= 95) return 'up';
    if (pct >= 85) return 'neutral';
    return 'down';
});

function onuBarWidth(count) {
    const total = kpis.value?.onus?.total || 1;
    return Math.round((count / total) * 100);
}

async function load() {
    await Promise.all([fetchKpis(props.period), fetchInsights(props.period)]);
}

onMounted(load);
watch(() => props.period, load);
</script>
