<template>
    <div class="wr-view wr-view-red">

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

        <!-- ── Tickets de red semanales ──────────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title mb-2">
                <i class="ti ti-chart-line me-1"></i>
                Tickets de red por semana — comparativo 3 meses
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
                label="PPPoE configurados"
                :value="kpis?.ppoe_activos"
                :delta="kpis?.olts?.activas != null ? `${kpis.olts.activas}/${kpis.olts.total} OLTs activas` : null"
                delta-direction="neutral"
                accent="blue"
                icon="ti-server"
                :loading="loading"
            />
        </div>

        <!-- ── Estado de ONUs ────────────────────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title mb-2">
                <i class="ti ti-server me-1"></i>
                Estado de ONUs en red
            </div>
            <template v-if="loading">
                <q-skeleton v-for="i in 3" :key="i" type="text" class="mb-2" :width="`${80 - i * 15}%`" />
            </template>
            <div v-else-if="!kpis?.onus" class="wr-empty-muted">Sin datos de OLT disponibles.</div>
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

            <div v-if="!loading && kpis?.tickets_sin_internet != null" class="mt-3">
                <div class="wr-section-title mb-2">
                    <i class="ti ti-alert-triangle me-1"></i>
                    Tickets sin servicio (mes)
                </div>
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

        <!-- ── Uso por OLT ───────────────────────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title mb-2">
                <i class="ti ti-server me-1"></i>
                Uso por OLT — ONUs activas / total
            </div>
            <template v-if="loading">
                <q-skeleton v-for="i in 3" :key="i" type="text" class="mb-2" :width="`${80 - i * 10}%`" />
            </template>
            <div v-else-if="!kpis?.olt_uso?.length" class="wr-empty-muted">Sin datos de puertos PON disponibles.</div>
            <div v-else class="wr-status-bars mt-1">
                <div v-for="olt in kpis.olt_uso" :key="olt.olt_name" class="wr-status-row">
                    <span class="wr-status-label" style="min-width:140px; flex-shrink:0;">{{ olt.olt_name }}</span>
                    <div class="wr-status-bar-wrap">
                        <div class="wr-status-bar" :style="{ width: oltBarWidth(olt) + '%', background: oltBarColor(olt.pct_up) }"></div>
                    </div>
                    <span class="wr-status-count" style="width:100px; text-align:right; flex-shrink:0;">
                        {{ olt.online_onus }}/{{ olt.total_onus }}
                        <span class="wr-text-dim" style="font-size:10px;"> ({{ olt.pct_up }}%)</span>
                    </span>
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
import { transformWeeklySeries } from '../utils.js';

const props = defineProps({
    period: { type: String, required: true },
});

const now  = new Date();
const from = ref(props.period + '-01');
const to   = ref(now.toISOString().slice(0, 10));
const granularidad = ref('semana');

const { kpis, loading, fetchKpis } = useKpis('red');
const { insights, loading: insightsLoading, source: insightsSource, status: insightsStatus, fetchInsights } = useInsights('red');

const serieData   = computed(() => transformWeeklySeries(kpis.value?.weekly_series));
const serieLabels = computed(() => serieData.value.labels);
const serieSeries = computed(() => serieData.value.series);

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

function oltBarWidth(olt) {
    return olt.total_onus > 0 ? Math.round((olt.online_onus / olt.total_onus) * 100) : 0;
}

function oltBarColor(pctUp) {
    if (pctUp >= 90) return 'var(--wr-green)';
    if (pctUp >= 70) return '#f0a500';
    return 'var(--wr-orange)';
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
