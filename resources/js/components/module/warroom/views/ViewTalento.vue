<template>
    <div class="wr-view wr-view-talento">

        <div v-if="!kpis?.available && !loading" class="wr-empty-muted">
            <i class="ti ti-users me-1"></i>
            Módulo Talento no disponible en esta instancia.
        </div>

        <template v-else>

            <!-- ── Controles unificados ──────────────────────────────────────── -->
            <WarroomViewControls
                v-model:from="from"
                v-model:to="to"
                v-model:granularidad="granularidad"
                :loading="loading"
                @refresh="load"
                @update:from="load"
                @update:to="load"
            />

            <!-- ── OTs validadas semanales ───────────────────────────────────── -->
            <div class="wr-panel mt-3">
                <div class="wr-section-title mb-2">
                    <i class="ti ti-chart-line me-1"></i>
                    OTs validadas por semana — comparativo 3 meses
                </div>
                <warroom-line-series
                    :series="serieSeries"
                    :labels="serieLabels"
                    :loading="loading"
                    v-model:granularidad="granularidad"
                    :show-controls="false"
                />
            </div>

            <!-- ── KPIs ──────────────────────────────────────────────────────── -->
            <div class="wr-kpi-grid wr-kpi-grid-4 mt-3">
                <KpiCard label="Colaboradores activos"  :value="kpis?.active_colaboradores" accent="blue"   icon="ti-users"  size="hero" :loading="loading" />
                <KpiCard label="Asistencia hoy"         :value="kpis?.checked_in_today"     accent="green"  icon="ti-clock"  size="hero" :loading="loading" />
                <KpiCard label="Órdenes hoy"            :value="kpis?.orders_today"         accent="yellow" icon="ti-tools"  size="hero" :loading="loading" />
                <KpiCard label="OTs validadas hoy"      :value="kpis?.validated_today"      accent="teal"   icon="ti-check"  size="hero" :loading="loading" />
            </div>

            <!-- ── Alertas ───────────────────────────────────────────────────── -->
            <div class="wr-panel mt-3">
                <template v-if="totalAlerts > 0">
                    <div class="wr-section-title mb-2" style="color: #e05252;">
                        <i class="ti ti-alert-triangle me-1"></i>
                        Alertas activas
                    </div>
                    <div class="wr-talento-alerts">
                        <div v-if="kpis?.alerts?.credentials" class="wr-talento-badge wr-talento-badge-danger">
                            <i class="ti ti-id me-1"></i>
                            {{ kpis.alerts.credentials }} credencial(es) vencida(s)/por vencer
                        </div>
                        <div v-if="kpis?.alerts?.desvios" class="wr-talento-badge wr-talento-badge-warning">
                            <i class="ti ti-route-x me-1"></i>
                            {{ kpis.alerts.desvios }} desvío(s) sin notificar
                        </div>
                    </div>
                </template>
                <template v-else-if="kpis && !loading">
                    <div class="wr-section-title" style="color: #1D9E75;">
                        <i class="ti ti-circle-check me-1"></i>
                        Sin alertas activas
                    </div>
                </template>
            </div>

            <!-- ── Top performers ────────────────────────────────────────────── -->
            <div class="wr-panel mt-3" v-if="kpis?.top_performers?.length">
                <div class="wr-section-title mb-2">
                    <i class="ti ti-trophy me-1"></i>
                    Top performers del mes
                </div>
                <table class="wr-table">
                    <thead>
                        <tr><th>#</th><th>Colaborador</th><th>Unidades validadas</th></tr>
                    </thead>
                    <tbody>
                        <tr v-for="(p, i) in kpis.top_performers" :key="i">
                            <td style="color: #f0a500; font-weight: 700;">{{ i + 1 }}</td>
                            <td>{{ p.name }}</td>
                            <td style="font-weight: 600;">{{ p.total_units }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ── Links externos ────────────────────────────────────────────── -->
            <div class="mt-3 d-flex gap-2">
                <a href="/talento/dashboard" target="_blank" class="wr-ext-link">
                    <i class="ti ti-external-link me-1"></i>Dashboard Talento
                </a>
                <a href="/talento/escalafon" target="_blank" class="wr-ext-link">
                    <i class="ti ti-trophy me-1"></i>Ver escalafón
                </a>
            </div>

            <!-- ── Insights ──────────────────────────────────────────────────── -->
            <div class="mt-3">
                <InsightsBlock :insights="insights" :loading="insightsLoading" :source="insightsSource" :status="insightsStatus" />
            </div>

        </template>
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

const { kpis, loading, fetchKpis } = useKpis('talento');
const { insights, loading: insightsLoading, source: insightsSource, status: insightsStatus, fetchInsights } = useInsights('talento');

const serieData   = computed(() => transformWeeklySeries(kpis.value?.weekly_series));
const serieLabels = computed(() => serieData.value.labels);
const serieSeries = computed(() => serieData.value.series);

const totalAlerts = computed(() => {
    if (!kpis.value?.alerts) return 0;
    return (kpis.value.alerts.credentials ?? 0) + (kpis.value.alerts.desvios ?? 0);
});

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
.wr-talento-alerts {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.wr-talento-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 6px;
    font-size: 12px;
    padding: 6px 12px;
}

.wr-talento-badge-danger {
    background: rgba(224, 82, 82, 0.12);
    border: 1px solid rgba(224, 82, 82, 0.3);
    color: #e88;
}

.wr-talento-badge-warning {
    background: rgba(240, 165, 0, 0.12);
    border: 1px solid rgba(240, 165, 0, 0.3);
    color: #f0c060;
}

.wr-ext-link {
    display: inline-flex;
    align-items: center;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 6px;
    color: #9999b0;
    font-size: 12px;
    padding: 5px 12px;
    text-decoration: none;
    transition: all 0.15s;
}

.wr-ext-link:hover {
    background: rgba(255,255,255,0.09);
    color: #e8e8f0;
}
</style>
