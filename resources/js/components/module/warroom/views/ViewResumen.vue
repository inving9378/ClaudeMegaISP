<template>
    <div class="wr-view wr-view-resumen">

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

        <!-- ── Serie ingresos semanales ──────────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title mb-2">
                <i class="ti ti-chart-line me-1"></i>
                Ingresos — {{ periodLabel }} vs meses anteriores
            </div>
            <warroom-line-series
                :series="serieSeries"
                :labels="serieLabels"
                :loading="loading"
                v-model:granularidad="granularidad"
                :show-controls="false"
            />
        </div>

        <!-- ── KPIs hero ─────────────────────────────────────────────────────── -->
        <div class="wr-kpi-grid wr-kpi-grid-3 mt-3">
            <KpiCard
                label="Ingresos del mes"
                :value="formatCurrency(kpis?.ingresos?.current)"
                :previousValue="formatCurrency(kpis?.ingresos?.previous)"
                :delta="deltaStr(kpis?.ingresos?.current, kpis?.ingresos?.previous)"
                :deltaDirection="deltaDir(kpis?.ingresos?.current, kpis?.ingresos?.previous)"
                accent="green"
                icon="ti-coin"
                size="hero"
                :loading="loading"
            />
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
                label="Comisiones embajadores"
                :value="formatCurrency(kpis?.comisiones_embajadores?.current)"
                :previousValue="formatCurrency(kpis?.comisiones_embajadores?.previous)"
                :delta="deltaStr(kpis?.comisiones_embajadores?.current, kpis?.comisiones_embajadores?.previous)"
                :deltaDirection="deltaDir(kpis?.comisiones_embajadores?.current, kpis?.comisiones_embajadores?.previous)"
                accent="purple"
                icon="ti-star"
                size="hero"
                :loading="loading"
            />
        </div>

        <!-- ── Segunda fila: por cobrar + tickets ────────────────────────────── -->
        <div class="wr-kpi-grid wr-kpi-grid-2 mt-3">
            <KpiCard
                label="Por cobrar"
                :value="formatCurrency(kpis?.por_cobrar?.amount)"
                :delta="kpis?.por_cobrar?.count ? `${kpis.por_cobrar.count} facturas` : null"
                delta-direction="neutral"
                accent="orange"
                icon="ti-receipt"
                :loading="loading"
            />
            <KpiCard
                label="Tickets abiertos"
                :value="kpis?.tickets_abiertos"
                accent="orange"
                icon="ti-ticket"
                :loading="loading"
            />
        </div>

        <!-- ── Fila inferior: Top performers + Riesgos/Oportunidades ─────────── -->
        <div class="wr-resumen-bottom mt-3">

            <!-- Top performers -->
            <div class="wr-panel">
                <div class="wr-section-title mb-2">
                    <i class="ti ti-trophy me-1"></i>
                    Top captadores — {{ periodLabel }}
                </div>
                <template v-if="loading">
                    <q-skeleton v-for="i in 4" :key="i" class="wr-skel-value mb-1" />
                </template>
                <template v-else-if="kpis?.top_performers?.length">
                    <div
                        v-for="(p, idx) in kpis.top_performers"
                        :key="p.name"
                        class="wr-performer-row"
                    >
                        <span class="wr-performer-rank">#{{ idx + 1 }}</span>
                        <span class="wr-performer-name">{{ p.name }}</span>
                        <div class="wr-performer-bar-wrap">
                            <div class="wr-performer-bar" :style="{ width: p.pct + '%' }"></div>
                        </div>
                        <span class="wr-performer-count">{{ p.nuevos_clientes }}</span>
                    </div>
                </template>
                <div v-else class="wr-empty-muted">Sin datos de captación este mes</div>
            </div>

            <!-- Riesgos y oportunidades -->
            <div class="wr-panel">
                <div class="wr-section-title mb-2">
                    <i class="ti ti-radar me-1"></i>
                    Riesgos y oportunidades
                </div>
                <template v-if="loading">
                    <q-skeleton v-for="i in 3" :key="i" class="wr-skel-value mb-1" />
                </template>
                <template v-else-if="kpis?.riesgos_oportunidades?.length">
                    <div
                        v-for="item in kpis.riesgos_oportunidades"
                        :key="item.mensaje"
                        class="wr-risk-row"
                        :class="`wr-risk-${item.tipo}`"
                    >
                        <i :class="`ti ${item.icono}`"></i>
                        <span>{{ item.mensaje }}</span>
                    </div>
                </template>
                <div v-else class="wr-empty-muted">Sin alertas activas</div>
            </div>

        </div>

        <!-- ── Activity feed ─────────────────────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title mb-2">
                <i class="ti ti-activity me-1"></i>
                Actividad reciente
            </div>
            <template v-if="loading">
                <q-skeleton v-for="i in 5" :key="i" class="wr-skel-delta mb-1" />
            </template>
            <template v-else-if="kpis?.activity_feed?.length">
                <div
                    v-for="(ev, idx) in kpis.activity_feed"
                    :key="idx"
                    class="wr-feed-row"
                >
                    <i :class="`ti ${ev.icon} wr-feed-icon`"></i>
                    <div class="wr-feed-body">
                        <span class="wr-feed-label">{{ ev.label }}</span>
                        <span class="wr-feed-causer">{{ ev.causer }}</span>
                    </div>
                    <span class="wr-feed-ago">{{ ev.ago }}</span>
                </div>
            </template>
            <div v-else class="wr-empty-muted">Sin actividad reciente</div>
        </div>

        <!-- ── Insights ──────────────────────────────────────────────────────── -->
        <div class="mt-3">
            <InsightsBlock
                :insights="insights"
                :loading="insightsLoading"
                :source="insightsSource"
                :status="insightsStatus"
                :can-regenerate="true"
                @regenerate="triggerRegenerate"
            />
        </div>

        <!-- ── Planes de ataque ──────────────────────────────────────────────── -->
        <div class="mt-3">
            <ActionItemsList
                :items="actionItems"
                :loading="false"
                :editable="!!activeMeetingId"
                :meeting-id="activeMeetingId"
                section-key="resumen"
                @item-created="onItemCreated"
            />
        </div>

    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import axios from 'axios';
import KpiCard from '../shared/KpiCard.vue';
import InsightsBlock from '../shared/InsightsBlock.vue';
import ActionItemsList from '../shared/ActionItemsList.vue';
import WarroomViewControls from '../shared/WarroomViewControls.vue';
import WarroomLineSeries from './WarroomLineSeries.vue';
import { useKpis } from '../composables/useKpis.js';
import { useInsights } from '../composables/useInsights.js';
import { deltaStr, deltaDir, formatCurrency, transformWeeklySeries } from '../utils.js';

const props = defineProps({
    period:          { type: String, required: true },
    activeMeetingId: { type: Number, default: null },
});

const now  = new Date();
const from = ref(props.period + '-01');
const to   = ref(now.toISOString().slice(0, 10));
const granularidad = ref('semana');

const { kpis, loading, fetchKpis } = useKpis('resumen');
const { insights, loading: insightsLoading, source: insightsSource, status: insightsStatus, fetchInsights } = useInsights('resumen');
const actionItems = ref([]);

const MONTHS_ES = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
const periodLabel = computed(() => {
    const [y, m] = from.value.slice(0, 7).split('-');
    return `${MONTHS_ES[parseInt(m) - 1]} ${y}`;
});

const serieData = computed(() => transformWeeklySeries(kpis.value?.weekly_series));
const serieLabels = computed(() => serieData.value.labels);
const serieSeries = computed(() => serieData.value.series);

async function load() {
    const period = from.value.slice(0, 7);
    await Promise.all([
        fetchKpis(period),
        fetchInsights(period),
        loadActionItems(),
    ]);
}

async function loadActionItems() {
    if (!props.activeMeetingId) return;
    try {
        const { data } = await axios.get('/warroom/api/action-items', {
            params: { meeting_id: props.activeMeetingId, section_key: 'resumen' },
        });
        actionItems.value = data;
    } catch { /* no-op */ }
}

async function triggerRegenerate() {
    const period = from.value.slice(0, 7);
    await axios.post(`/warroom/api/insights/resumen/${period}/regenerate`).catch(() => {});
    await fetchInsights(period);
}

function onItemCreated(item) {
    actionItems.value.unshift(item);
}

watch(() => props.period, (val) => {
    from.value = val + '-01';
    load();
});

onMounted(load);
</script>

<style>
/* ── Overlay chart panel ──────────────────────────────────────────────────── */
.warroom-container .wr-resumen-bottom {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

/* ── Top performers ───────────────────────────────────────────────────────── */
.warroom-container .wr-performer-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 12px;
}
.warroom-container .wr-performer-row:last-child { border-bottom: none; }
.warroom-container .wr-performer-rank  { color: var(--wr-text-dim); width: 20px; flex-shrink: 0; }
.warroom-container .wr-performer-name  { color: var(--wr-text); min-width: 100px; flex-shrink: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px; }
.warroom-container .wr-performer-bar-wrap { flex: 1; background: rgba(255,255,255,0.06); border-radius: 2px; height: 5px; }
.warroom-container .wr-performer-bar   { height: 5px; background: var(--wr-blue); border-radius: 2px; transition: width 0.4s; }
.warroom-container .wr-performer-count { color: #fff; font-weight: 500; width: 28px; text-align: right; flex-shrink: 0; }

/* ── Riesgos / Oportunidades ──────────────────────────────────────────────── */
.warroom-container .wr-risk-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 6px 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 12px;
    color: var(--wr-text);
}
.warroom-container .wr-risk-row:last-child { border-bottom: none; }
.warroom-container .wr-risk-riesgo i     { color: #e05252; margin-top: 1px; flex-shrink: 0; }
.warroom-container .wr-risk-oportunidad i { color: #1D9E75; margin-top: 1px; flex-shrink: 0; }

/* ── Activity feed ────────────────────────────────────────────────────────── */
.warroom-container .wr-feed-row {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    border-bottom: 1px solid rgba(255,255,255,0.04);
    font-size: 12px;
}
.warroom-container .wr-feed-row:last-child { border-bottom: none; }
.warroom-container .wr-feed-icon  { color: var(--wr-text-muted); font-size: 14px; flex-shrink: 0; }
.warroom-container .wr-feed-body  { flex: 1; min-width: 0; display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.warroom-container .wr-feed-label { color: var(--wr-text); font-weight: 500; }
.warroom-container .wr-feed-causer { color: var(--wr-text-dim); font-size: 11px; }
.warroom-container .wr-feed-ago   { color: var(--wr-text-dim); font-size: 11px; white-space: nowrap; flex-shrink: 0; }
</style>
