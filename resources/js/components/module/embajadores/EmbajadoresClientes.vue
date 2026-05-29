<template>
    <div class="embajadores-clientes">
        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-center">
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" placeholder="Buscar nombre o teléfono…"
                               v-model="filters.search" @input="debounceSearch">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" v-model="filters.plan_type" @change="cargar">
                            <option value="">Todos los planes</option>
                            <option value="single_reward">Mensualidad gratis</option>
                            <option value="multilevel">Multinivel</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" v-model="filters.eligible" @change="cargar">
                            <option value="">Todos los estados</option>
                            <option value="1">Elegibles</option>
                            <option value="0">En espera de umbral</option>
                        </select>
                    </div>
                    <div class="col-md-2 text-end">
                        <span class="text-muted small">{{ pagination.total ?? 0 }} embajadores</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="text-muted">Cargando embajadores…</div>
        <div v-else-if="embajadores.length === 0" class="alert alert-info">Sin resultados.</div>
        <div v-else>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Embajador</th>
                            <th>Plan</th>
                            <th>Estado</th>
                            <th class="text-end">Referidos</th>
                            <th class="text-end">Comisiones</th>
                            <th class="text-end">Recompensas</th>
                            <th class="text-center">Activado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in embajadores" :key="e.id">
                            <td>
                                <div class="fw-600">{{ clientName(e) }}</div>
                                <div class="text-muted small">{{ clientPhone(e) }}</div>
                            </td>
                            <td>
                                <span v-if="e.plan_type" class="badge" :class="planBadge(e.plan_type)">
                                    {{ planLabel(e.plan_type) }}
                                </span>
                                <span v-else class="badge bg-light text-dark">Sin plan</span>
                            </td>
                            <td>
                                <span class="badge" :class="e.is_eligible ? 'bg-success' : 'bg-warning text-dark'">
                                    {{ e.is_eligible ? 'Elegible' : 'Pendiente umbral' }}
                                </span>
                            </td>
                            <td class="text-end">{{ e.total_referrals }}</td>
                            <td class="text-end text-success fw-600">${{ formatMoney(e.total_commissions_earned) }}</td>
                            <td class="text-end">{{ e.total_rewards_earned }}</td>
                            <td class="text-center text-muted small">
                                {{ e.activated_at ? formatDate(e.activated_at) : '—' }}
                            </td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary me-1" @click="verDetalle(e)" title="Ver detalle">
                                    <i class="fa fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" @click="verArbol(e)" title="Ver árbol de red">
                                    <i class="fa fa-sitemap"></i>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación simple -->
            <div class="d-flex justify-content-end gap-2 mt-2" v-if="pagination.last_page > 1">
                <button class="btn btn-sm btn-outline-secondary" :disabled="pagination.current_page <= 1"
                        @click="cambiarPagina(pagination.current_page - 1)">‹ Anterior</button>
                <span class="btn btn-sm btn-light disabled">{{ pagination.current_page }} / {{ pagination.last_page }}</span>
                <button class="btn btn-sm btn-outline-secondary" :disabled="pagination.current_page >= pagination.last_page"
                        @click="cambiarPagina(pagination.current_page + 1)">Siguiente ›</button>
            </div>
        </div>

        <!-- Modal árbol de red -->
        <div v-if="arbol.open" class="modal-overlay" @click.self="arbol.open = false">
            <div class="modal-box card p-4" style="max-width:780px;width:100%;max-height:88vh;overflow-y:auto;">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="mb-0">
                        <i class="fa fa-sitemap me-2 text-primary"></i>
                        Red de {{ arbol.nombre }}
                    </h5>
                    <button class="btn btn-sm btn-outline-secondary" @click="arbol.open = false">✕</button>
                </div>
                <embajadores-arbol
                    :base-url="baseUrl"
                    :csrf-token="csrfToken"
                    :cliente-id="arbol.clienteId">
                </embajadores-arbol>
            </div>
        </div>

        <!-- Modal detalle embajador -->
        <div v-if="detalle.open" class="modal-overlay" @click.self="detalle.open = false">
            <div class="modal-box card p-4" style="max-width:680px;width:100%;max-height:85vh;overflow-y:auto;">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="mb-0">{{ clientName(detalle.profile) }}</h5>
                    <button class="btn btn-sm btn-outline-secondary" @click="detalle.open = false">✕</button>
                </div>

                <div v-if="detalle.loading" class="text-muted">Cargando…</div>
                <div v-else>
                    <div class="row mb-3">
                        <div class="col-sm-6">
                            <div class="small text-muted">Plan</div>
                            <span class="badge" :class="planBadge(detalle.profile.plan_type)">{{ planLabel(detalle.profile.plan_type) }}</span>
                        </div>
                        <div class="col-sm-6">
                            <div class="small text-muted">Código de referido</div>
                            <code>{{ detalle.profile.referral_code }}</code>
                        </div>
                        <div class="col-sm-6 mt-2">
                            <div class="small text-muted">Pagado para activación</div>
                            <strong>${{ formatMoney(detalle.profile.threshold_amount_paid) }}</strong> / $1,500.00
                        </div>
                        <div class="col-sm-6 mt-2">
                            <div class="small text-muted">Tamaño de la red (todos los niveles)</div>
                            <strong>{{ detalle.red_size }}</strong> referidos
                        </div>
                    </div>

                    <h6 class="mt-3">Últimas comisiones</h6>
                    <div v-if="!detalle.profile.commissions || detalle.profile.commissions.length === 0" class="text-muted small">Sin comisiones aún.</div>
                    <table v-else class="table table-sm">
                        <thead><tr><th>Periodo</th><th>Nivel</th><th class="text-end">Monto</th><th>Estado</th></tr></thead>
                        <tbody>
                            <tr v-for="c in detalle.profile.commissions" :key="c.id">
                                <td class="small">{{ c.period_month }}/{{ c.period_year }}</td>
                                <td><span class="badge bg-secondary">N{{ c.level }}</span></td>
                                <td class="text-end text-success">${{ formatMoney(c.commission_amount) }}</td>
                                <td><span class="badge" :class="statusBadge(c.status)">{{ c.status }}</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
let searchTimer = null;

export default {
    name: 'EmbajadoresClientes',
    props: {
        baseUrl:   { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading:     true,
            embajadores: [],
            pagination:  {},
            filters:     { search: '', plan_type: '', eligible: '', page: 1 },
            detalle:     { open: false, loading: false, profile: {}, red_size: 0 },
            arbol:       { open: false, clienteId: null, nombre: '' },
        };
    },
    mounted() {
        this.cargar();
    },
    methods: {
        async cargar() {
            this.loading = true;
            try {
                const params = { ...this.filters, per_page: 25 };
                const { data } = await axios.get(`${this.baseUrl}/clientes/data`, { params });
                this.embajadores = data.data;
                this.pagination  = { current_page: data.current_page, last_page: data.last_page, total: data.total };
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },
        debounceSearch() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => { this.filters.page = 1; this.cargar(); }, 350);
        },
        cambiarPagina(page) {
            this.filters.page = page;
            this.cargar();
        },
        verArbol(profile) {
            this.arbol = {
                open:      true,
                clienteId: profile.client_id,
                nombre:    this.clientName(profile),
            };
        },
        async verDetalle(profile) {
            this.detalle = { open: true, loading: true, profile, red_size: 0 };
            try {
                const { data } = await axios.get(`${this.baseUrl}/clientes/${profile.id}`);
                this.detalle.profile  = data.profile;
                this.detalle.red_size = data.red_size;
            } catch (e) {
                console.error(e);
            } finally {
                this.detalle.loading = false;
            }
        },
        clientName(e) {
            const info = e?.client?.client_main_information;
            if (!info) return `#${e?.client_id ?? e?.id}`;
            return `${info.name || ''} ${info.father_last_name || ''}`.trim();
        },
        clientPhone(e) {
            return e?.client?.client_main_information?.phone || '';
        },
        planLabel(plan) {
            return plan === 'single_reward' ? 'Mensualidad gratis' : plan === 'multilevel' ? 'Multinivel' : plan || '—';
        },
        planBadge(plan) {
            return plan === 'multilevel' ? 'bg-primary' : 'bg-info';
        },
        statusBadge(s) {
            return { pending: 'bg-warning text-dark', approved: 'bg-success', applied: 'bg-secondary', cancelled: 'bg-danger' }[s] || 'bg-light text-dark';
        },
        formatMoney(n) {
            return Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },
        formatDate(ts) {
            if (!ts) return '—';
            return new Date(ts).toLocaleDateString('es-MX');
        },
    },
};
</script>

<style scoped>
.fw-600 { font-weight: 600; }
.modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,.45);
    display: flex; align-items: center; justify-content: center; z-index: 1050;
}
</style>
