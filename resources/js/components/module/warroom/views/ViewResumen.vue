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

        <!-- ── Ingresos diarios — 3 meses ───────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title mb-2">
                <i class="ti ti-chart-line me-1"></i>
                Ingresos por día — últimos 3 meses
            </div>

            <warroom-line-series
                ref="lineSeriesRef"
                :series="dailySeries"
                :labels="daysLabels"
                :loading="loading"
                :show-controls="false"
                y-label="MXN"
            />

            <!-- Tarjetas resumen por mes -->
            <div class="wr-daily-cards mt-3" v-if="!loading && kpis?.daily_series?.length">
                <div
                    v-for="(s, i) in kpis.daily_series"
                    :key="i"
                    class="wr-daily-card"
                    :style="{ borderLeftColor: DAILY_PALETTE[i] }"
                >
                    <div class="wr-daily-card-label">{{ s.nombre }}</div>
                    <div class="wr-daily-card-total" :style="{ color: DAILY_PALETTE[i] }">
                        {{ formatCurrency(s.total) }}
                    </div>
                    <div class="wr-daily-card-avg">
                        Prom. {{ formatCurrency(s.promedio_diario) }}/día
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Comparativa 3 meses ──────────────────────────────────────────── -->
        <div class="wr-panel mt-3">
            <!-- Toggles de meses -->
            <div class="wr-compare-header mb-3">
                <span class="wr-section-title">
                    <i class="ti ti-columns-3 me-1"></i>Comparativa mensual
                </span>
                <div class="wr-compare-toggles">
                    <label
                        v-for="col in compareColumns"
                        :key="col.key"
                        class="wr-compare-toggle"
                    >
                        <input type="checkbox" v-model="mesesActivos" :value="col.key" />
                        <span class="wr-compare-dot" :style="{ color: col.color }">●</span>
                        {{ col.label }}
                    </label>
                </div>
            </div>

            <!-- Columnas -->
            <div class="wr-compare-cols">
                <div
                    v-for="col in compareColumns.filter(c => mesesActivos.includes(c.key))"
                    :key="col.key"
                    class="wr-compare-col"
                >
                    <div class="wr-compare-col-title" :style="{ color: col.color }">● {{ col.label }}</div>

                    <template v-if="col.loading">
                        <q-skeleton v-for="i in 5" :key="i" class="wr-skel-value mb-2" />
                    </template>
                    <template v-else-if="col.kpis">
                        <!-- Ingresos — conectado al daily chart -->
                        <div class="wr-cmp-card"
                            :class="{ 'wr-cmp-card-faded': !metricasVisibles[`${col.key}-ingresos`] }"
                            :style="{ borderLeftColor: col.color }">
                            <div class="wr-cmp-label-row">
                                <span class="wr-cmp-label">Ingresos</span>
                                <input type="checkbox"
                                    :checked="metricasVisibles[`${col.key}-ingresos`]"
                                    @change="toggleMetrica(`${col.key}-ingresos`)"
                                    class="wr-cmp-check"
                                    title="Mostrar/ocultar en gráfica" />
                            </div>
                            <div class="wr-cmp-value" :style="{ color: col.color }">
                                {{ formatCurrency(col.kpis.ingresos?.current) }}
                            </div>
                            <div class="wr-cmp-delta" :class="`wr-delta-${deltaDir(col.kpis.ingresos?.current, col.kpis.ingresos?.previous)}`">
                                {{ deltaStr(col.kpis.ingresos?.current, col.kpis.ingresos?.previous) }} vs {{ col.vsLabel }}
                            </div>
                        </div>
                        <!-- Clientes -->
                        <div class="wr-cmp-card"
                            :class="{ 'wr-cmp-card-faded': !metricasVisibles[`${col.key}-clientes`] }"
                            style="border-left-color: #639922">
                            <div class="wr-cmp-label-row">
                                <span class="wr-cmp-label">Clientes nuevos</span>
                                <input type="checkbox"
                                    :checked="metricasVisibles[`${col.key}-clientes`]"
                                    @change="toggleMetrica(`${col.key}-clientes`)"
                                    class="wr-cmp-check" />
                            </div>
                            <div class="wr-cmp-value" style="color: #639922">
                                {{ col.kpis.clientes_nuevos?.current ?? 0 }}
                            </div>
                            <div class="wr-cmp-delta" :class="`wr-delta-${deltaDir(col.kpis.clientes_nuevos?.current, col.kpis.clientes_nuevos?.previous)}`">
                                {{ deltaStr(col.kpis.clientes_nuevos?.current, col.kpis.clientes_nuevos?.previous) }} vs {{ col.vsLabel }}
                            </div>
                        </div>
                        <!-- Comisiones -->
                        <div class="wr-cmp-card"
                            :class="{ 'wr-cmp-card-faded': !metricasVisibles[`${col.key}-comisiones`] }"
                            style="border-left-color: #A32D2D">
                            <div class="wr-cmp-label-row">
                                <span class="wr-cmp-label">Comisiones embajadores</span>
                                <input type="checkbox"
                                    :checked="metricasVisibles[`${col.key}-comisiones`]"
                                    @change="toggleMetrica(`${col.key}-comisiones`)"
                                    class="wr-cmp-check" />
                            </div>
                            <div class="wr-cmp-value" style="color: #A32D2D">
                                {{ formatCurrency(col.kpis.comisiones_embajadores?.current) }}
                            </div>
                            <div class="wr-cmp-delta" :class="`wr-delta-${deltaDir(col.kpis.comisiones_embajadores?.current, col.kpis.comisiones_embajadores?.previous)}`">
                                {{ deltaStr(col.kpis.comisiones_embajadores?.current, col.kpis.comisiones_embajadores?.previous) }} vs {{ col.vsLabel }}
                            </div>
                        </div>
                        <!-- Por cobrar -->
                        <div class="wr-cmp-card"
                            :class="{ 'wr-cmp-card-faded': !metricasVisibles[`${col.key}-porcobrar`] }"
                            style="border-left-color: #BA7517">
                            <div class="wr-cmp-label-row">
                                <span class="wr-cmp-label">Por cobrar</span>
                                <input type="checkbox"
                                    :checked="metricasVisibles[`${col.key}-porcobrar`]"
                                    @change="toggleMetrica(`${col.key}-porcobrar`)"
                                    class="wr-cmp-check" />
                            </div>
                            <div class="wr-cmp-value" style="color: #BA7517">
                                {{ formatCurrency(col.kpis.por_cobrar?.amount) }}
                            </div>
                            <div class="wr-cmp-delta wr-delta-neutral">
                                {{ col.kpis.por_cobrar?.count ?? 0 }} facturas
                            </div>
                        </div>
                        <!-- Tickets -->
                        <div class="wr-cmp-card"
                            :class="{ 'wr-cmp-card-faded': !metricasVisibles[`${col.key}-tickets`] }"
                            style="border-left-color: #3C3489">
                            <div class="wr-cmp-label-row">
                                <span class="wr-cmp-label">Tickets abiertos</span>
                                <input type="checkbox"
                                    :checked="metricasVisibles[`${col.key}-tickets`]"
                                    @change="toggleMetrica(`${col.key}-tickets`)"
                                    class="wr-cmp-check" />
                            </div>
                            <div class="wr-cmp-value" style="color: #3C3489">
                                {{ col.kpis.tickets_abiertos ?? 0 }}
                            </div>
                        </div>
                    </template>
                    <div v-else class="wr-empty-muted">Sin datos</div>
                </div>

                <div v-if="!compareColumns.some(c => mesesActivos.includes(c.key))" class="wr-empty-muted">
                    Selecciona al menos un mes para comparar.
                </div>
            </div>
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
import { deltaStr, deltaDir, formatCurrency } from '../utils.js';

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

const MONTHS_ES      = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
const MONTHS_ES_FULL = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const periodLabel = computed(() => {
    const [y, m] = from.value.slice(0, 7).split('-');
    return `${MONTHS_ES[parseInt(m) - 1]} ${y}`;
});

// ── Comparativa 3 meses ───────────────────────────────────────────────────
const prevPeriod1 = computed(() => {
    const [y, m] = from.value.slice(0, 7).split('-').map(Number);
    const d = new Date(y, m - 2, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
});
const prevPeriod2 = computed(() => {
    const [y, m] = from.value.slice(0, 7).split('-').map(Number);
    const d = new Date(y, m - 3, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
});

const { kpis: kpisM1, loading: loadingM1, fetchKpis: fetchKpisM1 } = useKpis('resumen');
const { kpis: kpisM2, loading: loadingM2, fetchKpis: fetchKpisM2 } = useKpis('resumen');

const mesesActivos = ref(['m0', 'm1', 'm2']);

const COMPARE_COLORS = ['#534AB7', '#1D9E75', '#854F0B'];

// ── Visibilidad por métrica (checkboxes en tarjetas) ──────────────────────
const METRIC_IDS = ['ingresos', 'clientes', 'comisiones', 'porcobrar', 'tickets'];

const metricasVisibles = ref(
    Object.fromEntries(
        ['m0', 'm1', 'm2'].flatMap(c =>
            METRIC_IDS.map(m => [`${c}-${m}`, true])
        )
    )
);

const lineSeriesRef = ref(null);

function toggleMetrica(key) {
    metricasVisibles.value[key] = !metricasVisibles.value[key];
    if (key.endsWith('-ingresos')) {
        const colKey = key.split('-')[0];
        const idx = ['m0', 'm1', 'm2'].indexOf(colKey);
        if (idx >= 0 && lineSeriesRef.value) {
            const inst = lineSeriesRef.value;
            const visible = metricasVisibles.value[key];
            if (inst.chart) {
                const meta = inst.chart.getDatasetMeta(idx);
                if (meta) {
                    meta.hidden = !visible;
                    inst.visibleSet = { ...inst.visibleSet, [idx]: visible };
                    inst.chart.update('none');
                }
            }
        }
    }
}

const compareColumns = computed(() => {
    const periods  = [from.value.slice(0, 7), prevPeriod1.value, prevPeriod2.value];
    const kpisArr  = [kpis.value, kpisM1.value, kpisM2.value];
    const loadings = [loading.value, loadingM1.value, loadingM2.value];
    return periods.map((p, i) => {
        const [y, m] = p.split('-').map(Number);
        const vsM = i < 2 ? periods[i + 1].split('-').map(Number)[1] : null;
        return {
            key:     `m${i}`,
            label:   `${MONTHS_ES_FULL[m - 1]} ${y}`,
            color:   COMPARE_COLORS[i],
            vsLabel: vsM ? MONTHS_ES[vsM - 1] : 'anterior',
            kpis:    kpisArr[i],
            loading: loadings[i],
        };
    });
});

// ── Gráfico diario ────────────────────────────────────────────────────────
const DAILY_PALETTE = ['#534AB7', '#1D9E75', '#BA7517'];
const daysLabels = Array.from({ length: 31 }, (_, i) => i + 1);

const dailySeries = computed(() => {
    if (!kpis.value?.daily_series) return [];
    return kpis.value.daily_series.map((s) => ({
        nombre: s.nombre,
        datos:  s.datos,
    }));
});

async function load() {
    const period = from.value.slice(0, 7);
    await Promise.all([
        fetchKpis(period),
        fetchInsights(period),
        loadActionItems(),
        fetchKpisM1(prevPeriod1.value),
        fetchKpisM2(prevPeriod2.value),
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
/* ── Comparativa 3 meses ──────────────────────────────────────────────────── */
.warroom-container .wr-compare-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}
.warroom-container .wr-compare-toggles {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}
.warroom-container .wr-compare-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--wr-text-muted);
    cursor: pointer;
    user-select: none;
}
.warroom-container .wr-compare-toggle input[type="checkbox"] {
    width: 14px;
    height: 14px;
    cursor: pointer;
    accent-color: var(--wr-purple);
}
.warroom-container .wr-compare-dot { font-size: 14px; }

.warroom-container .wr-compare-cols {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.warroom-container .wr-compare-col {
    flex: 1;
    min-width: 200px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.warroom-container .wr-compare-col-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding-bottom: 6px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    margin-bottom: 4px;
}
.warroom-container .wr-cmp-card {
    background: rgba(255,255,255,0.03);
    border-left: 3px solid var(--wr-border);
    border-radius: 6px;
    padding: 10px 12px;
    transition: opacity 0.2s;
}
.warroom-container .wr-cmp-card-faded {
    opacity: 0.3;
}
.warroom-container .wr-cmp-label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
    gap: 6px;
}
.warroom-container .wr-cmp-label {
    font-size: 10px;
    color: var(--wr-text-dim);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.warroom-container .wr-cmp-check {
    width: 13px;
    height: 13px;
    cursor: pointer;
    flex-shrink: 0;
    accent-color: var(--wr-purple);
}
.warroom-container .wr-cmp-value {
    font-size: 15px;
    font-weight: 600;
    line-height: 1.2;
    margin-bottom: 4px;
}
.warroom-container .wr-cmp-delta {
    font-size: 10px;
}
.warroom-container .wr-delta-up   { color: #1D9E75; }
.warroom-container .wr-delta-down { color: #e05252; }
.warroom-container .wr-delta-neutral { color: var(--wr-text-dim); }

/* ── Tarjetas resumen ingresos diarios ────────────────────────────────────── */
.warroom-container .wr-daily-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}
@media (max-width: 640px) {
    .warroom-container .wr-daily-cards { grid-template-columns: 1fr; }
}
.warroom-container .wr-daily-card {
    background: rgba(255,255,255,0.04);
    border-left: 3px solid var(--wr-purple);
    border-radius: 6px;
    padding: 12px 14px;
}
.warroom-container .wr-daily-card-label {
    font-size: 11px;
    color: var(--wr-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 6px;
}
.warroom-container .wr-daily-card-total {
    font-size: 20px;
    font-weight: 600;
    line-height: 1.2;
}
.warroom-container .wr-daily-card-avg {
    font-size: 11px;
    color: var(--wr-text-dim);
    margin-top: 4px;
}

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
