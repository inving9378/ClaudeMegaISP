<template>
    <div class="megafamilia-dispositivos mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-mobile-alt me-2 text-primary"></i>
                Dispositivos MegaFamilia
            </h5>
            <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch()">
                <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
            </button>
        </div>

        <!-- KPIs -->
        <div class="row g-2 mb-3 mf-dev-kpis">
            <div class="col-md-4 col-6">
                <div class="card kpi-card">
                    <div class="card-body p-3">
                        <div class="text-muted small">Total dispositivos</div>
                        <div class="kpi-num">{{ kpis.total }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="card kpi-card kpi-success">
                    <div class="card-body p-3">
                        <div class="text-muted small">Online ahora</div>
                        <div class="kpi-num text-success">{{ kpis.online }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="card kpi-card kpi-warning">
                    <div class="card-body p-3">
                        <div class="text-muted small">Sin conexión &gt;7d</div>
                        <div class="kpi-num text-warning">{{ kpis.stale_7d }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Perfil</label>
                        <select v-model="filters.profile_id" class="form-select form-select-sm" @change="fetch(1)">
                            <option value="">Todos</option>
                            <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">SO</label>
                        <select v-model="filters.os" class="form-select form-select-sm" @change="fetch(1)">
                            <option value="">Todos</option>
                            <option value="android">Android</option>
                            <option value="ios">iOS</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Estado</label>
                        <select v-model="filters.status" class="form-select form-select-sm" @change="fetch(1)">
                            <option value="">Todos</option>
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                            <option value="unlinked">Desvinculado</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Grid de dispositivos -->
        <div v-if="loading && rows.length === 0" class="text-center py-5 text-muted">Cargando…</div>
        <div v-else-if="rows.length === 0" class="text-center py-5 text-muted">
            <i class="fa fa-mobile-alt fa-3x mb-3 d-block"></i>
            Sin dispositivos.
        </div>
        <div v-else class="row">
            <div v-for="row in rows" :key="row.id" class="col-md-6 col-lg-4 mb-3">
                <div class="card device-card h-100" role="button" @click="openDetail(row)">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong>{{ row.name }}</strong>
                                <div class="text-muted small">{{ row.model || 'Modelo desconocido' }}</div>
                            </div>
                            <span class="badge" :class="statusBadge(row.status)">{{ row.status }}</span>
                        </div>

                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="badge bg-light text-dark">
                                <i class="fab fa-android me-1" v-if="row.os === 'android'"></i>
                                <i class="fab fa-apple me-1" v-else-if="row.os === 'ios'"></i>
                                {{ row.os }} {{ row.os_version || '' }}
                            </span>
                            <span class="badge bg-light text-dark" v-if="row.app_version">
                                <i class="fa fa-tag me-1"></i> v{{ row.app_version }}
                            </span>
                            <span class="badge bg-info-subtle text-info">
                                <i class="fa fa-child me-1"></i> {{ row.profile?.name || 'Sin perfil' }}
                            </span>
                        </div>

                        <div class="d-flex justify-content-between text-muted small mb-2">
                            <span><i class="fa fa-clock me-1"></i>{{ relativeTime(row.last_seen_at) }}</span>
                        </div>

                        <div v-if="row.battery_level !== null" class="battery-bar mf-dev-battery">
                            <div class="d-flex justify-content-between small">
                                <span><i class="fa fa-battery-half me-1"></i>Batería</span>
                                <span :class="batteryTextClass(row.battery_level)">{{ row.battery_level }}%</span>
                            </div>
                            <div class="progress" style="height:6px;">
                                <div class="progress-bar" :class="batteryBarClass(row.battery_level)"
                                     :style="`width:${row.battery_level}%`"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paginación -->
        <div v-if="pagination.last_page > 1" class="d-flex justify-content-center mt-2">
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

        <!-- Modal detalle con tabs -->
        <div v-if="detail.open" class="mf-modal-overlay" @click.self="closeDetail">
            <div class="mf-modal-panel" style="max-width:720px;">
                <div class="mf-modal-header">
                    <div>
                        <h5 class="m-0">{{ detail.data?.device?.name }}</h5>
                        <small class="text-muted">{{ detail.data?.device?.model }}</small>
                    </div>
                    <button class="btn-close" @click="closeDetail"></button>
                </div>
                <div class="mf-modal-body">
                    <div v-if="detail.loading" class="text-center py-5">
                        <div class="spinner-border text-primary"></div>
                    </div>
                    <template v-else-if="detail.data">
                        <ul class="nav nav-tabs mb-3">
                            <li class="nav-item">
                                <button class="nav-link" :class="{ active: detailTab === 'info' }" @click="detailTab = 'info'">
                                    <i class="fa fa-info-circle me-1"></i> Info
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" :class="{ active: detailTab === 'location' }" @click="detailTab = 'location'">
                                    <i class="fa fa-map-marker-alt me-1"></i> Ubicación
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link" :class="{ active: detailTab === 'history' }" @click="detailTab = 'history'">
                                    <i class="fa fa-history me-1"></i> Historial
                                </button>
                            </li>
                        </ul>

                        <!-- Tab Info -->
                        <div v-show="detailTab === 'info'">
                            <dl class="row small mb-0">
                                <dt class="col-sm-4">Nombre</dt><dd class="col-sm-8">{{ detail.data.device.name }}</dd>
                                <dt class="col-sm-4">Modelo</dt><dd class="col-sm-8">{{ detail.data.device.model || '—' }}</dd>
                                <dt class="col-sm-4">SO</dt><dd class="col-sm-8">{{ detail.data.device.os }} {{ detail.data.device.os_version }}</dd>
                                <dt class="col-sm-4">App versión</dt><dd class="col-sm-8">{{ detail.data.device.app_version || '—' }}</dd>
                                <dt class="col-sm-4">Estado</dt><dd class="col-sm-8">
                                    <span class="badge" :class="statusBadge(detail.data.device.status)">{{ detail.data.device.status }}</span>
                                </dd>
                                <dt class="col-sm-4">Batería</dt><dd class="col-sm-8">
                                    <span v-if="detail.data.device.battery_level !== null">{{ detail.data.device.battery_level }}%</span>
                                    <span v-else class="text-muted">—</span>
                                </dd>
                                <dt class="col-sm-4">Perfil</dt><dd class="col-sm-8">{{ detail.data.device.profile?.name || '—' }}</dd>
                                <dt class="col-sm-4">Cuenta</dt><dd class="col-sm-8">
                                    {{ detail.data.device.account?.user?.name || '—' }}
                                </dd>
                                <dt class="col-sm-4">FCM token</dt><dd class="col-sm-8">
                                    <code v-if="detail.data.device.fcm_token">…{{ detail.data.device.fcm_token.slice(-8) }}</code>
                                    <span v-else class="text-muted">no registrado</span>
                                </dd>
                                <dt class="col-sm-4">Vinculado</dt><dd class="col-sm-8">{{ formatDate(detail.data.device.linked_at) }}</dd>
                                <dt class="col-sm-4">Última conexión</dt><dd class="col-sm-8">{{ formatDate(detail.data.device.last_seen_at) }}</dd>
                            </dl>
                        </div>

                        <!-- Tab Ubicación -->
                        <div v-show="detailTab === 'location'">
                            <div v-if="!detail.data.location" class="text-muted small text-center py-4">
                                <i class="fa fa-map fa-2x mb-2 d-block"></i>
                                Sin ubicación registrada.
                            </div>
                            <div v-else>
                                <div class="alert alert-info small py-2 mb-2">
                                    <strong>Lat:</strong> {{ detail.data.location.lat }} ·
                                    <strong>Lng:</strong> {{ detail.data.location.lng }} ·
                                    <strong>Precisión:</strong> {{ detail.data.location.accuracy || '?' }}m<br>
                                    <small>Registrado: {{ formatDate(detail.data.location.recorded_at) }}</small>
                                </div>
                                <div class="map-frame">
                                    <iframe
                                        :src="mapUrl(detail.data.location)"
                                        width="100%" height="320" frameborder="0" style="border:0"
                                        loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                                </div>
                            </div>
                        </div>

                        <!-- Tab Historial -->
                        <div v-show="detailTab === 'history'">
                            <div v-if="!detail.data.events?.length" class="text-muted small text-center py-4">
                                Sin eventos registrados.
                            </div>
                            <ul v-else class="list-group list-group-flush small">
                                <li v-for="ev in detail.data.events" :key="ev.id" class="list-group-item px-0">
                                    <div class="d-flex justify-content-between">
                                        <span><span class="badge bg-light text-dark me-2">{{ ev.action }}</span>{{ ev.detail }}</span>
                                        <small class="text-muted">{{ relativeTime(ev.created_at) }}</small>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </template>
                </div>
                <footer v-if="detail.data" class="mf-modal-footer mf-dev-acciones">
                    <div class="d-flex flex-wrap gap-2 justify-content-end w-100">
                        <button class="btn btn-sm btn-outline-info" :disabled="acting" @click="doPing">
                            <i class="fa fa-satellite-dish me-1"></i> Ping
                        </button>
                        <button class="btn btn-sm btn-outline-warning" :disabled="acting" @click="doForceLogout">
                            <i class="fa fa-sign-out-alt me-1"></i> Forzar logout
                        </button>
                        <button class="btn btn-sm btn-outline-danger" :disabled="acting" @click="doUnlink">
                            <i class="fa fa-unlink me-1"></i> Desvincular
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</template>

<script>
import { startTour, shouldShowTour } from './MegaFamiliaTour.js';

const STATUS_BADGE = {
    online: 'bg-success',
    offline: 'bg-secondary',
    unlinked: 'bg-danger',
};

export default {
    name: 'MegaFamiliaDispositivos',
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
            kpis: { total: 0, online: 0, stale_7d: 0 },
            pagination: { current_page: 1, last_page: 1, from: 0, to: 0, total: 0 },
            filters: { profile_id: '', os: '', status: '' },
            detail: { open: false, loading: false, data: null },
            detailTab: 'info',
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
        if (shouldShowTour('dispositivos')) setTimeout(() => startTour('dispositivos'), 1200);
    },
    methods: {
        launchTour() { startTour('dispositivos'); },
        async fetch(page = 1) {
            this.loading = true;
            try {
                const params = { page, per_page: 24 };
                if (this.filters.profile_id) params.profile_id = this.filters.profile_id;
                if (this.filters.os)         params.os         = this.filters.os;
                if (this.filters.status)     params.status     = this.filters.status;
                const { data } = await axios.get(`${this.baseUrl}/dispositivos/data`, { params });
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
                console.error('[MF/Dispositivos]', e);
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
            this.detail = { open: true, loading: true, data: null };
            this.detailTab = 'info';
            try {
                const { data } = await axios.get(`${this.baseUrl}/dispositivos/${row.id}`);
                this.detail.data = data;
            } catch (e) {
                alert(e.response?.data?.message || e.message);
                this.detail.open = false;
            } finally {
                this.detail.loading = false;
            }
        },
        closeDetail() { this.detail = { open: false, loading: false, data: null }; },
        async doPing() {
            const id = this.detail.data?.device?.id;
            if (!id) return;
            this.acting = true;
            try {
                const { data } = await axios.post(`${this.baseUrl}/dispositivos/${id}/ping`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                alert(data.success ? 'Ping enviado correctamente.' : (data.message || 'Falló el envío.'));
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        async doForceLogout() {
            const id = this.detail.data?.device?.id;
            if (!id) return;
            if (!confirm('¿Forzar logout? Esto revocará TODOS los tokens del usuario del dispositivo.')) return;
            this.acting = true;
            try {
                const { data } = await axios.post(`${this.baseUrl}/dispositivos/${id}/force-logout`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                alert(`Logout forzado. Tokens revocados: ${data.tokens_revoked || 0}`);
                await this.fetch(this.pagination.current_page);
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        async doUnlink() {
            const id = this.detail.data?.device?.id;
            if (!id) return;
            if (!confirm('¿Desvincular este dispositivo? Se borrará su link_token y se revocarán los tokens del usuario.')) return;
            this.acting = true;
            try {
                await axios.delete(`${this.baseUrl}/dispositivos/${id}`, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                this.closeDetail();
                await this.fetch(this.pagination.current_page);
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        statusBadge(s) { return STATUS_BADGE[s] || 'bg-light text-dark'; },
        batteryBarClass(level) {
            if (level > 50) return 'bg-success';
            if (level > 20) return 'bg-warning';
            return 'bg-danger';
        },
        batteryTextClass(level) {
            if (level > 50) return 'text-success';
            if (level > 20) return 'text-warning';
            return 'text-danger';
        },
        mapUrl(loc) {
            const q = `${loc.lat},${loc.lng}`;
            return `https://maps.google.com/maps?q=${q}&hl=es&z=16&output=embed`;
        },
        relativeTime(value) {
            if (!value) return 'nunca';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            const diff = (Date.now() - d.getTime()) / 1000;
            if (diff < 60)    return 'hace unos segundos';
            if (diff < 3600)  return `hace ${Math.floor(diff / 60)} min`;
            if (diff < 86400) return `hace ${Math.floor(diff / 3600)} h`;
            return `hace ${Math.floor(diff / 86400)} d`;
        },
        formatDate(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
    },
};
</script>

<style scoped>
.kpi-card { border: 1px solid #e9ecef; }
.kpi-card.kpi-success { border-left: 4px solid #198754; }
.kpi-card.kpi-warning { border-left: 4px solid #ffc107; }
.kpi-num { font-size: 1.75rem; font-weight: 700; line-height: 1; margin-top: 4px; }

.device-card { cursor: pointer; transition: transform 0.15s, box-shadow 0.15s; }
.device-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.07); }

.battery-bar { margin-top: 6px; }

.map-frame { border-radius: 6px; overflow: hidden; border: 1px solid #e9ecef; }

.nav-tabs .nav-link { border: 0; border-bottom: 2px solid transparent; color: #495057; background: transparent; }
.nav-tabs .nav-link.active { color: #0d6efd; border-bottom-color: #0d6efd; }

.mf-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.mf-modal-panel { background: #fff; border-radius: 8px; width: min(720px, 92vw); max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
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
