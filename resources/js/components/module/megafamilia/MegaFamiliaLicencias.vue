<template>
    <div class="megafamilia-licencias mt-3">
        <h5 class="mb-3">Inventario de licencias por plan</h5>
        <div v-if="loading && byPlan.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>

        <div class="row">
            <div v-for="row in byPlan" :key="row.id" class="col-md-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <h5 class="m-0">{{ row.name }}</h5>
                            <span class="badge bg-light text-dark">{{ row.total }} totales</span>
                        </div>
                        <div class="mt-3">
                            <div class="d-flex justify-content-between small text-muted">
                                <span>Activas</span><span class="fw-bold text-success">{{ row.active }}</span>
                            </div>
                            <div class="progress my-1" style="height:8px;">
                                <div class="progress-bar bg-success" :style="{ width: pct(row.active, row.total) + '%' }"></div>
                            </div>

                            <div class="d-flex justify-content-between small text-muted mt-2">
                                <span>Suspendidas</span><span class="fw-bold text-warning">{{ row.suspended }}</span>
                            </div>
                            <div class="progress my-1" style="height:8px;">
                                <div class="progress-bar bg-warning" :style="{ width: pct(row.suspended, row.total) + '%' }"></div>
                            </div>

                            <div class="d-flex justify-content-between small text-muted mt-2">
                                <span>Expiradas</span><span class="fw-bold text-danger">{{ row.expired }}</span>
                            </div>
                            <div class="progress my-1" style="height:8px;">
                                <div class="progress-bar bg-danger" :style="{ width: pct(row.expired, row.total) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <h5 class="mb-2 mt-3">Últimas licencias</h5>
        <div class="card">
            <div class="card-body">
                <div v-if="list.length === 0" class="text-muted">No hay licencias todavía.</div>
                <div v-else class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Plan</th>
                                <th>Estado</th>
                                <th>Activada</th>
                                <th>Vence</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="l in list" :key="l.id">
                                <td>#{{ l.id }}</td>
                                <td>{{ l.account?.client?.name || `acc#${l.account_id}` }}</td>
                                <td>{{ l.plan?.name || '—' }}</td>
                                <td><span class="badge" :class="statusBadge(l.status)">{{ statusLabel(l.status) }}</span></td>
                                <td class="small text-muted">{{ l.activated_at ? formatDate(l.activated_at) : '—' }}</td>
                                <td class="small">{{ l.expires_at ? formatDate(l.expires_at) : '—' }}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-success me-1" :disabled="acting === l.id" @click="renew(l)">
                                        <i class="fa fa-redo"></i> Renovar
                                    </button>
                                    <button v-if="l.status === 'active'" class="btn btn-sm btn-outline-warning" :disabled="acting === l.id" @click="suspend(l)">
                                        <i class="fa fa-pause"></i> Suspender
                                    </button>
                                </td>
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
    name: 'MegaFamiliaLicencias',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() {
        return { byPlan: [], list: [], loading: false, errorMsg: '', acting: null };
    },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/licencias/data`);
                this.byPlan = data.by_plan || [];
                this.list = data.list?.data || [];
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        pct(part, total) { return total > 0 ? Math.round((Number(part) / Number(total)) * 100) : 0; },
        async renew(l) {
            this.acting = l.id;
            try { const { data } = await axios.post(`${this.baseUrl}/licencias/${l.id}/renew`, {}, { headers: { 'X-CSRF-TOKEN': this.csrfToken } }); Object.assign(l, data.license); }
            catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.acting = null; }
        },
        async suspend(l) {
            const reason = prompt('Motivo de suspensión (opcional):');
            this.acting = l.id;
            try {
                await axios.post(`${this.baseUrl}/licencias/${l.id}/suspend`, { reason }, { headers: { 'X-CSRF-TOKEN': this.csrfToken } });
                l.status = 'suspended';
            } catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.acting = null; }
        },
        statusBadge(s) { return { active: 'bg-success', suspended: 'bg-warning text-dark', expired: 'bg-danger' }[s] || 'bg-light text-dark'; },
        statusLabel(s) { return { active: 'Activa', suspended: 'Suspendida', expired: 'Expirada' }[s] || s; },
        formatDate(ts) { return new Date(ts).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' }); },
    },
};
</script>
