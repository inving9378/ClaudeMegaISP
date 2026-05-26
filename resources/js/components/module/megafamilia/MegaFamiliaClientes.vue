<template>
    <div class="megafamilia-clientes mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-users me-2 text-primary"></i>
                Clientes MegaFamilia
            </h5>
            <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch">
                <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
            </button>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Buscar</label>
                        <input
                            id="mf-clientes-search"
                            v-model="filters.search"
                            type="search"
                            class="form-control form-control-sm"
                            placeholder="Nombre o email…"
                            @keyup.enter="fetch(1)" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Plan</label>
                        <select v-model="filters.plan_id" class="form-select form-select-sm" @change="fetch(1)">
                            <option value="">Todos</option>
                            <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Estado</label>
                        <select v-model="filters.status" class="form-select form-select-sm" @change="fetch(1)">
                            <option value="">Todos</option>
                            <option value="active">Activo</option>
                            <option value="suspended">Suspendido</option>
                            <option value="cancelled">Cancelado</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-primary w-100" @click="fetch(1)">
                            <i class="fa fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card" id="mf-clientes-tabla">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Email</th>
                            <th>Plan</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Dispositivos</th>
                            <th>Fecha alta</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading && rows.length === 0">
                            <td colspan="7" class="text-center py-4 text-muted">Cargando…</td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td colspan="7" class="text-center py-4 text-muted">Sin resultados.</td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id" role="button" @click="openDrawer(row)">
                            <td><strong>{{ row.user?.name || '—' }}</strong></td>
                            <td class="small text-muted">{{ row.user?.email || '—' }}</td>
                            <td><span class="badge bg-light text-dark">{{ row.plan?.name || '—' }}</span></td>
                            <td class="text-center">
                                <span class="badge" :class="statusBadge(row.status)">{{ statusLabel(row.status) }}</span>
                            </td>
                            <td class="text-center">{{ row.devices_count || 0 }}</td>
                            <td class="small">{{ formatDate(row.created_at) }}</td>
                            <td class="text-end" @click.stop>
                                <button class="btn btn-sm btn-outline-primary" @click="openDrawer(row)">
                                    <i class="fa fa-eye"></i> Ver
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="pagination.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                <small class="text-muted">
                    Mostrando {{ pagination.from }}-{{ pagination.to }} de {{ pagination.total }}
                </small>
                <ul class="pagination pagination-sm m-0">
                    <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
                        <button class="page-link" @click="fetch(pagination.current_page - 1)">‹</button>
                    </li>
                    <li class="page-item"
                        v-for="p in pageRange" :key="p"
                        :class="{ active: p === pagination.current_page }">
                        <button class="page-link" @click="fetch(p)">{{ p }}</button>
                    </li>
                    <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
                        <button class="page-link" @click="fetch(pagination.current_page + 1)">›</button>
                    </li>
                </ul>
            </div>
        </div>

        <button @click="launchTour" class="btn-tour-help" title="Tour guiado">
            <i class="fas fa-question-circle"></i>
        </button>

        <!-- Drawer detalle -->
        <div v-if="drawer.open" class="mf-drawer-overlay" @click.self="closeDrawer">
            <aside class="mf-drawer">
                <header class="mf-drawer-header">
                    <div>
                        <h5 class="m-0">{{ drawer.data?.user?.name || 'Detalle' }}</h5>
                        <small class="text-muted">{{ drawer.data?.user?.email }}</small>
                    </div>
                    <button class="btn-close" @click="closeDrawer"></button>
                </header>
                <div class="mf-drawer-body">
                    <div v-if="drawer.loading" class="text-center py-5">
                        <div class="spinner-border text-primary"></div>
                    </div>
                    <template v-else-if="drawer.data">
                        <section class="mb-3">
                            <h6 class="text-uppercase small text-muted">Usuario</h6>
                            <ul class="list-unstyled small mb-0">
                                <li><strong>Nombre:</strong> {{ drawer.data.user?.name || '—' }}</li>
                                <li><strong>Email:</strong> {{ drawer.data.user?.email || '—' }}</li>
                            </ul>
                        </section>

                        <section class="mb-3">
                            <h6 class="text-uppercase small text-muted">Cliente ISP</h6>
                            <div v-if="drawer.data.client_isp" class="alert alert-info py-2 mb-0 small">
                                <i class="fa fa-link me-1"></i>
                                Vinculado al cliente ISP <strong>#{{ drawer.data.client_isp.id }}</strong>
                                <a :href="`${baseUrl.replace('/megafamilia','')}/client/edit/${drawer.data.client_isp.id}`" class="ms-2">Ver en módulo Cliente →</a>
                                <ul class="list-unstyled mt-1 mb-0">
                                    <li><strong>Fecha de corte:</strong> {{ drawer.data.client_isp.fecha_corte || '—' }}</li>
                                    <li><strong>Fecha de pago:</strong> {{ drawer.data.client_isp.fecha_pago || '—' }}</li>
                                </ul>
                            </div>
                            <div v-else class="text-muted small">Sin cliente ISP vinculado.</div>
                        </section>

                        <section class="mb-3">
                            <h6 class="text-uppercase small text-muted">Licencia activa</h6>
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="small">
                                    <div><strong>Plan:</strong> {{ drawer.data.plan?.name || '—' }}</div>
                                    <div><strong>Estado:</strong>
                                        <span class="badge ms-1" :class="statusBadge(drawer.data.status)">
                                            {{ statusLabel(drawer.data.status) }}
                                        </span>
                                    </div>
                                    <div v-if="drawer.data.license">
                                        <strong>Vence:</strong> {{ formatDate(drawer.data.license.expires_at) }}
                                    </div>
                                    <div v-if="drawer.data.license">
                                        <strong>Dispositivos:</strong>
                                        {{ totalDevices(drawer.data) }} / {{ drawer.data.plan?.max_devices || '∞' }}
                                    </div>
                                </div>
                                <button class="btn btn-sm btn-outline-primary" @click="openChangePlan(drawer.data)">
                                    <i class="fa fa-exchange-alt me-1"></i> Cambiar Plan
                                </button>
                            </div>
                        </section>

                        <section class="mb-3">
                            <h6 class="text-uppercase small text-muted">
                                Perfiles ({{ (drawer.data.profiles || []).length }})
                            </h6>
                            <ul class="list-group list-group-flush small">
                                <li v-for="profile in drawer.data.profiles" :key="profile.id"
                                    class="list-group-item px-0 py-1 d-flex justify-content-between">
                                    <span>
                                        <i class="fa fa-child me-1 text-info"></i>
                                        {{ profile.name }}
                                        <span class="text-muted">· {{ profile.age || '?' }} años</span>
                                    </span>
                                    <span class="badge bg-light text-dark">{{ profile.devices_count }} disp.</span>
                                </li>
                                <li v-if="!drawer.data.profiles?.length" class="list-group-item px-0 py-1 text-muted">
                                    Sin perfiles.
                                </li>
                            </ul>
                        </section>

                        <section class="mb-3">
                            <h6 class="text-uppercase small text-muted">
                                Dispositivos vinculados ({{ totalDevices(drawer.data) }})
                            </h6>
                            <ul class="list-group list-group-flush small">
                                <li v-for="dev in flatDevices(drawer.data)" :key="dev.id"
                                    class="list-group-item px-0 py-1 d-flex justify-content-between">
                                    <span>
                                        <i class="fa fa-mobile-alt me-1 text-info"></i>
                                        {{ dev.name }}
                                        <span class="text-muted">· {{ dev.os }}</span>
                                    </span>
                                    <span class="badge" :class="dev.status === 'online' ? 'bg-success' : 'bg-secondary'">
                                        {{ dev.status }}
                                    </span>
                                </li>
                                <li v-if="totalDevices(drawer.data) === 0" class="list-group-item px-0 py-1 text-muted">
                                    Sin dispositivos.
                                </li>
                            </ul>
                        </section>

                        <section>
                            <h6 class="text-uppercase small text-muted">Últimas alertas</h6>
                            <ul class="list-group list-group-flush small">
                                <li v-for="alert in drawer.data.alerts" :key="alert.id"
                                    class="list-group-item px-0 py-1">
                                    <span class="badge bg-warning-subtle text-warning me-2">{{ alert.type }}</span>
                                    <span>{{ alert.detail || '—' }}</span>
                                    <small class="text-muted ms-2">{{ formatDate(alert.created_at) }}</small>
                                </li>
                                <li v-if="!drawer.data.alerts?.length" class="list-group-item px-0 py-1 text-muted">
                                    Sin alertas recientes.
                                </li>
                            </ul>
                        </section>
                    </template>
                </div>
                <footer v-if="drawer.data" class="mf-drawer-footer mf-cliente-acciones">
                    <div class="d-flex flex-wrap gap-2 justify-content-end">
                        <button v-if="drawer.data.status !== 'active'" class="btn btn-success btn-sm"
                            :disabled="acting" @click="doAction('activate')">
                            <i class="fa fa-play me-1"></i> Activar
                        </button>
                        <button v-if="drawer.data.status === 'active'" class="btn btn-warning btn-sm"
                            :disabled="acting" @click="doAction('suspend')">
                            <i class="fa fa-pause me-1"></i> Suspender
                        </button>
                        <button v-if="drawer.data.status !== 'cancelled'" class="btn btn-danger btn-sm"
                            :disabled="acting" @click="doAction('cancel')">
                            <i class="fa fa-times me-1"></i> Cancelar
                        </button>
                    </div>
                </footer>
            </aside>
        </div>

        <!-- Modal cambiar plan -->
        <div v-if="changePlan.open" class="mf-modal-overlay" @click.self="changePlan.open = false">
            <div class="mf-modal-panel" style="max-width:480px;">
                <div class="mf-modal-header">
                    <h5 class="m-0">Cambiar plan</h5>
                    <button class="btn-close" @click="changePlan.open = false"></button>
                </div>
                <div class="mf-modal-body">
                    <p class="small text-muted">
                        Cliente: <strong>{{ changePlan.user_name }}</strong><br>
                        Plan actual: <strong>{{ changePlan.current_plan }}</strong>
                    </p>
                    <label class="form-label">Nuevo plan</label>
                    <select v-model.number="changePlan.plan_id" class="form-select">
                        <option :value="null">Selecciona…</option>
                        <option v-for="p in plans" :key="p.id" :value="p.id">
                            {{ p.name }} — ${{ Number(p.price_monthly).toFixed(0) }}/mes
                        </option>
                    </select>
                </div>
                <div class="mf-modal-footer">
                    <button class="btn btn-outline-secondary" @click="changePlan.open = false">Cancelar</button>
                    <button class="btn btn-primary" :disabled="!changePlan.plan_id || acting" @click="doChangePlan">
                        <i class="fa fa-check me-1"></i> Confirmar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { startTour, shouldShowTour } from './MegaFamiliaTour.js';

const STATUS_LABEL = { active: 'Activo', suspended: 'Suspendido', cancelled: 'Cancelado' };
const STATUS_BADGE = {
    active: 'bg-success',
    suspended: 'bg-warning text-dark',
    cancelled: 'bg-secondary',
};

export default {
    name: 'MegaFamiliaClientes',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: false,
            rows: [],
            plans: [],
            pagination: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            filters: { search: '', plan_id: '', status: '' },
            drawer: { open: false, loading: false, data: null },
            acting: false,
            changePlan: { open: false, plan_id: null, account_id: null, user_name: '', current_plan: '' },
        };
    },
    computed: {
        pageRange() {
            const cur = this.pagination.current_page;
            const last = this.pagination.last_page;
            const lo = Math.max(1, cur - 2);
            const hi = Math.min(last, cur + 2);
            const arr = [];
            for (let i = lo; i <= hi; i++) arr.push(i);
            return arr;
        },
    },
    mounted() {
        this.fetchPlans();
        this.fetch();
        if (shouldShowTour('clientes')) setTimeout(() => startTour('clientes'), 1200);
    },
    methods: {
        launchTour() { startTour('clientes'); },
        async fetch(page = 1) {
            this.loading = true;
            try {
                const params = { page, per_page: 20 };
                if (this.filters.search)  params.search  = this.filters.search;
                if (this.filters.plan_id) params.plan_id = this.filters.plan_id;
                if (this.filters.status)  params.status  = this.filters.status;
                const { data } = await axios.get(`${this.baseUrl}/clientes/data`, { params });
                this.rows = data.data || [];
                this.pagination = {
                    current_page: data.current_page,
                    last_page: data.last_page,
                    from: data.from || 0,
                    to: data.to || 0,
                    total: data.total || 0,
                };
            } catch (e) {
                console.error('[MF/Clientes] fetch', e);
            } finally {
                this.loading = false;
            }
        },
        async fetchPlans() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/planes/data`);
                this.plans = (data.plans || []).filter(p => p.active);
            } catch (e) { /* no-op */ }
        },
        async openDrawer(row) {
            this.drawer = { open: true, loading: true, data: null };
            try {
                const { data } = await axios.get(`${this.baseUrl}/clientes/${row.id}`);
                this.drawer.data = data;
            } catch (e) {
                alert(e.response?.data?.message || e.message);
                this.drawer.open = false;
            } finally {
                this.drawer.loading = false;
            }
        },
        closeDrawer() { this.drawer = { open: false, loading: false, data: null }; },
        async doAction(action) {
            const id = this.drawer.data?.id;
            if (!id) return;
            const labels = { activate: 'activar', suspend: 'suspender', cancel: 'cancelar' };
            if (!confirm(`¿Confirmas ${labels[action]} esta cuenta?`)) return;
            this.acting = true;
            try {
                await axios.post(`${this.baseUrl}/clientes/${id}/${action}`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                await this.openDrawer({ id });
                await this.fetch(this.pagination.current_page);
            } catch (e) {
                alert(e.response?.data?.message || e.message);
            } finally {
                this.acting = false;
            }
        },
        openChangePlan(account) {
            this.changePlan = {
                open: true,
                plan_id: account.plan_id,
                account_id: account.id,
                user_name: account.user?.name || '',
                current_plan: account.plan?.name || '',
            };
        },
        async doChangePlan() {
            if (!this.changePlan.plan_id) return;
            this.acting = true;
            try {
                await axios.post(
                    `${this.baseUrl}/clientes/${this.changePlan.account_id}/change-plan`,
                    { plan_id: this.changePlan.plan_id },
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } },
                );
                this.changePlan.open = false;
                await this.openDrawer({ id: this.changePlan.account_id });
                await this.fetch(this.pagination.current_page);
            } catch (e) {
                alert(e.response?.data?.message || e.message);
            } finally {
                this.acting = false;
            }
        },
        statusLabel(s) { return STATUS_LABEL[s] || s; },
        statusBadge(s) { return STATUS_BADGE[s] || 'bg-light text-dark'; },
        flatDevices(account) {
            return (account.profiles || []).flatMap(p => p.devices || []);
        },
        totalDevices(account) { return this.flatDevices(account).length; },
        formatDate(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: '2-digit' });
        },
    },
};
</script>

<style scoped>
.megafamilia-clientes tbody tr { cursor: pointer; }

.mf-drawer-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9990;
}
.mf-drawer {
    position: fixed; top: 0; right: 0; bottom: 0;
    width: min(520px, 95vw); background: #fff; box-shadow: -4px 0 16px rgba(0,0,0,0.1);
    display: flex; flex-direction: column;
    animation: mf-slide-in 0.18s ease;
}
@keyframes mf-slide-in {
    from { transform: translateX(40px); opacity: 0; }
    to { transform: none; opacity: 1; }
}
.mf-drawer-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef;
}
.mf-drawer-body { flex: 1; overflow-y: auto; padding: 1rem 1.25rem; }
.mf-drawer-footer { padding: 0.75rem 1.25rem; border-top: 1px solid #e9ecef; }

.mf-modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center; z-index: 9999;
}
.mf-modal-panel {
    background: #fff; border-radius: 8px; width: min(640px, 92vw);
    max-height: 90vh; overflow: hidden; display: flex; flex-direction: column;
}
.mf-modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef;
}
.mf-modal-body { padding: 1rem 1.25rem; overflow-y: auto; }
.mf-modal-footer {
    padding: 0.75rem 1.25rem; border-top: 1px solid #e9ecef;
    display: flex; justify-content: flex-end; gap: 0.5rem;
}

.btn-tour-help {
    position: fixed; bottom: 30px; right: 30px; z-index: 999;
    width: 48px; height: 48px; border-radius: 50%;
    background: #0d6efd; color: white; border: none;
    font-size: 20px; box-shadow: 0 4px 12px rgba(13,110,253,0.4);
    cursor: pointer; transition: transform 0.2s;
}
.btn-tour-help:hover { transform: scale(1.1); }
</style>
