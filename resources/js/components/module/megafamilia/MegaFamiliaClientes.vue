<template>
    <div class="megafamilia-clientes">
        <div class="card mt-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h5 class="card-title m-0">Clientes</h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <select v-model="planFilter" class="form-select form-select-sm" style="width:140px" @change="fetch">
                            <option value="">Todos los planes</option>
                            <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <select v-model="statusFilter" class="form-select form-select-sm" style="width:140px" @change="fetch">
                            <option value="">Todos los estados</option>
                            <option value="active">Activo</option>
                            <option value="suspended">Suspendido</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                        <input v-model="search" class="form-control form-control-sm" style="width:220px" placeholder="Nombre o email…" @keyup.enter="fetch" />
                        <button class="btn btn-sm btn-outline-secondary" @click="fetch" :disabled="loading">
                            <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                        </button>
                    </div>
                </div>

                <div v-if="loading && rows.length === 0" class="text-muted">Cargando…</div>
                <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
                <div v-else-if="rows.length === 0" class="text-muted">No hay cuentas con esos filtros.</div>
                <div v-else class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Plan</th>
                                <th>Estado</th>
                                <th>Licencia</th>
                                <th>Vence</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="acc in rows" :key="acc.id">
                                <td>
                                    <div class="fw-bold">{{ acc.client?.name || '—' }}</div>
                                    <div class="text-muted small">{{ acc.client?.email || `#${acc.client_id}` }}</div>
                                </td>
                                <td>
                                    <span class="badge" :class="planBadge(acc.plan?.slug)">{{ acc.plan?.name || '—' }}</span>
                                </td>
                                <td>
                                    <span class="badge" :class="statusBadge(acc.status)">{{ statusLabel(acc.status) }}</span>
                                </td>
                                <td class="small text-muted">{{ acc.licensed_at ? formatDate(acc.licensed_at) : '—' }}</td>
                                <td class="small" :class="expirationClass(acc.expires_at)">{{ acc.expires_at ? formatDate(acc.expires_at) : '—' }}</td>
                                <td class="text-end">
                                    <button v-if="acc.status !== 'active'" class="btn btn-sm btn-outline-success me-1" :disabled="acting === acc.id" @click="activate(acc)">
                                        <i class="fa fa-play"></i> Activar
                                    </button>
                                    <button v-if="acc.status === 'active'" class="btn btn-sm btn-outline-warning" :disabled="acting === acc.id" @click="suspend(acc)">
                                        <i class="fa fa-pause"></i> Suspender
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="pagination" class="d-flex justify-content-between align-items-center mt-2">
                    <div class="text-muted small">
                        Página {{ pagination.current_page }} / {{ pagination.last_page }} · {{ pagination.total }} cuentas
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-secondary me-1" :disabled="!pagination.prev_page_url || loading" @click="goto(pagination.current_page - 1)">←</button>
                        <button class="btn btn-sm btn-outline-secondary" :disabled="!pagination.next_page_url || loading" @click="goto(pagination.current_page + 1)">→</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaClientes',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() {
        return {
            rows: [], plans: [], pagination: null,
            search: '', planFilter: '', statusFilter: '',
            loading: false, errorMsg: '',
            acting: null, page: 1,
        };
    },
    mounted() { this.fetchPlans(); this.fetch(); },
    methods: {
        async fetchPlans() {
            try { const { data } = await axios.get(`${this.baseUrl}/planes/data`); this.plans = data.plans || []; } catch (_) {}
        },
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const params = { page: this.page };
                if (this.search) params.search = this.search;
                if (this.planFilter) params.plan_id = this.planFilter;
                if (this.statusFilter) params.status = this.statusFilter;
                const { data } = await axios.get(`${this.baseUrl}/clientes/data`, { params });
                this.rows = data.data || [];
                this.pagination = {
                    current_page: data.current_page, last_page: data.last_page, total: data.total,
                    prev_page_url: data.prev_page_url, next_page_url: data.next_page_url,
                };
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        goto(p) { this.page = Math.max(1, p); this.fetch(); },
        async activate(acc) {
            this.acting = acc.id;
            try { await axios.post(`${this.baseUrl}/clientes/${acc.id}/activate`, {}, { headers: { 'X-CSRF-TOKEN': this.csrfToken } }); acc.status = 'active'; }
            catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.acting = null; }
        },
        async suspend(acc) {
            this.acting = acc.id;
            try { await axios.post(`${this.baseUrl}/clientes/${acc.id}/suspend`, {}, { headers: { 'X-CSRF-TOKEN': this.csrfToken } }); acc.status = 'suspended'; }
            catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.acting = null; }
        },
        planBadge(slug) {
            return { basico: 'bg-secondary', plus: 'bg-info', premium: 'bg-warning text-dark' }[slug] || 'bg-light text-dark';
        },
        statusBadge(s) { return { active: 'bg-success', suspended: 'bg-warning text-dark', cancelled: 'bg-danger' }[s] || 'bg-light text-dark'; },
        statusLabel(s) { return { active: 'Activo', suspended: 'Suspendido', cancelled: 'Cancelado' }[s] || s; },
        expirationClass(d) {
            if (!d) return 'text-muted';
            const days = (new Date(d) - new Date()) / 86400000;
            return days < 0 ? 'text-danger' : days < 7 ? 'text-warning' : '';
        },
        formatDate(ts) { return new Date(ts).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' }); },
    },
};
</script>
