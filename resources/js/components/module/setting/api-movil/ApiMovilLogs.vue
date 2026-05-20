<template>
    <div class="api-movil-logs mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">Logs de acceso /api/megafamilia/*</h5>
            <a :href="csvUrl" target="_blank" class="btn btn-sm btn-outline-success">
                <i class="fa fa-file-csv"></i> Exportar CSV
            </a>
        </div>

        <div class="card mb-2">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Desde</label>
                        <input v-model="filters.date_from" type="date" class="form-control form-control-sm" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Hasta</label>
                        <input v-model="filters.date_to" type="date" class="form-control form-control-sm" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">User id</label>
                        <input v-model="filters.user_id" type="number" class="form-control form-control-sm" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted mb-1">Status</label>
                        <input v-model="filters.status" type="number" placeholder="200, 401…" class="form-control form-control-sm" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted mb-1">Endpoint contiene</label>
                        <input v-model="filters.endpoint" class="form-control form-control-sm" placeholder="/profiles…" />
                    </div>
                    <div class="col-md-1">
                        <button class="btn btn-sm btn-primary w-100" :disabled="loading" @click="fetch">
                            <i class="fa fa-filter"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading && rows.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
        <div v-else-if="rows.length === 0" class="text-muted">Sin requests todavía. Las llamadas a /api/megafamilia/* se registrarán aquí.</div>

        <div v-else class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover m-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>User</th>
                                <th style="width:70px">Método</th>
                                <th>Endpoint</th>
                                <th style="width:80px">Status</th>
                                <th>IP</th>
                                <th>ms</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in rows" :key="r.id">
                                <td class="small">{{ formatDate(r.created_at) }}</td>
                                <td class="small">{{ r.user_id || '—' }}</td>
                                <td><span class="badge" :class="methodBadge(r.method)">{{ r.method }}</span></td>
                                <td><code>{{ r.endpoint }}</code></td>
                                <td><span class="badge" :class="statusBadge(r.status)">{{ r.status }}</span></td>
                                <td class="small text-muted">{{ r.ip || '—' }}</td>
                                <td class="small text-muted">{{ r.duration_ms || 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="pagination" class="d-flex justify-content-between align-items-center mt-2">
            <div class="text-muted small">Página {{ pagination.current_page }} / {{ pagination.last_page }} · {{ pagination.total }} logs</div>
            <div>
                <button class="btn btn-sm btn-outline-secondary me-1" :disabled="!pagination.prev_page_url || loading" @click="goto(pagination.current_page - 1)">←</button>
                <button class="btn btn-sm btn-outline-secondary" :disabled="!pagination.next_page_url || loading" @click="goto(pagination.current_page + 1)">→</button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ApiMovilLogs',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() {
        return {
            rows: [], pagination: null, loading: false, errorMsg: '', page: 1,
            filters: { date_from: '', date_to: '', user_id: '', status: '', endpoint: '' },
        };
    },
    computed: {
        csvUrl() {
            const qs = new URLSearchParams();
            Object.entries(this.filters).forEach(([k, v]) => { if (v) qs.set(k, v); });
            const q = qs.toString();
            return `${this.baseUrl}/logs/csv${q ? '?' + q : ''}`;
        },
    },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const params = { page: this.page };
                Object.entries(this.filters).forEach(([k, v]) => { if (v) params[k] = v; });
                const { data } = await axios.get(`${this.baseUrl}/logs/data`, { params });
                this.rows = data.data || [];
                this.pagination = {
                    current_page: data.current_page, last_page: data.last_page, total: data.total,
                    prev_page_url: data.prev_page_url, next_page_url: data.next_page_url,
                };
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        goto(p) { this.page = Math.max(1, p); this.fetch(); },
        methodBadge(m) { return ({ GET: 'bg-info', POST: 'bg-success', PUT: 'bg-warning text-dark', PATCH: 'bg-warning text-dark', DELETE: 'bg-danger' })[m] || 'bg-secondary'; },
        statusBadge(s) {
            if (s >= 500) return 'bg-danger';
            if (s >= 400) return 'bg-warning text-dark';
            if (s >= 300) return 'bg-info';
            if (s >= 200) return 'bg-success';
            return 'bg-secondary';
        },
        formatDate(ts) { return ts ? new Date(ts).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'medium' }) : '—'; },
    },
};
</script>
