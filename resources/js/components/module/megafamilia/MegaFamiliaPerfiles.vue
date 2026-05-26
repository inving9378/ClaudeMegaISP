<template>
    <div class="megafamilia-perfiles mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-child me-2 text-primary"></i>
                Perfiles MegaFamilia
            </h5>
            <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetchProfiles">
                <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
            </button>
        </div>

        <div class="row">
            <!-- Lista lateral -->
            <div class="col-lg-4 col-xl-3 mb-3 mf-perfiles-grid">
                <div class="card">
                    <div class="card-header py-2 bg-light">
                        <strong>Perfiles ({{ profiles.length }})</strong>
                    </div>
                    <div class="list-group list-group-flush profile-list">
                        <button
                            v-if="loading && profiles.length === 0"
                            class="list-group-item text-muted">
                            Cargando…
                        </button>
                        <button
                            v-for="p in profiles"
                            :key="p.id"
                            class="list-group-item list-group-item-action d-flex align-items-center"
                            :class="{ active: selected && selected.id === p.id }"
                            @click="selectProfile(p)">
                            <div class="profile-avatar me-2">
                                <i class="fa fa-child"></i>
                            </div>
                            <div class="flex-grow-1 text-start">
                                <div class="fw-semibold">{{ p.name }}</div>
                                <small class="opacity-75">
                                    {{ p.age || '?' }} años · {{ p.profile_type }}
                                </small>
                            </div>
                            <span class="badge bg-light text-dark">{{ p.devices_count }}</span>
                        </button>
                        <div v-if="!loading && profiles.length === 0" class="list-group-item text-muted small">
                            No hay perfiles registrados.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel detalle con tabs -->
            <div class="col-lg-8 col-xl-9 mb-3">
                <div class="card h-100">
                    <div v-if="!selected" class="card-body text-center text-muted py-5">
                        <i class="fa fa-arrow-left fa-2x mb-3 d-block"></i>
                        Selecciona un perfil de la lista.
                    </div>
                    <template v-else>
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h5 class="m-0">{{ selected.name }}</h5>
                                    <small class="text-muted">
                                        {{ selected.age || '?' }} años ·
                                        {{ selected.profile_type }} ·
                                        {{ selected.school_level || 'sin nivel' }}
                                    </small>
                                </div>
                                <span class="badge" :class="selected.active ? 'bg-success' : 'bg-secondary'">
                                    {{ selected.active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            <ul class="nav nav-tabs nav-justified card-header-tabs mf-perfil-tabs">
                                <li class="nav-item" v-for="t in tabs" :key="t.key">
                                    <button
                                        class="nav-link"
                                        :class="{ active: activeTab === t.key }"
                                        @click="activeTab = t.key">
                                        <i :class="['fa', t.icon, 'me-1']"></i>
                                        {{ t.label }}
                                        <span v-if="t.count !== null" class="badge bg-light text-dark ms-1">{{ t.count }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div class="card-body">
                            <div v-if="detailLoading" class="text-center py-5">
                                <div class="spinner-border text-primary"></div>
                            </div>
                            <template v-else>
                                <!-- Tab Info -->
                                <div v-show="activeTab === 'info'">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-4">Nombre</dt><dd class="col-sm-8">{{ selected.name }}</dd>
                                        <dt class="col-sm-4">Edad</dt><dd class="col-sm-8">{{ selected.age || '—' }}</dd>
                                        <dt class="col-sm-4">Tipo</dt><dd class="col-sm-8">{{ selected.profile_type }}</dd>
                                        <dt class="col-sm-4">Nivel escolar</dt><dd class="col-sm-8">{{ selected.school_level || '—' }}</dd>
                                        <dt class="col-sm-4">Cuenta padre</dt><dd class="col-sm-8">#{{ selected.account_id }}</dd>
                                        <dt class="col-sm-4">Dispositivos</dt><dd class="col-sm-8">{{ (detail?.profile?.devices || []).length }}</dd>
                                        <dt class="col-sm-4">Creado</dt><dd class="col-sm-8">{{ formatDate(selected.created_at) }}</dd>
                                    </dl>
                                </div>

                                <!-- Tab Dispositivos -->
                                <div v-show="activeTab === 'devices'">
                                    <div v-if="!detail?.profile?.devices?.length" class="text-muted small">
                                        Sin dispositivos vinculados.
                                    </div>
                                    <table v-else class="table table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Nombre</th>
                                                <th>OS</th>
                                                <th>App</th>
                                                <th>Batería</th>
                                                <th class="text-center">Estado</th>
                                                <th>Última actividad</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="d in detail.profile.devices" :key="d.id">
                                                <td>
                                                    <strong>{{ d.name }}</strong>
                                                    <div v-if="d.model" class="text-muted small">{{ d.model }}</div>
                                                </td>
                                                <td>{{ d.os }} {{ d.os_version }}</td>
                                                <td class="small">{{ d.app_version || '—' }}</td>
                                                <td>
                                                    <span v-if="d.battery_level !== null">
                                                        <i class="fa fa-battery-half me-1"></i>{{ d.battery_level }}%
                                                    </span>
                                                    <span v-else class="text-muted">—</span>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge" :class="d.status === 'online' ? 'bg-success' : 'bg-secondary'">
                                                        {{ d.status }}
                                                    </span>
                                                </td>
                                                <td class="small">{{ formatDate(d.last_seen_at) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Tab Reglas -->
                                <div v-show="activeTab === 'rules'">
                                    <div v-if="!detail?.profile?.rules" class="text-muted small">
                                        No hay reglas configuradas para este perfil.
                                    </div>
                                    <dl v-else class="row mb-3">
                                        <dt class="col-sm-5">Límite diario</dt>
                                        <dd class="col-sm-7">{{ detail.profile.rules.daily_limit_minutes }} min</dd>

                                        <dt class="col-sm-5">Límite fin de semana</dt>
                                        <dd class="col-sm-7">{{ detail.profile.rules.weekend_limit_minutes }} min</dd>

                                        <dt class="col-sm-5">Hora de dormir</dt>
                                        <dd class="col-sm-7">
                                            {{ detail.profile.rules.bedtime_start || '—' }} – {{ detail.profile.rules.bedtime_end || '—' }}
                                        </dd>

                                        <dt class="col-sm-5">Horario escolar</dt>
                                        <dd class="col-sm-7">
                                            {{ detail.profile.rules.school_start || '—' }} – {{ detail.profile.rules.school_end || '—' }}
                                        </dd>

                                        <dt class="col-sm-5">Internet pausado</dt>
                                        <dd class="col-sm-7">
                                            <span class="badge" :class="detail.profile.rules.internet_paused ? 'bg-danger' : 'bg-success'">
                                                {{ detail.profile.rules.internet_paused ? 'Sí' : 'No' }}
                                            </span>
                                        </dd>
                                    </dl>

                                    <h6 class="text-uppercase small text-muted mt-3">Apps bloqueadas ({{ (detail?.profile?.app_blocks || []).length }})</h6>
                                    <div v-if="!detail?.profile?.app_blocks?.length" class="text-muted small">Ninguna.</div>
                                    <ul v-else class="list-unstyled small">
                                        <li v-for="b in detail.profile.app_blocks" :key="b.id">
                                            <i class="fa fa-ban me-1 text-danger"></i> {{ b.package_name || b.app_name }}
                                        </li>
                                    </ul>

                                    <h6 class="text-uppercase small text-muted mt-3">Webs bloqueadas ({{ (detail?.profile?.web_blocks || []).length }})</h6>
                                    <div v-if="!detail?.profile?.web_blocks?.length" class="text-muted small">Ninguna.</div>
                                    <ul v-else class="list-unstyled small">
                                        <li v-for="b in detail.profile.web_blocks" :key="b.id">
                                            <i class="fa fa-ban me-1 text-danger"></i> {{ b.domain }}
                                        </li>
                                    </ul>
                                </div>

                                <!-- Tab Tareas -->
                                <div v-show="activeTab === 'tasks'">
                                    <div v-if="!detail?.profile?.tasks?.length" class="text-muted small">
                                        Sin tareas registradas.
                                    </div>
                                    <table v-else class="table table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Tarea</th>
                                                <th>Recompensa</th>
                                                <th>Puntos</th>
                                                <th class="text-center">Estado</th>
                                                <th>Completada</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="t in detail.profile.tasks" :key="t.id">
                                                <td>
                                                    <strong>{{ t.title }}</strong>
                                                    <div v-if="t.description" class="text-muted small">{{ t.description }}</div>
                                                </td>
                                                <td class="small">{{ rewardLabel(t.reward_type) }}</td>
                                                <td>{{ t.points }}</td>
                                                <td class="text-center">
                                                    <span class="badge" :class="taskBadge(t.status)">{{ t.status }}</span>
                                                </td>
                                                <td class="small">{{ formatDate(t.completed_at) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Tab Alertas -->
                                <div v-show="activeTab === 'alerts'">
                                    <div v-if="!detail?.alerts?.length" class="text-muted small">
                                        Sin alertas para este perfil.
                                    </div>
                                    <ul v-else class="list-group list-group-flush">
                                        <li v-for="a in detail.alerts" :key="a.id" class="list-group-item px-0">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <span class="badge bg-warning-subtle text-warning me-2">
                                                        {{ alertLabel(a.type) }}
                                                    </span>
                                                    <strong>{{ a.device?.name || 'Dispositivo' }}</strong>
                                                    <div class="text-muted small mt-1">{{ a.detail || '—' }}</div>
                                                </div>
                                                <small class="text-muted">{{ formatDate(a.created_at) }}</small>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <button @click="launchTour" class="btn-tour-help" title="Tour guiado">
            <i class="fas fa-question-circle"></i>
        </button>
    </div>
</template>

<script>
import { startTour, shouldShowTour } from './MegaFamiliaTour.js';

const ALERT_LABELS = {
    uninstall_attempt: 'Intento de desinstalación',
    geofence_exit: 'Salida de zona',
    blocked_content: 'Contenido bloqueado',
    low_battery: 'Batería baja',
    device_offline: 'Dispositivo offline',
};
const REWARD_LABELS = {
    time_extra: 'Tiempo extra',
    app_unlock: 'Desbloqueo de app',
    points: 'Puntos',
    badge: 'Insignia',
};
const TASK_BADGE = {
    pending: 'bg-secondary',
    completed: 'bg-info text-dark',
    approved: 'bg-success',
    rejected: 'bg-danger',
};

export default {
    name: 'MegaFamiliaPerfiles',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            loading: false,
            detailLoading: false,
            profiles: [],
            selected: null,
            detail: null,
            activeTab: 'info',
        };
    },
    computed: {
        tabs() {
            const d = this.detail || {};
            const p = d.profile || {};
            return [
                { key: 'info',    label: 'Info',         icon: 'fa-info-circle',     count: null },
                { key: 'devices', label: 'Dispositivos', icon: 'fa-mobile-alt',      count: (p.devices || []).length },
                { key: 'rules',   label: 'Reglas',       icon: 'fa-shield-alt',      count: null },
                { key: 'tasks',   label: 'Tareas',       icon: 'fa-tasks',           count: (p.tasks || []).length },
                { key: 'alerts',  label: 'Alertas',      icon: 'fa-exclamation-triangle', count: (d.alerts || []).length },
            ];
        },
    },
    mounted() {
        this.fetchProfiles();
        if (shouldShowTour('perfiles')) setTimeout(() => startTour('perfiles'), 1200);
    },
    methods: {
        launchTour() { startTour('perfiles'); },
        async fetchProfiles() {
            this.loading = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/perfiles/data`);
                this.profiles = data.profiles || [];
                if (!this.selected && this.profiles.length) {
                    this.selectProfile(this.profiles[0]);
                }
            } catch (e) {
                console.error('[MF/Perfiles] fetch', e);
            } finally {
                this.loading = false;
            }
        },
        async selectProfile(profile) {
            this.selected = profile;
            this.activeTab = 'info';
            this.detail = null;
            this.detailLoading = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/perfiles/${profile.id}`);
                this.detail = data;
            } catch (e) {
                console.error('[MF/Perfiles] show', e);
            } finally {
                this.detailLoading = false;
            }
        },
        alertLabel(type) { return ALERT_LABELS[type] || type; },
        rewardLabel(type) { return REWARD_LABELS[type] || type; },
        taskBadge(status) { return TASK_BADGE[status] || 'bg-secondary'; },
        formatDate(value) {
            if (!value) return '—';
            const d = new Date(value);
            if (Number.isNaN(d.getTime())) return value;
            return d.toLocaleString('es-MX', {
                year: 'numeric', month: 'short', day: '2-digit',
                hour: '2-digit', minute: '2-digit',
            });
        },
    },
};
</script>

<style scoped>
.profile-list { max-height: calc(100vh - 250px); overflow-y: auto; }
.profile-list .list-group-item { cursor: pointer; border-left: 3px solid transparent; }
.profile-list .list-group-item.active {
    background: rgba(13, 110, 253, 0.08);
    color: #0d6efd;
    border-left-color: #0d6efd;
}
.profile-avatar {
    width: 36px; height: 36px;
    background: rgba(13, 110, 253, 0.12);
    color: #0d6efd;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
}
.nav-tabs .nav-link {
    border: 0;
    border-bottom: 2px solid transparent;
    color: #495057;
    background: transparent;
}
.nav-tabs .nav-link.active {
    color: #0d6efd;
    border-bottom-color: #0d6efd;
    background: transparent;
}
dl.row dt { color: #6c757d; font-weight: 500; }

.btn-tour-help {
    position: fixed; bottom: 30px; right: 30px; z-index: 999;
    width: 48px; height: 48px; border-radius: 50%;
    background: #0d6efd; color: white; border: none;
    font-size: 20px; box-shadow: 0 4px 12px rgba(13,110,253,0.4);
    cursor: pointer; transition: transform 0.2s;
}
.btn-tour-help:hover { transform: scale(1.1); }
</style>
