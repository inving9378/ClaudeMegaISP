<template>
    <div class="megafamilia-alertas mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">Alertas</h5>
            <div class="form-check form-switch">
                <input v-model="unreadOnly" class="form-check-input" type="checkbox" id="unread-switch" @change="fetch" />
                <label class="form-check-label" for="unread-switch">Sólo sin leer</label>
            </div>
        </div>

        <!-- Filter tabs -->
        <ul class="nav nav-pills mb-3 flex-wrap">
            <li class="nav-item">
                <a class="nav-link" :class="{ active: typeFilter === '' }" href="#" @click.prevent="setType('')">Todas</a>
            </li>
            <li v-for="t in types" :key="t.key" class="nav-item">
                <a class="nav-link" :class="{ active: typeFilter === t.key }" href="#" @click.prevent="setType(t.key)">
                    <i class="fa me-1" :class="t.icon"></i> {{ t.label }}
                </a>
            </li>
        </ul>

        <div v-if="loading && rows.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
        <div v-else-if="rows.length === 0" class="text-muted">No hay alertas con esos filtros.</div>

        <div v-else>
            <div v-for="alert in rows" :key="alert.id" class="card alert-item mb-2" :class="{ unread: !alert.read_at }">
                <div class="card-body d-flex align-items-center">
                    <i class="fa fa-2x me-3" :class="iconFor(alert.type)"></i>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center">
                            <strong>{{ labelFor(alert.type) }}</strong>
                            <span v-if="!alert.read_at" class="badge bg-primary ms-2">Nueva</span>
                            <span class="text-muted small ms-2">{{ alert.profile?.name || 'sin perfil' }}</span>
                            <span class="text-muted small ms-auto">{{ formatDate(alert.created_at) }}</span>
                        </div>
                        <div v-if="alert.detail" class="text-muted small">{{ alert.detail }}</div>
                    </div>
                    <button v-if="!alert.read_at" class="btn btn-sm btn-outline-secondary ms-2" :disabled="acting === alert.id" @click="markRead(alert)">
                        <i class="fa fa-check"></i>
                    </button>
                </div>
            </div>

            <div v-if="pagination" class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">Página {{ pagination.current_page }} / {{ pagination.last_page }} · {{ pagination.total }} alertas</div>
                <div>
                    <button class="btn btn-sm btn-outline-secondary me-1" :disabled="!pagination.prev_page_url || loading" @click="goto(pagination.current_page - 1)">←</button>
                    <button class="btn btn-sm btn-outline-secondary" :disabled="!pagination.next_page_url || loading" @click="goto(pagination.current_page + 1)">→</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
const TYPES = [
    { key: 'uninstall_attempt', label: 'Desinstalación', icon: 'fa-shield-alt text-danger' },
    { key: 'geofence_exit', label: 'Geofence', icon: 'fa-map-marker-alt text-warning' },
    { key: 'blocked_content', label: 'Contenido', icon: 'fa-ban text-secondary' },
    { key: 'low_battery', label: 'Batería baja', icon: 'fa-battery-quarter text-warning' },
    { key: 'device_offline', label: 'Offline', icon: 'fa-wifi text-muted' },
];

export default {
    name: 'MegaFamiliaAlertas',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() {
        return { rows: [], pagination: null, types: TYPES, typeFilter: '', unreadOnly: false, loading: false, errorMsg: '', acting: null, page: 1 };
    },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const params = { page: this.page };
                if (this.typeFilter) params.type = this.typeFilter;
                if (this.unreadOnly) params.unread = 'true';
                const { data } = await axios.get(`${this.baseUrl}/alertas/data`, { params });
                this.rows = data.data || [];
                this.pagination = {
                    current_page: data.current_page, last_page: data.last_page, total: data.total,
                    prev_page_url: data.prev_page_url, next_page_url: data.next_page_url,
                };
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        setType(t) { this.typeFilter = t; this.page = 1; this.fetch(); },
        goto(p) { this.page = Math.max(1, p); this.fetch(); },
        async markRead(alert) {
            this.acting = alert.id;
            try { await axios.post(`${this.baseUrl}/alertas/${alert.id}/read`, {}, { headers: { 'X-CSRF-TOKEN': this.csrfToken } }); alert.read_at = new Date().toISOString(); }
            catch (e) { alert(e.response?.data?.error || e.message); }
            finally { this.acting = null; }
        },
        iconFor(type) { return (TYPES.find((t) => t.key === type) || { icon: 'fa-bell' }).icon; },
        labelFor(type) { return (TYPES.find((t) => t.key === type) || { label: type }).label; },
        formatDate(ts) { if (!ts) return ''; return new Date(ts).toLocaleString('es-MX', { dateStyle: 'short', timeStyle: 'short' }); },
    },
};
</script>

<style scoped>
.alert-item.unread { border-left: 3px solid #5b8def; background: #f5f8ff; }
</style>
