<template>
    <div class="megafamilia-client-tab mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-shield-alt me-2 text-primary"></i>
                MegaFamilia
            </h5>
            <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch">
                <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
            </button>
        </div>

        <!-- Cargando -->
        <div v-if="loading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary"></div>
        </div>

        <!-- Sin cuenta MegaFamilia -->
        <div v-else-if="!account" class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fa fa-shield-alt fa-2x mb-2 d-block opacity-50"></i>
                Este cliente no tiene una cuenta MegaFamilia activa.
            </div>
        </div>

        <!-- Resumen de la cuenta -->
        <template v-else>
            <div class="row g-2 mb-3">
                <div class="col-md-3 col-6">
                    <div class="card kpi-card kpi-primary"><div class="card-body p-3">
                        <div class="text-muted small">Estado</div>
                        <div class="kpi-num">
                            <span class="badge" :class="statusBadge(account.status)">{{ statusLabel(account.status) }}</span>
                        </div>
                    </div></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card kpi-card kpi-info"><div class="card-body p-3">
                        <div class="text-muted small">Perfiles</div>
                        <div class="kpi-num">{{ account.profiles_count ?? 0 }}</div>
                    </div></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card kpi-card kpi-success"><div class="card-body p-3">
                        <div class="text-muted small">Dispositivos</div>
                        <div class="kpi-num text-success">{{ account.devices_count ?? 0 }}</div>
                    </div></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card kpi-card kpi-warning"><div class="card-body p-3">
                        <div class="text-muted small">Licencia vence</div>
                        <div class="kpi-num" style="font-size:1rem;">{{ formatDate(account.license?.expires_at || account.expires_at) }}</div>
                    </div></div>
                </div>
            </div>

            <div class="card mb-3"><div class="card-body">
                <div class="row">
                    <div class="col-md-6 small">
                        <div class="mb-1"><span class="text-muted">Plan:</span> <strong>{{ account.plan?.name || '—' }}</strong></div>
                        <div class="mb-1"><span class="text-muted">Titular:</span> <strong>{{ account.user?.name || '—' }}</strong></div>
                        <div class="mb-1"><span class="text-muted">Correo:</span> {{ account.user?.email || '—' }}</div>
                    </div>
                    <div class="col-md-6 small">
                        <div class="mb-1"><span class="text-muted">Licenciada:</span> {{ formatDate(account.licensed_at) }}</div>
                        <div class="mb-1"><span class="text-muted">Términos aceptados:</span> {{ formatDate(account.terms_accepted_at) }}</div>
                    </div>
                </div>
            </div></div>

            <!-- Perfiles -->
            <div class="card">
                <div class="card-header py-2"><strong>Perfiles supervisados</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th class="text-center">Dispositivos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!account.profiles?.length">
                                <td colspan="2" class="text-center text-muted py-3">Sin perfiles.</td>
                            </tr>
                            <tr v-for="p in account.profiles" :key="p.id">
                                <td>{{ p.name || ('Perfil #' + p.id) }}</td>
                                <td class="text-center">{{ p.devices_count ?? 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="text-end mt-2">
                <a :href="`/megafamilia/clientes/${account.id}`" class="btn btn-sm btn-link">
                    Ver cuenta completa <i class="fa fa-arrow-right ms-1"></i>
                </a>
            </div>
        </template>
    </div>
</template>

<script>
const STATUS_LABEL = {
    active: 'Activa', suspended: 'Suspendida', cancelled: 'Cancelada',
    pending: 'Pendiente', trial: 'Prueba',
};
const STATUS_BADGE = {
    active: 'bg-success', suspended: 'bg-warning text-dark',
    cancelled: 'bg-secondary', pending: 'bg-info text-dark', trial: 'bg-primary',
};

export default {
    name: 'MegaFamiliaClientTab',
    props: {
        // Contrato infra de ficha extensible (roadmap #5): toda pestaña de
        // módulo recibe `id` (string) y `clientId` (number) del cliente ISP.
        id: { type: [String, Number], default: null },
        clientId: { type: Number, default: null },
    },
    data() {
        return { loading: false, account: null };
    },
    mounted() {
        this.fetch();
    },
    methods: {
        async fetch() {
            const clientId = this.clientId || Number(this.id);
            if (!clientId) return;
            this.loading = true;
            try {
                const { data } = await axios.get(`/megafamilia/clientes/por-cliente-isp/${clientId}`);
                this.account = data.account || null;
            } catch (e) {
                console.error('[MF/ClientTab]', e);
                this.account = null;
            } finally {
                this.loading = false;
            }
        },
        statusLabel(s) { return STATUS_LABEL[s] || s || '—'; },
        statusBadge(s) { return STATUS_BADGE[s] || 'bg-light text-dark'; },
        formatDate(v) {
            if (!v) return '—';
            const d = new Date(v);
            return Number.isNaN(d.getTime()) ? v : d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
        },
    },
};
</script>

<style scoped>
.kpi-card { border: 1px solid #e9ecef; }
.kpi-card.kpi-primary { border-left: 4px solid #0d6efd; }
.kpi-card.kpi-warning { border-left: 4px solid #ffc107; }
.kpi-card.kpi-success { border-left: 4px solid #198754; }
.kpi-card.kpi-info    { border-left: 4px solid #0dcaf0; }
.kpi-num { font-size: 1.4rem; font-weight: 700; line-height: 1; margin-top: 4px; }
</style>
