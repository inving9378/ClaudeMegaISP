<template>
    <div class="megafamilia-solicitudes mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-inbox me-2 text-primary"></i>
                Solicitudes MegaFamilia
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch()">
                    <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                </button>
                <button class="btn btn-sm btn-outline-primary" :disabled="acting" @click="bulkRead">
                    <i class="fa fa-check-double me-1"></i> Marcar todas leídas
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Perfil</label>
                        <select v-model="filters.profile_id" class="form-select form-select-sm" @change="fetch()">
                            <option value="">Todos</option>
                            <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Tipo</label>
                        <select v-model="filters.type" class="form-select form-select-sm" @change="fetch()">
                            <option value="">Todos</option>
                            <option value="time_extra">Tiempo extra</option>
                            <option value="app_unlock">Desbloqueo app</option>
                            <option value="web_unlock">Desbloqueo sitio</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <small class="text-muted">
                            <i class="fa fa-sync me-1"></i>
                            Auto-actualiza cada 30s
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-3 mf-sol-tabs">
            <li class="nav-item" v-for="t in tabs" :key="t.key">
                <button
                    class="nav-link"
                    :class="{ active: activeTab === t.key }"
                    @click="setTab(t.key)">
                    <i :class="['fa', t.icon, 'me-1']"></i>
                    {{ t.label }}
                    <span v-if="t.count !== null" class="badge ms-1" :class="t.badgeClass">{{ t.count }}</span>
                </button>
            </li>
        </ul>

        <!-- Cards -->
        <div v-if="loading && rows.length === 0" class="text-center py-5 text-muted">Cargando…</div>
        <div v-else-if="rows.length === 0" class="text-center py-5 text-muted">
            <i class="fa fa-inbox fa-3x mb-3 d-block"></i>
            No hay solicitudes {{ tabLabel.toLowerCase() }}.
        </div>
        <div v-else class="row">
            <div v-for="row in rows" :key="row.id" class="col-md-6 col-lg-4 mb-3">
                <div class="card request-card h-100" :class="cardClass(row)">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <div class="profile-avatar">
                                <img v-if="row.profile?.photo" :src="row.profile.photo" alt="" />
                                <span v-else>{{ initials(row.profile?.name) }}</span>
                            </div>
                            <div class="flex-grow-1">
                                <strong>{{ row.profile?.name || '—' }}</strong>
                                <div class="text-muted small">
                                    <i class="fa fa-mobile-alt me-1"></i>{{ row.device?.name || 'Dispositivo desconocido' }}
                                </div>
                            </div>
                            <span class="badge" :class="typeBadge(row.type)">{{ typeLabel(row.type) }}</span>
                        </div>

                        <p class="mb-2 small">{{ row.message || row.detail || 'Sin descripción' }}</p>

                        <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                            <span><i class="fa fa-clock me-1"></i>{{ relativeTime(row.created_at) }}</span>
                            <span>{{ formatDate(row.created_at) }}</span>
                        </div>

                        <div v-if="row.status === 'pending'" class="d-flex gap-2">
                            <button class="btn btn-sm btn-success flex-grow-1 mf-sol-aprobar" @click="openApprove(row)">
                                <i class="fa fa-check me-1"></i> Aprobar
                            </button>
                            <button class="btn btn-sm btn-danger flex-grow-1 mf-sol-rechazar" @click="openReject(row)">
                                <i class="fa fa-times me-1"></i> Rechazar
                            </button>
                        </div>
                        <div v-else class="text-muted small">
                            <span :class="row.status === 'approved' ? 'text-success' : 'text-danger'">
                                <i :class="['fa', row.status === 'approved' ? 'fa-check' : 'fa-times', 'me-1']"></i>
                                {{ row.status === 'approved' ? 'Aprobada' : 'Rechazada' }}
                            </span>
                            <span v-if="row.responded_at" class="ms-2">
                                {{ relativeTime(row.responded_at) }}
                            </span>
                            <div v-if="row.notes" class="mt-1"><strong>Motivo:</strong> {{ row.notes }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button @click="launchTour" class="btn-tour-help" title="Tour guiado">
            <i class="fas fa-question-circle"></i>
        </button>

        <!-- Modal aprobar -->
        <div v-if="approve.open" class="mf-modal-overlay" @click.self="approve.open = false">
            <div class="mf-modal-panel" style="max-width:460px;">
                <div class="mf-modal-header">
                    <h5 class="m-0">Aprobar solicitud</h5>
                    <button class="btn-close" @click="approve.open = false"></button>
                </div>
                <div class="mf-modal-body">
                    <p class="small">
                        <strong>{{ approve.profile_name }}</strong> solicita
                        <span class="badge" :class="typeBadge(approve.type)">{{ typeLabel(approve.type) }}</span>
                    </p>
                    <p class="text-muted small">{{ approve.message }}</p>

                    <div v-if="approve.type === 'time_extra'">
                        <label class="form-label">Minutos extra a conceder</label>
                        <input v-model.number="approve.minutes" type="number" min="1" max="1440" class="form-control" />
                    </div>
                    <div v-else-if="approve.type === 'app_unlock' || approve.type === 'web_unlock'">
                        <label class="form-label">Duración temporal (minutos)</label>
                        <input v-model.number="approve.duration" type="number" min="1" max="1440" class="form-control" />
                    </div>
                </div>
                <div class="mf-modal-footer">
                    <button class="btn btn-outline-secondary" @click="approve.open = false">Cancelar</button>
                    <button class="btn btn-success" :disabled="acting" @click="doApprove">
                        <i class="fa fa-check me-1"></i> Aprobar y notificar
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal rechazar -->
        <div v-if="reject.open" class="mf-modal-overlay" @click.self="reject.open = false">
            <div class="mf-modal-panel" style="max-width:460px;">
                <div class="mf-modal-header">
                    <h5 class="m-0">Rechazar solicitud</h5>
                    <button class="btn-close" @click="reject.open = false"></button>
                </div>
                <div class="mf-modal-body">
                    <p class="small">
                        <strong>{{ reject.profile_name }}</strong> · solicitud #{{ reject.request_id }}
                    </p>
                    <label class="form-label">Motivo (opcional)</label>
                    <textarea v-model="reject.reason" rows="3" class="form-control"
                              placeholder="Explicación visible para el hijo…"></textarea>
                </div>
                <div class="mf-modal-footer">
                    <button class="btn btn-outline-secondary" @click="reject.open = false">Cancelar</button>
                    <button class="btn btn-danger" :disabled="acting" @click="doReject">
                        <i class="fa fa-times me-1"></i> Rechazar y notificar
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { startTour, shouldShowTour } from './MegaFamiliaTour.js';

const TYPE_LABELS = {
    time_extra: 'Tiempo extra',
    app_unlock: 'Desbloqueo app',
    web_unlock: 'Desbloqueo sitio',
};
const TYPE_BADGES = {
    time_extra: 'bg-info-subtle text-info',
    app_unlock: 'bg-primary-subtle text-primary',
    web_unlock: 'bg-warning-subtle text-warning',
};

export default {
    name: 'MegaFamiliaSolicitudes',
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
            counts: { pending: 0, approved: 0, rejected: 0 },
            activeTab: 'pending',
            filters: { profile_id: '', type: '' },
            approve: { open: false, request_id: null, type: '', profile_name: '', message: '', minutes: 30, duration: 30 },
            reject: { open: false, request_id: null, profile_name: '', reason: '' },
            pollTimer: null,
        };
    },
    computed: {
        tabs() {
            return [
                { key: 'pending',  label: 'Pendientes', icon: 'fa-hourglass-half', count: this.counts.pending,  badgeClass: 'bg-danger' },
                { key: 'approved', label: 'Aprobadas',  icon: 'fa-check-circle',   count: this.counts.approved, badgeClass: 'bg-success' },
                { key: 'rejected', label: 'Rechazadas', icon: 'fa-times-circle',   count: this.counts.rejected, badgeClass: 'bg-secondary' },
            ];
        },
        tabLabel() {
            return ({ pending: 'pendientes', approved: 'aprobadas', rejected: 'rechazadas' })[this.activeTab];
        },
    },
    mounted() {
        this.fetchProfiles();
        this.fetch();
        this.pollTimer = setInterval(() => this.fetch(true), 30000);
        if (shouldShowTour('solicitudes')) setTimeout(() => startTour('solicitudes'), 1200);
    },
    beforeUnmount() {
        if (this.pollTimer) clearInterval(this.pollTimer);
    },
    methods: {
        launchTour() { startTour('solicitudes'); },
        setTab(tab) {
            this.activeTab = tab;
            this.fetch();
        },
        async fetch(silent = false) {
            if (!silent) this.loading = true;
            try {
                const params = { status: this.activeTab, per_page: 30 };
                if (this.filters.profile_id) params.profile_id = this.filters.profile_id;
                if (this.filters.type)       params.type       = this.filters.type;
                const { data } = await axios.get(`${this.baseUrl}/solicitudes/data`, { params });
                this.rows = data.list?.data || [];
                this.counts = data.counts || this.counts;
            } catch (e) {
                if (!silent) console.error('[MF/Solicitudes]', e);
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
        openApprove(row) {
            this.approve = {
                open: true,
                request_id: row.id,
                type: row.type,
                profile_name: row.profile?.name || '',
                message: row.message || row.detail || '',
                minutes: 30,
                duration: 30,
            };
        },
        openReject(row) {
            this.reject = {
                open: true,
                request_id: row.id,
                profile_name: row.profile?.name || '',
                reason: '',
            };
        },
        async doApprove() {
            this.acting = true;
            try {
                const payload = {};
                if (this.approve.type === 'time_extra') payload.minutes = this.approve.minutes;
                else payload.duration = this.approve.duration;
                await axios.post(
                    `${this.baseUrl}/solicitudes/${this.approve.request_id}/approve`,
                    payload,
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } },
                );
                this.approve.open = false;
                await this.fetch();
            } catch (e) {
                alert(e.response?.data?.message || e.message);
            } finally { this.acting = false; }
        },
        async doReject() {
            this.acting = true;
            try {
                await axios.post(
                    `${this.baseUrl}/solicitudes/${this.reject.request_id}/reject`,
                    { reason: this.reject.reason },
                    { headers: { 'X-CSRF-TOKEN': this.csrfToken } },
                );
                this.reject.open = false;
                await this.fetch();
            } catch (e) {
                alert(e.response?.data?.message || e.message);
            } finally { this.acting = false; }
        },
        async bulkRead() {
            if (!confirm('¿Marcar todas las solicitudes pendientes como vistas?')) return;
            this.acting = true;
            try {
                await axios.post(`${this.baseUrl}/solicitudes/bulk-read`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                await this.fetch();
            } catch (e) { alert(e.response?.data?.message || e.message); }
            finally { this.acting = false; }
        },
        cardClass(row) {
            if (row.status === 'pending') return 'border-warning';
            if (row.status === 'approved') return 'border-success-subtle';
            return 'border-secondary-subtle';
        },
        typeLabel(t) { return TYPE_LABELS[t] || t; },
        typeBadge(t) { return TYPE_BADGES[t] || 'bg-light text-dark'; },
        initials(name) {
            if (!name) return '?';
            return name.split(/\s+/).map(p => p[0]).slice(0, 2).join('').toUpperCase();
        },
        relativeTime(value) {
            if (!value) return '';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            const diff = (Date.now() - d.getTime()) / 1000;
            if (diff < 60)   return 'hace unos segundos';
            if (diff < 3600) return `hace ${Math.floor(diff / 60)} min`;
            if (diff < 86400) return `hace ${Math.floor(diff / 3600)} h`;
            return `hace ${Math.floor(diff / 86400)} d`;
        },
        formatDate(value) {
            if (!value) return '';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        },
    },
};
</script>

<style scoped>
.request-card { border: 2px solid #e9ecef; }
.profile-avatar {
    width: 40px; height: 40px;
    background: rgba(13, 110, 253, 0.12);
    color: #0d6efd; font-weight: 600;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}
.profile-avatar img { width: 100%; height: 100%; object-fit: cover; }

.nav-tabs .nav-link { border: 0; border-bottom: 2px solid transparent; color: #495057; background: transparent; }
.nav-tabs .nav-link.active { color: #0d6efd; border-bottom-color: #0d6efd; }

.mf-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.mf-modal-panel { background: #fff; border-radius: 8px; width: min(640px, 92vw); max-height: 90vh; overflow: hidden; display: flex; flex-direction: column; }
.mf-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #e9ecef; }
.mf-modal-body { padding: 1rem 1.25rem; overflow-y: auto; }
.mf-modal-footer { padding: 0.75rem 1.25rem; border-top: 1px solid #e9ecef; display: flex; justify-content: flex-end; gap: 0.5rem; }

.btn-tour-help {
    position: fixed; bottom: 30px; right: 30px; z-index: 999;
    width: 48px; height: 48px; border-radius: 50%;
    background: #0d6efd; color: white; border: none;
    font-size: 20px; box-shadow: 0 4px 12px rgba(13,110,253,0.4);
    cursor: pointer; transition: transform 0.2s;
}
.btn-tour-help:hover { transform: scale(1.1); }
</style>
