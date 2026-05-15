<template>
    <div class="megafamilia-reportes mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0">Reportes de uso</h5>
            <a :href="exportUrl" target="_blank" class="btn btn-sm btn-outline-danger">
                <i class="fa fa-file-pdf"></i> Exportar PDF
            </a>
        </div>

        <div v-if="loading && topApps.length === 0 && screenByDay.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title">Top apps</h6>
                        <div v-if="topApps.length === 0" class="text-muted small">Sin datos.</div>
                        <div v-else class="bars">
                            <div v-for="row in topApps" :key="row.app_name" class="bar-row">
                                <div class="bar-label" :title="row.app_name">{{ row.app_name }}</div>
                                <div class="bar-track">
                                    <div class="bar-fill" :style="{ width: barWidth(row.total, topApps) + '%' }">{{ row.total }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title">Tiempo de pantalla (últimos 14 días)</h6>
                        <div v-if="screenByDay.length === 0" class="text-muted small">Sin eventos screen_time_log.</div>
                        <div v-else class="bars">
                            <div v-for="row in screenByDay" :key="row.day" class="bar-row">
                                <div class="bar-label">{{ formatDay(row.day) }}</div>
                                <div class="bar-track">
                                    <div class="bar-fill alt" :style="{ width: barWidth(row.samples, screenByDay, 'samples') + '%' }">{{ row.samples }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaReportes',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { topApps: [], screenByDay: [], loading: false, errorMsg: '' }; },
    computed: {
        exportUrl() { return `${this.baseUrl}/reportes/export`; },
    },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/reportes/data`);
                this.topApps = data.top_apps || [];
                this.screenByDay = data.screen_by_day || [];
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        barWidth(value, list, key = 'total') {
            const max = Math.max(...list.map((r) => Number(r[key])), 1);
            return Math.max(5, (Number(value) / max) * 100);
        },
        formatDay(d) { return new Date(d).toLocaleDateString('es-MX', { month: 'short', day: 'numeric' }); },
    },
};
</script>

<style scoped>
.bars { display: flex; flex-direction: column; gap: 0.4rem; }
.bar-row { display: flex; align-items: center; gap: 0.6rem; }
.bar-label { min-width: 96px; font-size: 12px; color: #495057; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bar-track { flex: 1; background: #eef0f4; border-radius: 4px; height: 20px; overflow: hidden; }
.bar-fill {
    background: linear-gradient(90deg, #5b8def, #38c172);
    color: #fff; height: 100%; padding: 0 0.5rem; font-size: 11px; line-height: 20px; text-align: right; border-radius: 4px;
}
.bar-fill.alt { background: linear-gradient(90deg, #ff8c00, #e74c3c); }
</style>
