<template>
    <div class="megafamilia-ingresos mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0">Ingresos MegaFamilia</h5>
            <a :href="`${baseUrl}/ingresos/export`" target="_blank" class="btn btn-sm btn-outline-danger">
                <i class="fa fa-file-pdf"></i> Exportar PDF
            </a>
        </div>

        <div v-if="loading && byPlan.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>

        <!-- KPI -->
        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Ingreso recurrente mensual (MRR)</div>
                        <div class="h2 mb-0 text-success">${{ formatMoney(mrr) }} MXN</div>
                    </div>
                </div>
            </div>
            <div v-for="row in byPlan" :key="row.name" class="col-md mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ row.name }}</div>
                        <div class="h4 mb-0">${{ formatMoney(row.monthly_revenue) }}</div>
                        <div class="small text-muted">{{ row.accounts }} cuentas</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly bars -->
        <div class="card mt-2">
            <div class="card-body">
                <h6 class="card-title">Ingresos por mes (últimos 12)</h6>
                <div v-if="byMonth.length === 0" class="text-muted small">Sin datos.</div>
                <div v-else class="bars">
                    <div v-for="row in byMonth" :key="row.month" class="bar-row">
                        <div class="bar-label">{{ row.month }}</div>
                        <div class="bar-track">
                            <div class="bar-fill" :style="{ width: barWidth(row.revenue) + '%' }">${{ formatMoney(row.revenue) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaIngresos',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { byPlan: [], byMonth: [], mrr: 0, loading: false, errorMsg: '' }; },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/ingresos/data`);
                this.byPlan = data.by_plan || [];
                this.byMonth = data.by_month || [];
                this.mrr = Number(data.mrr) || 0;
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        barWidth(value) {
            const max = Math.max(...this.byMonth.map((r) => Number(r.revenue)), 1);
            return Math.max(5, (Number(value) / max) * 100);
        },
        formatMoney(n) { return Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    },
};
</script>

<style scoped>
.bars { display: flex; flex-direction: column; gap: 0.45rem; }
.bar-row { display: flex; align-items: center; gap: 0.6rem; }
.bar-label { min-width: 70px; font-family: monospace; font-size: 12px; color: #6c757d; }
.bar-track { flex: 1; background: #eef0f4; border-radius: 4px; height: 22px; overflow: hidden; }
.bar-fill {
    background: linear-gradient(90deg, #38c172, #ff8c00);
    color: #fff;
    height: 100%;
    padding: 0 0.5rem;
    font-size: 12px;
    line-height: 22px;
    text-align: right;
    border-radius: 4px;
}
</style>
