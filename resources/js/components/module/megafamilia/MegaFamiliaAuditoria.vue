<template>
    <div class="megafamilia-auditoria mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">Auditoría de eventos</h5>
            <div class="d-flex gap-2 flex-wrap">
                <input v-model="actionFilter" class="form-control form-control-sm" style="width:180px" placeholder="Acción…" @keyup.enter="fetch" />
                <input v-model="dateFrom" type="date" class="form-control form-control-sm" style="width:160px" @change="fetch" />
                <input v-model="dateTo" type="date" class="form-control form-control-sm" style="width:160px" @change="fetch" />
                <button class="btn btn-sm btn-outline-secondary" @click="fetch" :disabled="loading">
                    <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                </button>
            </div>
        </div>

        <div v-if="loading && rows.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
        <div v-else-if="rows.length === 0" class="text-muted">No hay eventos con esos filtros.</div>

        <div v-else class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover m-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Acción</th>
                                <th>Cuenta</th>
                                <th>Perfil</th>
                                <th>Device</th>
                                <th>IP</th>
                                <th>Detalle</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="ev in rows" :key="ev.id">
                                <td class="text-muted small">{{ ev.id }}</td>
                                <td>
                                    <i class="fa me-1" :class="actionIcon(ev.action)"></i>
                                    <code>{{ ev.action }}</code>
                                </td>
                                <td>#{{ ev.account_id }}</td>
                                <td>{{ ev.profile_id || '—' }}</td>
                                <td>{{ ev.device_id || '—' }}</td>
                                <td class="small text-muted">{{ ev.ip || '—' }}</td>
                                <td class="detail-cell" :title="ev.detail">{{ truncate(ev.detail, 60) }}</td>
                                <td class="small text-muted">{{ formatDate(ev.created_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="pagination" class="d-flex justify-content-between align-items-center mt-2">
            <div class="text-muted small">Página {{ pagination.current_page }} / {{ pagination.last_page }} · {{ pagination.total }} eventos</div>
            <div>
                <button class="btn btn-sm btn-outline-secondary me-1" :disabled="!pagination.prev_page_url || loading" @click="goto(pagination.current_page - 1)">←</button>
                <button class="btn btn-sm btn-outline-secondary" :disabled="!pagination.next_page_url || loading" @click="goto(pagination.current_page + 1)">→</button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaAuditoria',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { rows: [], pagination: null, actionFilter: '', dateFrom: '', dateTo: '', loading: false, errorMsg: '', page: 1 }; },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const params = { page: this.page };
                if (this.actionFilter) params.action = this.actionFilter;
                if (this.dateFrom) params.date_from = this.dateFrom;
                if (this.dateTo) params.date_to = this.dateTo;
                const { data } = await axios.get(`${this.baseUrl}/auditoria/data`, { params });
                this.rows = data.data || [];
                this.pagination = {
                    current_page: data.current_page, last_page: data.last_page, total: data.total,
                    prev_page_url: data.prev_page_url, next_page_url: data.next_page_url,
                };
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        goto(p) { this.page = Math.max(1, p); this.fetch(); },
        actionIcon(a) {
            if (a?.includes('notification')) return 'fa-bell text-warning';
            if (a?.includes('login') || a?.includes('auth')) return 'fa-key text-primary';
            if (a?.includes('delete')) return 'fa-trash text-danger';
            if (a?.includes('update')) return 'fa-edit text-info';
            return 'fa-circle-info text-muted';
        },
        truncate(s, n) { if (!s) return ''; return s.length > n ? s.slice(0, n) + '…' : s; },
        formatDate(ts) { return new Date(ts).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }); },
    },
};
</script>

<style scoped>
.detail-cell { max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: monospace; font-size: 11px; }
</style>
