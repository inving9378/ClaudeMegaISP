<template>
    <div class="wr-view wr-view-finanzas">
        <div class="wr-kpi-grid wr-kpi-grid-2">
            <KpiCard
                label="MRR (Ingresos recurrentes)"
                :value="formatCurrency(kpis?.mrr?.current)"
                :previousValue="formatCurrency(kpis?.mrr?.previous)"
                :delta="deltaStr(kpis?.mrr?.current, kpis?.mrr?.previous)"
                :deltaDirection="deltaDir(kpis?.mrr?.current, kpis?.mrr?.previous)"
                accent="green"
                icon="ti-chart-line"
                size="hero"
                :loading="loading"
            />
            <KpiCard
                label="Por cobrar"
                :value="formatCurrency(kpis?.por_cobrar?.amount)"
                :delta="kpis?.por_cobrar?.count ? `${kpis.por_cobrar.count} facturas` : null"
                delta-direction="neutral"
                accent="orange"
                icon="ti-receipt"
                size="hero"
                :loading="loading"
            />
        </div>

        <!-- Top deudores -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title"><i class="ti ti-alert-circle me-1"></i> Top deudores</div>
            <template v-if="loading">
                <q-skeleton v-for="i in 5" :key="i" type="text" class="mb-2" :width="`${90 - i * 8}%`" />
            </template>
            <div v-else-if="!kpis?.top_deudores?.length" class="wr-empty">Sin deudores registrados.</div>
            <table v-else class="wr-table">
                <thead>
                    <tr><th>Cliente</th><th class="text-end">Deuda</th><th class="text-end">Facturas</th></tr>
                </thead>
                <tbody>
                    <tr v-for="d in kpis.top_deudores.slice(0, 8)" :key="d.name">
                        <td>{{ d.name }}</td>
                        <td class="text-end wr-text-danger">{{ formatCurrency(d.deuda) }}</td>
                        <td class="text-end">{{ d.facturas }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Cash flow próximo -->
        <div class="wr-panel mt-3">
            <div class="wr-section-title"><i class="ti ti-calendar-dollar me-1"></i> Cash flow próximas 4 semanas</div>
            <template v-if="loading">
                <q-skeleton v-for="i in 4" :key="i" type="rect" height="24px" class="mb-1" />
            </template>
            <div v-else-if="!kpis?.cashflow_proximo?.length" class="wr-empty">Sin vencimientos próximos.</div>
            <div v-else class="wr-cashflow-list">
                <div v-for="row in kpis.cashflow_proximo" :key="row.due_date" class="wr-cashflow-row">
                    <span class="wr-cashflow-date">{{ formatDate(row.due_date) }}</span>
                    <div class="wr-cashflow-bar-wrap">
                        <div class="wr-cashflow-bar" :style="{ width: cashflowWidth(row.monto) + '%' }"></div>
                    </div>
                    <span class="wr-cashflow-amount">{{ formatCurrency(row.monto) }}</span>
                    <span class="wr-cashflow-count">{{ row.facturas }} fact.</span>
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
import { deltaStr, deltaDir, formatCurrency } from '../utils.js';

const props = defineProps({
    period: { type: String, required: true },
});

const { kpis, loading, fetchKpis } = useKpis('finanzas');
const { insights, loading: insightsLoading, source: insightsSource, fetchInsights } = useInsights('finanzas');

const maxDeuda = computed(() => {
    if (!kpis.value?.cashflow_proximo?.length) return 1;
    return Math.max(...kpis.value.cashflow_proximo.map(r => parseFloat(r.monto) || 0), 1);
});

function cashflowWidth(monto) {
    return Math.round((parseFloat(monto) / maxDeuda.value) * 100);
}

function formatDate(d) {
    if (!d) return '';
    const date = new Date(d + 'T00:00:00');
    return date.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' });
}

async function load() {
    await Promise.all([fetchKpis(props.period), fetchInsights(props.period)]);
}

onMounted(load);
watch(() => props.period, load);
</script>
