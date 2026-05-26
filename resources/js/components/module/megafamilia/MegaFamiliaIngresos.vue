<template>
    <div class="megafamilia-ingresos mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-coins me-2 text-primary"></i>
                Ingresos MegaFamilia
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch">
                    <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                </button>
                <button class="btn btn-sm btn-outline-primary" @click="exportCsv">
                    <i class="fa fa-file-csv me-1"></i> Exportar reporte
                </button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="row g-2 mb-3">
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-success">
                    <div class="card-body p-3">
                        <div class="text-muted small">MRR actual</div>
                        <div class="kpi-num text-success">${{ money(data.mrr) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-primary">
                    <div class="card-body p-3">
                        <div class="text-muted small">ARR estimado</div>
                        <div class="kpi-num text-primary">${{ money(data.arr) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-info">
                    <div class="card-body p-3">
                        <div class="text-muted small">Licencias activas</div>
                        <div class="kpi-num">{{ data.active_licenses }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-warning">
                    <div class="card-body p-3">
                        <div class="text-muted small">ARPU (avg/cliente)</div>
                        <div class="kpi-num text-warning">${{ money(data.avg_per_client) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-center py-3">
            <div class="spinner-border text-primary"></div>
        </div>

        <!-- Evolución de ingresos -->
        <div class="card mb-3">
            <div class="card-header py-2 bg-light"><strong>Evolución de ingresos — últimos 12 meses</strong></div>
            <div class="card-body">
                <canvas ref="chartEvol" height="90"></canvas>
            </div>
        </div>

        <div class="row">
            <!-- Por plan -->
            <div class="col-lg-6 mb-3">
                <div class="card h-100">
                    <div class="card-header py-2 bg-light"><strong>Ingresos por plan</strong></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Plan</th>
                                    <th class="text-end">Licencias</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-end">Total</th>
                                    <th style="width:30%">% MRR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!data.by_plan?.length">
                                    <td colspan="5" class="text-center text-muted py-3">Sin datos.</td>
                                </tr>
                                <tr v-for="p in data.by_plan" :key="p.id">
                                    <td><strong>{{ p.name }}</strong></td>
                                    <td class="text-end">{{ p.licenses }}</td>
                                    <td class="text-end small">${{ money(p.unit_price) }}</td>
                                    <td class="text-end">${{ money(p.total) }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress flex-grow-1" style="height:8px;">
                                                <div class="progress-bar bg-success" :style="`width:${p.percent}%`"></div>
                                            </div>
                                            <small class="text-muted">{{ p.percent }}%</small>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Renovaciones recientes -->
            <div class="col-lg-6 mb-3">
                <div class="card h-100">
                    <div class="card-header py-2 bg-light"><strong>Últimas renovaciones</strong></div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Cliente</th>
                                    <th>Plan</th>
                                    <th class="text-end">Monto</th>
                                    <th>Renovación</th>
                                    <th>Vence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!data.recent_renewals?.length">
                                    <td colspan="5" class="text-center text-muted py-3">Sin renovaciones recientes.</td>
                                </tr>
                                <tr v-for="r in data.recent_renewals" :key="r.id">
                                    <td>
                                        <strong>{{ r.client }}</strong>
                                        <div class="text-muted small">{{ r.email }}</div>
                                    </td>
                                    <td><span class="badge bg-light text-dark">{{ r.plan }}</span></td>
                                    <td class="text-end">${{ money(r.amount) }}</td>
                                    <td class="small">{{ formatDate(r.activated_at) }}</td>
                                    <td class="small">{{ formatDate(r.expires_at) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Por vencer este mes -->
        <div class="card mb-3">
            <div class="card-header py-2 bg-light d-flex justify-content-between">
                <strong>Por vencer en los próximos 30 días</strong>
                <span class="badge bg-warning text-dark">{{ (data.expiring_soon || []).length }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Plan</th>
                            <th class="text-end">Monto</th>
                            <th>Vencimiento</th>
                            <th class="text-center">Días restantes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!data.expiring_soon?.length">
                            <td colspan="5" class="text-center text-muted py-3">Ninguna licencia vence en los próximos 30 días.</td>
                        </tr>
                        <tr v-for="e in data.expiring_soon" :key="e.id">
                            <td><strong>{{ e.client }}</strong></td>
                            <td><span class="badge bg-light text-dark">{{ e.plan }}</span></td>
                            <td class="text-end">${{ money(e.amount) }}</td>
                            <td class="small">{{ formatDate(e.expires_at) }}</td>
                            <td class="text-center">
                                <span class="badge" :class="e.days_left < 7 ? 'bg-danger' : 'bg-warning text-dark'">
                                    {{ e.days_left }} días
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

export default {
    name: 'MegaFamiliaIngresos',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: false,
            data: {
                mrr: 0, arr: 0, active_licenses: 0, avg_per_client: 0,
                by_plan: [], monthly_evolution: [], recent_renewals: [], expiring_soon: [],
            },
            chartEvol: null,
        };
    },
    mounted() { this.fetch(); },
    beforeUnmount() { this.chartEvol?.destroy(); },
    methods: {
        async fetch() {
            this.loading = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/ingresos/data`);
                this.data = {
                    mrr: data.mrr || 0,
                    arr: data.arr || 0,
                    active_licenses: data.active_licenses || 0,
                    avg_per_client: data.avg_per_client || 0,
                    by_plan: data.by_plan || [],
                    monthly_evolution: data.monthly_evolution || [],
                    recent_renewals: data.recent_renewals || [],
                    expiring_soon: data.expiring_soon || [],
                };
                this.$nextTick(() => this.renderChart());
            } catch (e) {
                console.error('[MF/Ingresos]', e);
            } finally {
                this.loading = false;
            }
        },
        renderChart() {
            const el = this.$refs.chartEvol;
            if (!el) return;
            this.chartEvol?.destroy();
            this.chartEvol = new Chart(el, {
                type: 'line',
                data: {
                    labels: this.data.monthly_evolution.map(r => r.label),
                    datasets: [{
                        label: 'Ingresos',
                        data: this.data.monthly_evolution.map(r => r.revenue),
                        fill: true,
                        backgroundColor: 'rgba(25,135,84,0.10)',
                        borderColor: '#198754',
                        tension: 0.35,
                        pointRadius: 4,
                    }],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => '$' + Number(ctx.parsed.y).toLocaleString('es-MX', { minimumFractionDigits: 2 }),
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => '$' + Number(v).toLocaleString('es-MX') },
                        },
                    },
                },
            });
        },
        exportCsv() { window.location.href = `${this.baseUrl}/ingresos/export`; },
        money(v) {
            return Number(v || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatDate(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
        },
    },
};
</script>

<style scoped>
.kpi-card { border: 1px solid #e9ecef; }
.kpi-card.kpi-success { border-left: 4px solid #198754; }
.kpi-card.kpi-primary { border-left: 4px solid #0d6efd; }
.kpi-card.kpi-info    { border-left: 4px solid #0dcaf0; }
.kpi-card.kpi-warning { border-left: 4px solid #ffc107; }
.kpi-num { font-size: 1.6rem; font-weight: 700; line-height: 1; margin-top: 4px; }

canvas { max-height: 260px; }
</style>
