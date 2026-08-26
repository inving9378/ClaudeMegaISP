<template>
    <div class="embajadores-client-tab mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-user-friends me-2 text-primary"></i>
                Embajadores Meganet
            </h5>
            <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch">
                <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
            </button>
        </div>

        <!-- Cargando -->
        <div v-if="loading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary"></div>
        </div>

        <!-- Sin perfil de embajador -->
        <div v-else-if="!profile" class="card">
            <div class="card-body text-center text-muted py-5">
                <i class="fa fa-user-friends fa-2x mb-2 d-block opacity-50"></i>
                Este cliente no participa en el programa Embajadores Meganet.
            </div>
        </div>

        <!-- Resumen del perfil -->
        <template v-else>
            <div class="row g-2 mb-3">
                <div class="col-md-3 col-6">
                    <div class="card kpi-card kpi-primary"><div class="card-body p-3">
                        <div class="text-muted small">Plan</div>
                        <div class="kpi-num" style="font-size:1rem;">{{ planLabel(profile.plan_type) }}</div>
                    </div></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card kpi-card kpi-info"><div class="card-body p-3">
                        <div class="text-muted small">Referidos totales</div>
                        <div class="kpi-num">{{ profile.total_referrals ?? 0 }}</div>
                    </div></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card kpi-card kpi-success"><div class="card-body p-3">
                        <div class="text-muted small">Comisiones ganadas</div>
                        <div class="kpi-num text-success">${{ formatMoney(profile.total_commissions_earned) }}</div>
                    </div></div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card kpi-card kpi-warning"><div class="card-body p-3">
                        <div class="text-muted small">Tamaño de la red</div>
                        <div class="kpi-num">{{ redSize }}</div>
                    </div></div>
                </div>
            </div>

            <div class="card mb-3"><div class="card-body">
                <div class="row">
                    <div class="col-md-6 small">
                        <div class="mb-1"><span class="text-muted">Código de referido:</span> <code>{{ profile.referral_code || '—' }}</code></div>
                        <div class="mb-1"><span class="text-muted">Elegible:</span>
                            <span class="badge" :class="profile.is_eligible ? 'bg-success' : 'bg-secondary'">
                                {{ profile.is_eligible ? 'Sí' : 'No' }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6 small">
                        <div class="mb-1"><span class="text-muted">Activado:</span> {{ formatDate(profile.activated_at) }}</div>
                        <div class="mb-1"><span class="text-muted">Recompensas ganadas:</span> ${{ formatMoney(profile.total_rewards_earned) }}</div>
                    </div>
                </div>
            </div></div>

            <!-- Comisiones recientes -->
            <div class="card mb-3">
                <div class="card-header py-2"><strong>Comisiones recientes</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Nivel</th>
                                <th>Monto</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!profile.commissions?.length">
                                <td colspan="3" class="text-center text-muted py-3">Sin comisiones aún.</td>
                            </tr>
                            <tr v-for="c in profile.commissions" :key="c.id">
                                <td>N{{ c.level }}</td>
                                <td>${{ formatMoney(c.commission_amount) }}</td>
                                <td><span class="badge bg-light text-dark border">{{ c.status }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="text-end mt-2">
                <a href="/embajadores/clientes" class="btn btn-sm btn-link">
                    Ver programa completo <i class="fa fa-arrow-right ms-1"></i>
                </a>
            </div>
        </template>
    </div>
</template>

<script>
const PLAN_LABEL = {
    single_reward: 'Mensualidad gratis',
    multilevel: 'Multinivel',
};

export default {
    name: 'EmbajadoresClientTab',
    props: {
        // Contrato infra de ficha extensible (roadmap #5): toda pestaña de
        // módulo recibe `id` (string) y `clientId` (number) del cliente ISP.
        id: { type: [String, Number], default: null },
        clientId: { type: Number, default: null },
    },
    data() {
        return { loading: false, profile: null, redSize: 0 };
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
                const { data } = await axios.get(`/embajadores/clientes/por-cliente-isp/${clientId}`);
                this.profile = data.profile || null;
                this.redSize = data.red_size ?? 0;
            } catch (e) {
                console.error('[Embajadores/ClientTab]', e);
                this.profile = null;
            } finally {
                this.loading = false;
            }
        },
        planLabel(p) { return PLAN_LABEL[p] || p || '—'; },
        formatMoney(n) {
            return Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
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
