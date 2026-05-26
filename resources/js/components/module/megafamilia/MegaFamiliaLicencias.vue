<template>
    <div class="megafamilia-licencias mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-key me-2 text-primary"></i>
                Licencias MegaFamilia
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch()">
                    <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                </button>
                <button id="btn-nueva-licencia" class="btn btn-sm btn-primary" @click="openCreate">
                    <i class="fa fa-plus me-1"></i> Nueva Licencia
                </button>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="row g-2 mb-3 mf-lic-kpis">
            <div class="col-md-3 col-6">
                <div class="card kpi-card">
                    <div class="card-body p-3">
                        <div class="text-muted small">Total licencias</div>
                        <div class="kpi-num">{{ kpis.total }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-success">
                    <div class="card-body p-3">
                        <div class="text-muted small">Activas</div>
                        <div class="kpi-num text-success">{{ kpis.active }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-warning">
                    <div class="card-body p-3">
                        <div class="text-muted small">Por vencer &lt;30d</div>
                        <div class="kpi-num text-warning">{{ kpis.expiring }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card kpi-card kpi-danger">
                    <div class="card-body p-3">
                        <div class="text-muted small">Expiradas</div>
                        <div class="kpi-num text-danger">{{ kpis.expired }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Estado</label>
                        <select v-model="filters.status" class="form-select form-select-sm" @change="fetch(1)">
                            <option value="">Todos</option>
                            <option value="active">Activa</option>
                            <option value="suspended">Suspendida</option>
                            <option value="expired">Expirada</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Plan</label>
                        <select v-model="filters.plan_id" class="form-select form-select-sm" @change="fetch(1)">
                            <option value="">Todos</option>
                            <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Vence desde</label>
                        <input v-model="filters.from" type="date" class="form-control form-control-sm" @change="fetch(1)" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Vence hasta</label>
                        <input v-model="filters.to" type="date" class="form-control form-control-sm" @change="fetch(1)" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Plan</th>
                            <th>Inicio</th>
                            <th>Vencimiento</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Dispositivos</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading && rows.length === 0">
                            <td colspan="8" class="text-center py-4 text-muted">Cargando…</td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td colspan="8" class="text-center py-4 text-muted">Sin licencias.</td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id" :class="['mf-lic-vencer', rowClass(row)]">
                            <td class="text-muted">#{{ row.id }}</td>
                            <td>
                                <strong>{{ row.account?.user?.name || '—' }}</strong>
                                <div class="text-muted small">{{ row.account?.user?.email }}</div>
                            </td>
                            <td><span class="badge bg-light text-dark">{{ row.plan?.name || '—' }}</span></td>
                            <td class="small">{{ formatDate(row.activated_at) }}</td>
                            <td class="small">
                                {{ formatDate(row.expires_at) }}
                                <span v-if="daysToExpire(row.expires_at) !== null" class="ms-1" :class="expireTextClass(row)">
                                    ({{ expireText(row) }})
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="badge" :class="statusBadge(row.status)">{{ statusLabel(row.status) }}</span>
                            </td>
                            <td class="text-center small">
                                {{ row.devices_used || 0 }} / {{ row.plan?.max_devices || '∞' }}
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-success" title="Renovar" @click="openRenew(row)">
                                        <i class="fa fa-redo"></i>
                                    </button>
                                    <button
                                        v-if="row.status === 'active'"
                                        class="btn btn-outline-warning"
                                        title="Suspender"
                                        @click="doSuspend(row)">
                                        <i class="fa fa-pause"></i>
                                    </button>
                                    <button
                                        v-else-if="row.status === 'suspended'"
                                        class="btn btn-outline-primary"
                                        title="Reactivar"
                                        @click="doReactivate(row)">
                                        <i class="fa fa-play"></i>
                                    </button>
                                </div>
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

        <!-- Modal: nueva licencia -->
        <div v-if="form.open" class="mf-modal-overlay" @click.self="form.open = false">
            <div class="mf-modal-panel" style="max-width:560px;">
                <div class="mf-modal-header">
                    <h5 class="m-0">Nueva licencia</h5>
                    <button class="btn-close" @click="form.open = false"></button>
                </div>
                <div class="mf-modal-body">
                    <div class="mb-3">
                        <label class="form-label">Buscar usuario / cuenta</label>
                        <input
                            v-model="form.searchAcc"
                            class="form-control"
                            placeholder="Nombre o email…"
                            @input="searchAccounts" />
                        <div v-if="form.accountResults.length" class="border rounded mt-1 search-results">
                            <button
                                v-for="acc in form.accountResults"
                                :key="acc.id"
                                type="button"
                                class="dropdown-item d-flex justify-content-between"
                                @click="selectAccount(acc)">
                                <span>{{ acc.user?.name || '—' }} · {{ acc.user?.email }}</span>
                                <small class="text-muted">#{{ acc.id }}</small>
                            </button>
                        </div>
                        <div v-if="form.account" class="alert alert-info small mt-2 mb-0 py-2">
                            <i class="fa fa-check me-1"></i>
                            Cuenta seleccionada: <strong>{{ form.account.user?.name }}</strong> (#{{ form.account.id }})
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">Plan</label>
                            <select v-model.number="form.plan_id" class="form-select">
                                <option :value="null">Selecciona…</option>
                                <option v-for="p in plans" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Inicio</label>
                            <input v-model="form.start_at" type="date" class="form-control" />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Duración</label>
                            <select v-model.number="form.months" class="form-select">
                                <option :value="1">1 mes</option>
                                <option :value="6">6 meses</option>
                                <option :value="12">12 meses</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mf-modal-footer">
                    <button class="btn btn-outline-secondary" @click="form.open = false">Cancelar</button>
                    <button class="btn btn-primary" :disabled="!canCreate || saving" @click="createLicense">
                        <i class="fa fa-save me-1"></i> {{ saving ? 'Creando…' : 'Crear licencia' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: renovar -->
        <div v-if="renew.open" class="mf-modal-overlay" @click.self="renew.open = false">
            <div class="mf-modal-panel" style="max-width:440px;">
                <div class="mf-modal-header">
                    <h5 class="m-0">Renovar licencia</h5>
                    <button class="btn-close" @click="renew.open = false"></button>
                </div>
                <div class="mf-modal-body">
                    <p class="small text-muted mb-3">
                        Licencia <strong>#{{ renew.license_id }}</strong> de
                        <strong>{{ renew.user_name }}</strong>.
                        Vence actualmente el <strong>{{ formatDate(renew.expires_at) }}</strong>.
                    </p>
                    <label class="form-label">Duración a sumar</label>
                    <select v-model.number="renew.months" class="form-select">
                        <option :value="1">1 mes</option>
                        <option :value="6">6 meses</option>
                        <option :value="12">12 meses</option>
                    </select>
                    <p class="small text-info mt-3 mb-0">
                        <i class="fa fa-info-circle me-1"></i>
                        Precio referencia: <strong>${{ renew.price.toFixed(0) }} MXN</strong>
                    </p>
                </div>
                <div class="mf-modal-footer">
                    <button class="btn btn-outline-secondary" @click="renew.open = false">Cancelar</button>
                    <button class="btn btn-success" :disabled="saving" @click="doRenew">
                        <i class="fa fa-redo me-1"></i> Renovar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { startTour, shouldShowTour } from './MegaFamiliaTour.js';

const STATUS_LABEL = { active: 'Activa', suspended: 'Suspendida', expired: 'Expirada' };
const STATUS_BADGE = {
    active: 'bg-success',
    suspended: 'bg-warning text-dark',
    expired: 'bg-danger',
};

export default {
    name: 'MegaFamiliaLicencias',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: false,
            saving: false,
            rows: [],
            plans: [],
            kpis: { total: 0, active: 0, expiring: 0, expired: 0 },
            pagination: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            filters: { status: '', plan_id: '', from: '', to: '' },
            form: {
                open: false, searchAcc: '', accountResults: [], account: null,
                plan_id: null, start_at: '', months: 1,
            },
            renew: {
                open: false, license_id: null, user_name: '', expires_at: null,
                plan_id: null, months: 1, price: 0,
            },
            searchTimer: null,
        };
    },
    computed: {
        canCreate() {
            return !!(this.form.account && this.form.plan_id);
        },
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
        if (shouldShowTour('licencias')) setTimeout(() => startTour('licencias'), 1200);
    },
    methods: {
        launchTour() { startTour('licencias'); },
        async fetch(page = 1) {
            this.loading = true;
            try {
                const params = { page };
                if (this.filters.status)  params.status  = this.filters.status;
                if (this.filters.plan_id) params.plan_id = this.filters.plan_id;
                if (this.filters.from)    params.from    = this.filters.from;
                if (this.filters.to)      params.to      = this.filters.to;
                const { data } = await axios.get(`${this.baseUrl}/licencias/data`, { params });
                this.kpis = data.kpis || this.kpis;
                this.rows = data.list?.data || [];
                this.pagination = {
                    current_page: data.list?.current_page || 1,
                    last_page: data.list?.last_page || 1,
                    from: data.list?.from || 0,
                    to: data.list?.to || 0,
                    total: data.list?.total || 0,
                };
            } catch (e) {
                console.error('[MF/Licencias] fetch', e);
            } finally {
                this.loading = false;
            }
        },
        async fetchPlans() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/licencias/plans`);
                this.plans = data.plans || [];
            } catch (e) { /* no-op */ }
        },
        openCreate() {
            this.form = {
                open: true, searchAcc: '', accountResults: [], account: null,
                plan_id: null, start_at: new Date().toISOString().slice(0, 10), months: 1,
            };
        },
        searchAccounts() {
            clearTimeout(this.searchTimer);
            const q = this.form.searchAcc.trim();
            if (q.length < 2) { this.form.accountResults = []; return; }
            this.searchTimer = setTimeout(async () => {
                try {
                    const { data } = await axios.get(`${this.baseUrl}/licencias/accounts`, { params: { search: q } });
                    this.form.accountResults = data.accounts || [];
                } catch (e) { /* no-op */ }
            }, 300);
        },
        selectAccount(acc) {
            this.form.account = acc;
            this.form.accountResults = [];
            this.form.searchAcc = '';
        },
        async createLicense() {
            this.saving = true;
            try {
                const payload = {
                    account_id: this.form.account.id,
                    plan_id: this.form.plan_id,
                    start_at: this.form.start_at,
                    months: this.form.months,
                };
                await axios.post(`${this.baseUrl}/licencias`, payload, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                this.form.open = false;
                await this.fetch(1);
            } catch (e) {
                alert(e.response?.data?.message || e.message);
            } finally {
                this.saving = false;
            }
        },
        openRenew(row) {
            this.renew = {
                open: true,
                license_id: row.id,
                user_name: row.account?.user?.name || '—',
                expires_at: row.expires_at,
                plan_id: row.plan_id,
                months: 1,
                price: Number(row.plan?.price_monthly || 0),
            };
        },
        async doRenew() {
            this.saving = true;
            try {
                await axios.post(
                    `${this.baseUrl}/licencias/${this.renew.license_id}/renew`,
                    { months: this.renew.months },
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } },
                );
                this.renew.open = false;
                await this.fetch(this.pagination.current_page);
            } catch (e) {
                alert(e.response?.data?.message || e.message);
            } finally {
                this.saving = false;
            }
        },
        async doSuspend(row) {
            const reason = prompt('Motivo de suspensión (opcional):') || '';
            try {
                await axios.post(
                    `${this.baseUrl}/licencias/${row.id}/suspend`,
                    { reason },
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } },
                );
                await this.fetch(this.pagination.current_page);
            } catch (e) { alert(e.response?.data?.message || e.message); }
        },
        async doReactivate(row) {
            if (!confirm('¿Reactivar esta licencia?')) return;
            try {
                await axios.post(
                    `${this.baseUrl}/licencias/${row.id}/reactivate`, {},
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } },
                );
                await this.fetch(this.pagination.current_page);
            } catch (e) { alert(e.response?.data?.message || e.message); }
        },
        statusLabel(s) { return STATUS_LABEL[s] || s; },
        statusBadge(s) { return STATUS_BADGE[s] || 'bg-light text-dark'; },
        daysToExpire(value) {
            if (!value) return null;
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return null;
            return Math.ceil((d.getTime() - Date.now()) / (24 * 60 * 60 * 1000));
        },
        rowClass(row) {
            const days = this.daysToExpire(row.expires_at);
            if (row.status === 'expired' || (days !== null && days < 0)) return 'row-expired';
            if (days !== null && days <= 30 && row.status === 'active') return 'row-expiring';
            return '';
        },
        expireText(row) {
            const days = this.daysToExpire(row.expires_at);
            if (days === null) return '';
            if (days < 0) return `expirada hace ${Math.abs(days)}d`;
            if (days === 0) return 'hoy';
            return `${days}d`;
        },
        expireTextClass(row) {
            const days = this.daysToExpire(row.expires_at);
            if (days === null) return 'text-muted';
            if (days < 0) return 'text-danger fw-bold';
            if (days <= 30) return 'text-warning fw-bold';
            return 'text-muted';
        },
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
.kpi-card { border: 1px solid #e9ecef; }
.kpi-card.kpi-success { border-left: 4px solid #198754; }
.kpi-card.kpi-warning { border-left: 4px solid #ffc107; }
.kpi-card.kpi-danger { border-left: 4px solid #dc3545; }
.kpi-num { font-size: 1.75rem; font-weight: 700; line-height: 1; margin-top: 4px; }

.row-expiring { background: #fff7e0 !important; }
.row-expired { background: #ffe5e5 !important; }

.search-results { max-height: 200px; overflow-y: auto; background: #fff; }
.search-results .dropdown-item { padding: 6px 12px; cursor: pointer; }
.search-results .dropdown-item:hover { background: #f1f3f5; }

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
