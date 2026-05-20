<template>
    <div class="megafamilia-terminos mt-3">
        <h5 class="mb-3">Términos y condiciones</h5>

        <div class="row">
            <div class="col-md-4 mb-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <i class="fa fa-file-contract fa-2x text-info mb-2"></i>
                        <div class="text-muted small">Versión vigente</div>
                        <div class="h3 m-0">v1.0</div>
                        <div class="text-muted small">2026-05</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <i class="fa fa-check-circle fa-2x text-success mb-2"></i>
                        <div class="text-muted small">Aceptaciones registradas</div>
                        <div class="h3 m-0 text-success">{{ acceptedTotal }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card text-center">
                    <div class="card-body">
                        <i class="fa fa-clock fa-2x text-warning mb-2"></i>
                        <div class="text-muted small">Pendientes de aceptar</div>
                        <div class="h3 m-0 text-warning">{{ pendingCount }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h6 class="card-title">Registro de aceptaciones</h6>
                <div v-if="loading && rows.length === 0" class="text-muted">Cargando…</div>
                <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
                <div v-else-if="rows.length === 0" class="text-muted">Nadie ha aceptado todavía.</div>
                <div v-else class="table-responsive">
                    <table class="table table-sm table-hover m-0">
                        <thead><tr><th>Cliente</th><th>Email</th><th>Aceptado</th><th>IP</th></tr></thead>
                        <tbody>
                            <tr v-for="a in rows" :key="a.id">
                                <td>{{ a.client?.name || '—' }}</td>
                                <td class="text-muted small">{{ a.client?.email || '—' }}</td>
                                <td class="small">{{ formatDate(a.terms_accepted_at) }}</td>
                                <td class="text-muted small"><code>{{ a.terms_ip || '—' }}</code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaTerminos',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { rows: [], acceptedTotal: 0, pendingCount: 0, loading: false, errorMsg: '' }; },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/terminos/data`);
                this.rows = data.accepted?.data || [];
                this.acceptedTotal = data.accepted?.total || 0;
                this.pendingCount = data.pending_count || 0;
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        formatDate(ts) { return ts ? new Date(ts).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) : '—'; },
    },
};
</script>
