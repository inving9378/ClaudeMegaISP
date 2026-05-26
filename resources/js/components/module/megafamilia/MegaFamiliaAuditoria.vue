<template>
    <div class="megafamilia-auditoria mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-clipboard-list me-2 text-primary"></i>
                Auditoría MegaFamilia
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch(pagination.current_page)">
                    <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                </button>
                <button class="btn btn-sm btn-outline-primary" @click="exportCsv">
                    <i class="fa fa-file-csv me-1"></i> Exportar CSV
                </button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="row g-2 mb-3">
            <div class="col-md-4 col-6">
                <div class="card kpi-card kpi-primary">
                    <div class="card-body p-3">
                        <div class="text-muted small">Eventos hoy</div>
                        <div class="kpi-num">{{ kpis.today }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="card kpi-card kpi-info">
                    <div class="card-body p-3">
                        <div class="text-muted small">Eventos esta semana</div>
                        <div class="kpi-num">{{ kpis.this_week }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-12">
                <div class="card kpi-card kpi-danger">
                    <div class="card-body p-3">
                        <div class="text-muted small">Acciones críticas (semana)</div>
                        <div class="kpi-num text-danger">{{ kpis.critical }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Acción</label>
                        <select v-model="filters.action" class="form-select form-select-sm" @change="fetch(1)">
                            <option value="">Todas</option>
                            <option v-for="a in actions" :key="a" :value="a">{{ a }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Cuenta ID</label>
                        <input v-model="filters.account_id" type="number" class="form-control form-control-sm"
                               placeholder="—" @keyup.enter="fetch(1)" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Perfil ID</label>
                        <input v-model="filters.profile_id" type="number" class="form-control form-control-sm"
                               placeholder="—" @keyup.enter="fetch(1)" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Dispositivo ID</label>
                        <input v-model="filters.device_id" type="number" class="form-control form-control-sm"
                               placeholder="—" @keyup.enter="fetch(1)" />
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-sm btn-primary w-100" @click="fetch(1)">
                            <i class="fa fa-filter me-1"></i> Filtrar
                        </button>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Desde</label>
                        <input v-model="filters.date_from" type="date" class="form-control form-control-sm" @change="fetch(1)" />
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Hasta</label>
                        <input v-model="filters.date_to" type="date" class="form-control form-control-sm" @change="fetch(1)" />
                    </div>
                </div>
                <small class="text-muted d-block mt-1"><i class="fa fa-sync me-1"></i>Auto-actualiza cada 2 min</small>
            </div>
        </div>

        <!-- Tabla -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:36px"></th>
                            <th>Fecha/hora</th>
                            <th>Acción</th>
                            <th>Perfil</th>
                            <th>Dispositivo</th>
                            <th>IP / Descripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading && rows.length === 0">
                            <td colspan="6" class="text-center text-muted py-4">Cargando…</td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td colspan="6" class="text-center text-muted py-4">Sin eventos para el filtro.</td>
                        </tr>
                        <template v-for="row in rows" :key="row.id">
                            <tr role="button" @click="toggleExpand(row.id)">
                                <td class="text-center">
                                    <i class="fa" :class="expanded[row.id] ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                                </td>
                                <td class="small">{{ formatDate(row.created_at) }}</td>
                                <td>
                                    <span :class="['badge', actionBadge(row.action)]">
                                        {{ categoryIcon(row.action) }} {{ row.action }}
                                    </span>
                                </td>
                                <td>{{ row.profile?.name || '—' }}</td>
                                <td>{{ row.device?.name || '—' }}</td>
                                <td class="small">
                                    <code v-if="row.ip" class="me-2">{{ row.ip }}</code>
                                    <span class="text-truncate d-inline-block" style="max-width:320px;">{{ shortDetail(row.detail) }}</span>
                                </td>
                            </tr>
                            <tr v-if="expanded[row.id]">
                                <td></td>
                                <td colspan="5" class="bg-light">
                                    <div class="small">
                                        <strong>Cuenta:</strong> #{{ row.account_id }}
                                        <span v-if="row.account?.user"> · {{ row.account.user.name }} ({{ row.account.user.email }})</span>
                                    </div>
                                    <pre class="audit-detail mt-2 mb-0">{{ prettyDetail(row.detail) }}</pre>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div v-if="pagination.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                <small class="text-muted">
                    Mostrando {{ pagination.from }}-{{ pagination.to }} de {{ pagination.total }}
                </small>
                <ul class="pagination pagination-sm m-0">
                    <li class="page-item" :class="{ disabled: pagination.current_page === 1 }">
                        <button class="page-link" @click="fetch(pagination.current_page - 1)">‹</button>
                    </li>
                    <li class="page-item" v-for="p in pageRange" :key="p" :class="{ active: p === pagination.current_page }">
                        <button class="page-link" @click="fetch(p)">{{ p }}</button>
                    </li>
                    <li class="page-item" :class="{ disabled: pagination.current_page === pagination.last_page }">
                        <button class="page-link" @click="fetch(pagination.current_page + 1)">›</button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script>
const CRITICAL  = ['uninstall_attempt', 'sos', 'geofence_exit'];
const WARNING   = ['app_blocked', 'web_blocked', 'device_offline'];
const NORMAL    = ['login', 'logout', 'task_completed', 'request_approved'];
const INFO_KEYS = ['screen_time', 'app_usage', 'location_update', 'device_linked'];

export default {
    name: 'MegaFamiliaAuditoria',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: false,
            rows: [],
            actions: [],
            kpis: { today: 0, this_week: 0, critical: 0 },
            filters: { action: '', account_id: '', profile_id: '', device_id: '', date_from: '', date_to: '' },
            pagination: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            expanded: {},
            pollTimer: null,
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
        this.fetch(1);
        this.pollTimer = setInterval(() => this.fetch(this.pagination.current_page, true), 120000);
    },
    beforeUnmount() {
        if (this.pollTimer) clearInterval(this.pollTimer);
    },
    methods: {
        async fetch(page = 1, silent = false) {
            if (!silent) this.loading = true;
            try {
                const params = { page, per_page: 50 };
                for (const k of Object.keys(this.filters)) {
                    if (this.filters[k] !== '' && this.filters[k] !== null) params[k] = this.filters[k];
                }
                const { data } = await axios.get(`${this.baseUrl}/auditoria/data`, { params });
                this.kpis    = data.kpis || this.kpis;
                this.actions = data.actions_list || [];
                this.rows    = data.events?.data || [];
                this.pagination = {
                    current_page: data.events?.current_page || 1,
                    last_page:    data.events?.last_page || 1,
                    from:         data.events?.from || 0,
                    to:           data.events?.to || 0,
                    total:        data.events?.total || 0,
                };
            } catch (e) {
                if (!silent) console.error('[MF/Auditoria]', e);
            } finally {
                this.loading = false;
            }
        },
        toggleExpand(id) {
            this.expanded[id] = !this.expanded[id];
        },
        exportCsv() {
            const params = new URLSearchParams();
            for (const k of Object.keys(this.filters)) {
                if (this.filters[k] !== '' && this.filters[k] !== null) params.set(k, this.filters[k]);
            }
            window.location.href = `${this.baseUrl}/auditoria/export?${params.toString()}`;
        },
        categoryIcon(action) {
            if (CRITICAL.includes(action)) return '🔴';
            if (WARNING.includes(action))  return '🟠';
            if (NORMAL.includes(action))   return '🟢';
            if (INFO_KEYS.includes(action)) return '🔵';
            return '⚪';
        },
        actionBadge(action) {
            if (CRITICAL.includes(action))  return 'bg-danger';
            if (WARNING.includes(action))   return 'bg-warning text-dark';
            if (NORMAL.includes(action))    return 'bg-success';
            if (INFO_KEYS.includes(action)) return 'bg-info text-dark';
            return 'bg-light text-dark';
        },
        shortDetail(detail) {
            if (!detail) return '';
            const s = String(detail);
            return s.length > 80 ? s.slice(0, 80) + '…' : s;
        },
        prettyDetail(detail) {
            if (!detail) return 'Sin detalle.';
            try {
                const parsed = JSON.parse(detail);
                return JSON.stringify(parsed, null, 2);
            } catch (e) {
                return detail;
            }
        },
        formatDate(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleString('es-MX', {
                day: '2-digit', month: 'short', year: 'numeric',
                hour: '2-digit', minute: '2-digit', second: '2-digit',
            });
        },
    },
};
</script>

<style scoped>
.kpi-card { border: 1px solid #e9ecef; }
.kpi-card.kpi-primary { border-left: 4px solid #0d6efd; }
.kpi-card.kpi-info    { border-left: 4px solid #0dcaf0; }
.kpi-card.kpi-danger  { border-left: 4px solid #dc3545; }
.kpi-num { font-size: 1.75rem; font-weight: 700; line-height: 1; margin-top: 4px; }

.audit-detail {
    background: #fff; border: 1px solid #e9ecef; border-radius: 4px;
    padding: 0.5rem 0.75rem; font-size: 12px; max-height: 240px; overflow-y: auto;
    white-space: pre-wrap; word-break: break-word;
}
</style>
