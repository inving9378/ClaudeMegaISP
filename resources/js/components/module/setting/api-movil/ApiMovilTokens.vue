<template>
    <div class="api-movil-tokens mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">Tokens activos</h5>
            <div class="d-flex gap-2">
                <input v-model="search" class="form-control form-control-sm" style="width:220px" placeholder="Buscar usuario o device…" @keyup.enter="fetch" />
                <button class="btn btn-sm btn-outline-secondary" @click="fetch" :disabled="loading">
                    <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" :disabled="revoking" @click="revokeAll">
                    <i class="fa fa-trash"></i> Revocar todos
                </button>
            </div>
        </div>

        <div v-if="note" class="alert alert-info py-2">{{ note }}</div>
        <div v-if="loading && rows.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
        <div v-else-if="rows.length === 0" class="text-muted">Sin tokens activos.</div>

        <div v-else class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover m-0">
                        <thead class="table-light">
                            <tr><th>#</th><th>Usuario</th><th>Device / nombre</th><th>Último uso</th><th>Expira</th><th class="text-end">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in rows" :key="t.id">
                                <td class="text-muted small">{{ t.id }}</td>
                                <td>
                                    <div class="fw-bold">{{ t.user_name || '—' }}</div>
                                    <div class="text-muted small">{{ t.email || `user#${t.user_id}` }}</div>
                                </td>
                                <td><code>{{ t.device }}</code></td>
                                <td class="small">{{ formatDate(t.last_used_at) }}</td>
                                <td class="small" :class="expiryClass(t.expires_at)">{{ formatDate(t.expires_at) }}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-danger" :disabled="revokingId === t.id" @click="revoke(t)">
                                        <i class="fa fa-times"></i> Revocar
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div v-if="pagination" class="d-flex justify-content-between align-items-center mt-2">
            <div class="text-muted small">Página {{ pagination.current_page }} / {{ pagination.last_page }} · {{ pagination.total }} tokens</div>
            <div>
                <button class="btn btn-sm btn-outline-secondary me-1" :disabled="!pagination.prev_page_url || loading" @click="goto(pagination.current_page - 1)">←</button>
                <button class="btn btn-sm btn-outline-secondary" :disabled="!pagination.next_page_url || loading" @click="goto(pagination.current_page + 1)">→</button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ApiMovilTokens',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() {
        return { rows: [], pagination: null, search: '', loading: false, errorMsg: '', note: '', revokingId: null, revoking: false, page: 1 };
    },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = ''; this.note = '';
            try {
                const params = { page: this.page };
                if (this.search) params.search = this.search;
                const { data } = await axios.get(`${this.baseUrl}/tokens/data`, { params });
                if (data.note) this.note = data.note;
                this.rows = data.data || [];
                this.pagination = data.data ? {
                    current_page: data.current_page, last_page: data.last_page, total: data.total,
                    prev_page_url: data.prev_page_url, next_page_url: data.next_page_url,
                } : null;
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        goto(p) { this.page = Math.max(1, p); this.fetch(); },
        async revoke(t) {
            if (! confirm(`¿Revocar el token #${t.id} de ${t.user_name || t.email}?`)) return;
            this.revokingId = t.id;
            try {
                await axios.post(`${this.baseUrl}/tokens/${t.id}/revoke`, {}, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                await this.fetch();
            } catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.revokingId = null; }
        },
        async revokeAll() {
            if (! confirm('¿Revocar TODOS los tokens? Esto cerrará la sesión de todos los clientes móviles.')) return;
            this.revoking = true;
            try {
                const { data } = await axios.post(`${this.baseUrl}/tokens/revoke-all`, {}, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                alert('Revocados: ' + data.revoked);
                await this.fetch();
            } catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.revoking = false; }
        },
        formatDate(ts) { return ts ? new Date(ts).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }) : '—'; },
        expiryClass(d) {
            if (!d) return 'text-muted';
            const days = (new Date(d) - new Date()) / 86400000;
            return days < 0 ? 'text-danger' : days < 1 ? 'text-warning' : '';
        },
    },
};
</script>
