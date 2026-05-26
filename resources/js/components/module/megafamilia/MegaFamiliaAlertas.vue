<template>
    <div class="megafamilia-alertas mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-bell me-2 text-primary"></i>
                Alertas MegaFamilia
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch()">
                    <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                </button>
                <button id="btn-mark-all-read" class="btn btn-sm btn-outline-primary" :disabled="acting" @click="markAllRead">
                    <i class="fa fa-check-double me-1"></i> Marcar todas leídas
                </button>
            </div>
        </div>

        <!-- KPIs -->
        <div class="row g-2 mb-3 mf-alert-kpis">
            <div class="col-md-4 col-6">
                <div class="card kpi-card">
                    <div class="card-body p-3">
                        <div class="text-muted small">Total hoy</div>
                        <div class="kpi-num">{{ kpis.today }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="card kpi-card kpi-warning">
                    <div class="card-body p-3">
                        <div class="text-muted small">Sin leer</div>
                        <div class="kpi-num text-warning">{{ kpis.unread }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="card kpi-card kpi-danger">
                    <div class="card-body p-3">
                        <div class="text-muted small">Críticas sin leer</div>
                        <div class="kpi-num text-danger">{{ kpis.critical }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Tipo</label>
                        <select v-model="filters.type" class="form-select form-select-sm" @change="fetch()">
                            <option value="">Todos</option>
                            <option value="uninstall_attempt">Intento desinstalación</option>
                            <option value="geofence_exit">Salida zona</option>
                            <option value="low_battery">Batería baja</option>
                            <option value="sos">SOS</option>
                            <option value="app_blocked">App bloqueada</option>
                            <option value="web_blocked">Web bloqueada</option>
                            <option value="blocked_content">Contenido bloqueado</option>
                            <option value="device_offline">Dispositivo offline</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Estado</label>
                        <select v-model="filters.unread" class="form-select form-select-sm" @change="fetch()">
                            <option value="">Todas</option>
                            <option value="true">Sin leer</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Perfil</label>
                        <select v-model="filters.profile_id" class="form-select form-select-sm" @change="fetch()">
                            <option value="">Todos</option>
                            <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Desde</label>
                        <input v-model="filters.fecha_desde" type="date" class="form-control form-control-sm" @change="fetch()" />
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Hasta</label>
                        <input v-model="filters.fecha_hasta" type="date" class="form-control form-control-sm" @change="fetch()" />
                    </div>
                </div>
                <small class="text-muted d-block mt-1"><i class="fa fa-sync me-1"></i>Auto-actualiza cada 60s</small>
            </div>
        </div>

        <!-- Lista -->
        <div v-if="loading && rows.length === 0" class="text-center py-5 text-muted">Cargando…</div>
        <div v-else-if="rows.length === 0" class="text-center py-5 text-muted">
            <i class="fa fa-bell-slash fa-3x mb-3 d-block"></i>
            Sin alertas que cumplan el filtro.
        </div>
        <div v-else>
            <div
                v-for="row in rows"
                :key="row.id"
                class="alert-card"
                :class="rowClass(row)"
                @click="openDetail(row)">
                <div class="alert-icon">{{ typeIcon(row.type) }}</div>
                <div class="alert-content">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>{{ typeLabel(row.type) }}</strong>
                            <span class="text-muted ms-2 small">
                                <i class="fa fa-child me-1"></i>{{ row.profile?.name || '—' }}
                                <span v-if="row.device?.name">· <i class="fa fa-mobile-alt me-1"></i>{{ row.device.name }}</span>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span v-if="!row.read_at" class="badge bg-danger">Sin leer</span>
                            <small class="text-muted">{{ relativeTime(row.created_at) }}</small>
                        </div>
                    </div>
                    <p class="mb-0 mt-1 small">{{ row.detail || 'Sin detalle' }}</p>
                </div>
            </div>
        </div>

        <!-- Paginación -->
        <div v-if="pagination.last_page > 1" class="d-flex justify-content-center mt-3">
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

        <button @click="launchTour" class="btn-tour-help" title="Tour guiado">
            <i class="fas fa-question-circle"></i>
        </button>

        <!-- Modal detalle -->
        <div v-if="detail.open" class="mf-modal-overlay" @click.self="detail.open = false">
            <div class="mf-modal-panel" style="max-width:560px;">
                <div class="mf-modal-header">
                    <div>
                        <h5 class="m-0">
                            {{ typeIcon(detail.alert?.type) }} {{ typeLabel(detail.alert?.type) }}
                        </h5>
                        <small class="text-muted">#{{ detail.alert?.id }} · {{ formatDate(detail.alert?.created_at) }}</small>
                    </div>
                    <button class="btn-close" @click="detail.open = false"></button>
                </div>
                <div class="mf-modal-body" v-if="detail.alert">
                    <dl class="row small mb-3">
                        <dt class="col-sm-4">Perfil</dt>
                        <dd class="col-sm-8">{{ detail.alert.profile?.name || '—' }}</dd>
                        <dt class="col-sm-4">Dispositivo</dt>
                        <dd class="col-sm-8">{{ detail.alert.device?.name || '—' }} <small class="text-muted">{{ detail.alert.device?.os }}</small></dd>
                        <dt class="col-sm-4">Cuenta</dt>
                        <dd class="col-sm-8">{{ detail.alert.account?.user?.name || '—' }}</dd>
                        <dt class="col-sm-4">Estado</dt>
                        <dd class="col-sm-8">
                            <span class="badge" :class="detail.alert.read_at ? 'bg-success' : 'bg-danger'">
                                {{ detail.alert.read_at ? 'Leída' : 'Sin leer' }}
                            </span>
                        </dd>
                    </dl>
                    <h6 class="text-uppercase small text-muted">Detalle</h6>
                    <p class="small">{{ detail.alert.detail || 'Sin detalle.' }}</p>
                </div>
                <footer v-if="detail.alert" class="mf-modal-footer">
                    <div class="d-flex flex-wrap gap-2 justify-content-end w-100">
                        <button
                            v-if="isCritical(detail.alert.type)"
                            class="btn btn-sm btn-warning"
                            :disabled="acting"
                            @click="notifyParent">
                            <i class="fa fa-paper-plane me-1"></i> Enviar push al padre
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</template>

<script>
import { startTour, shouldShowTour } from './MegaFamiliaTour.js';

const TYPE_LABELS = {
    uninstall_attempt: 'Intento de desinstalación',
    geofence_exit: 'Salida de zona',
    blocked_content: 'Contenido bloqueado',
    low_battery: 'Batería baja',
    device_offline: 'Dispositivo offline',
    sos: 'SOS',
    app_blocked: 'App bloqueada',
    web_blocked: 'Web bloqueada',
};
const TYPE_ICONS = {
    uninstall_attempt: '🔴',
    geofence_exit: '📍',
    blocked_content: '🚫',
    low_battery: '🔋',
    device_offline: '📵',
    sos: '🆘',
    app_blocked: '📵',
    web_blocked: '🌐',
};
const CRITICAL_TYPES = ['uninstall_attempt', 'sos'];

export default {
    name: 'MegaFamiliaAlertas',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: false,
            acting: false,
            rows: [],
            profiles: [],
            kpis: { today: 0, unread: 0, critical: 0 },
            pagination: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            filters: { type: '', unread: '', profile_id: '', fecha_desde: '', fecha_hasta: '' },
            detail: { open: false, alert: null },
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
        this.fetchProfiles();
        this.fetch();
        this.pollTimer = setInterval(() => this.fetch(this.pagination.current_page, true), 60000);
        if (shouldShowTour('alertas')) setTimeout(() => startTour('alertas'), 1200);
    },
    beforeUnmount() {
        if (this.pollTimer) clearInterval(this.pollTimer);
    },
    methods: {
        launchTour() { startTour('alertas'); },
        async fetch(page = 1, silent = false) {
            if (!silent) this.loading = true;
            try {
                const params = { page };
                if (this.filters.type)        params.type        = this.filters.type;
                if (this.filters.unread)      params.unread      = this.filters.unread;
                if (this.filters.profile_id)  params.profile_id  = this.filters.profile_id;
                if (this.filters.fecha_desde) params.fecha_desde = this.filters.fecha_desde;
                if (this.filters.fecha_hasta) params.fecha_hasta = this.filters.fecha_hasta;
                const { data } = await axios.get(`${this.baseUrl}/alertas/data`, { params });
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
                if (!silent) console.error('[MF/Alertas]', e);
            } finally {
                this.loading = false;
            }
        },
        async fetchProfiles() {
            try {
                const { data } = await axios.get(`${this.baseUrl}/perfiles/data`);
                this.profiles = data.profiles || [];
            } catch (e) { /* no-op */ }
        },
        async openDetail(row) {
            // El backend marca como leída automáticamente en show.
            this.detail = { open: true, alert: { ...row, read_at: row.read_at || new Date().toISOString() } };
            try {
                const { data } = await axios.get(`${this.baseUrl}/alertas/${row.id}`);
                this.detail.alert = data;
                // refrescar KPIs (la cuenta de unread bajó)
                await this.fetch(this.pagination.current_page, true);
            } catch (e) { /* no-op */ }
        },
        async markAllRead() {
            if (!confirm('¿Marcar TODAS las alertas como leídas?')) return;
            this.acting = true;
            try {
                await axios.post(`${this.baseUrl}/alertas/all-read`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                await this.fetch(this.pagination.current_page);
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        async notifyParent() {
            if (!this.detail.alert) return;
            this.acting = true;
            try {
                const { data } = await axios.post(
                    `${this.baseUrl}/alertas/${this.detail.alert.id}/notify-parent`, {},
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } },
                );
                alert(data.success
                    ? 'Push enviado a los dispositivos de la cuenta.'
                    : (data.message || 'No se pudo enviar el push.'));
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        rowClass(row) {
            const classes = ['alert-card-base'];
            if (!row.read_at) classes.push('alert-unread');
            if (this.isCritical(row.type)) classes.push('alert-critical', 'mf-alert-critica');
            return classes.join(' ');
        },
        isCritical(type) { return CRITICAL_TYPES.includes(type); },
        typeLabel(t) { return TYPE_LABELS[t] || t; },
        typeIcon(t) { return TYPE_ICONS[t] || '⚠️'; },
        relativeTime(value) {
            if (!value) return '';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            const diff = (Date.now() - d.getTime()) / 1000;
            if (diff < 60)    return 'hace unos segundos';
            if (diff < 3600)  return `hace ${Math.floor(diff / 60)} min`;
            if (diff < 86400) return `hace ${Math.floor(diff / 3600)} h`;
            return `hace ${Math.floor(diff / 86400)} d`;
        },
        formatDate(value) {
            if (!value) return '';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
    },
};
</script>

<style scoped>
.kpi-card { border: 1px solid #e9ecef; }
.kpi-card.kpi-warning { border-left: 4px solid #ffc107; }
.kpi-card.kpi-danger { border-left: 4px solid #dc3545; }
.kpi-num { font-size: 1.75rem; font-weight: 700; line-height: 1; margin-top: 4px; }

.alert-card {
    display: flex; align-items: flex-start; gap: 0.75rem;
    padding: 0.75rem 1rem; background: #fff;
    border: 1px solid #e9ecef; border-radius: 6px;
    margin-bottom: 0.5rem; cursor: pointer;
    transition: transform 0.1s, box-shadow 0.1s;
}
.alert-card:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.alert-card.alert-unread { background: #f8f9ff; border-left: 4px solid #0d6efd; }
.alert-card.alert-critical { border-left: 4px solid #dc3545; background: #fff5f5; }
.alert-card.alert-critical.alert-unread { background: #ffe5e5; }

.alert-icon { font-size: 1.5rem; line-height: 1; }
.alert-content { flex: 1; min-width: 0; }

.mf-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.mf-modal-panel { background: #fff; border-radius: 8px; width: min(640px, 92vw); max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
.mf-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; }
.mf-modal-body { padding: 1rem 1.25rem; overflow-y: auto; flex: 1; }
.mf-modal-footer { padding: 0.75rem 1.25rem; border-top: 1px solid #e9ecef; }

.btn-tour-help {
    position: fixed; bottom: 30px; right: 30px; z-index: 999;
    width: 48px; height: 48px; border-radius: 50%;
    background: #0d6efd; color: white; border: none;
    font-size: 20px; box-shadow: 0 4px 12px rgba(13,110,253,0.4);
    cursor: pointer; transition: transform 0.2s;
}
.btn-tour-help:hover { transform: scale(1.1); }
</style>
