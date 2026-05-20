<template>
    <div class="megafamilia-dispositivos mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">Dispositivos</h5>
            <div class="d-flex gap-2">
                <select v-model="osFilter" class="form-select form-select-sm" style="width:120px" @change="fetch">
                    <option value="">Todos los SO</option>
                    <option value="android">Android</option>
                    <option value="ios">iOS</option>
                </select>
                <select v-model="statusFilter" class="form-select form-select-sm" style="width:130px" @change="fetch">
                    <option value="">Todos los estados</option>
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                </select>
            </div>
        </div>

        <div v-if="loading && rows.length === 0" class="text-muted">Cargando…</div>
        <div v-else-if="errorMsg" class="text-danger small">{{ errorMsg }}</div>
        <div v-else-if="rows.length === 0" class="text-muted">No hay dispositivos.</div>

        <div v-else class="row">
            <div v-for="d in rows" :key="d.id" class="col-md-6 col-lg-4 mb-3">
                <div class="card device-card h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <i class="fab fa-2x me-3" :class="osIcon(d.os)"></i>
                            <div class="flex-grow-1">
                                <div class="fw-bold">{{ d.name }}</div>
                                <div class="text-muted small">{{ d.model || '—' }}</div>
                                <div class="text-muted small">{{ d.profile?.name || 'sin perfil' }}</div>
                            </div>
                            <span class="status-dot" :class="d.status"></span>
                        </div>
                        <div class="mt-3 d-flex justify-content-between align-items-center">
                            <div class="battery">
                                <i class="fa" :class="batteryIcon(d.battery_level)"></i>
                                {{ d.battery_level !== null ? d.battery_level + '%' : '—' }}
                            </div>
                            <div class="text-muted small">v{{ d.app_version || '?' }} · {{ d.os }} {{ d.os_version || '' }}</div>
                        </div>
                        <div class="text-muted small mt-2">
                            <i class="fa fa-clock"></i>
                            Última conexión: {{ d.last_seen_at ? timeAgo(d.last_seen_at) : 'nunca' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="pagination" class="d-flex justify-content-between align-items-center mt-2">
            <div class="text-muted small">{{ pagination.total }} dispositivos · página {{ pagination.current_page }}/{{ pagination.last_page }}</div>
            <div>
                <button class="btn btn-sm btn-outline-secondary me-1" :disabled="!pagination.prev_page_url || loading" @click="goto(pagination.current_page - 1)">←</button>
                <button class="btn btn-sm btn-outline-secondary" :disabled="!pagination.next_page_url || loading" @click="goto(pagination.current_page + 1)">→</button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaDispositivos',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { rows: [], pagination: null, osFilter: '', statusFilter: '', loading: false, errorMsg: '', page: 1 }; },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const params = { page: this.page };
                if (this.osFilter) params.os = this.osFilter;
                if (this.statusFilter) params.status = this.statusFilter;
                const { data } = await axios.get(`${this.baseUrl}/dispositivos/data`, { params });
                this.rows = data.data || [];
                this.pagination = {
                    current_page: data.current_page, last_page: data.last_page, total: data.total,
                    prev_page_url: data.prev_page_url, next_page_url: data.next_page_url,
                };
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        goto(p) { this.page = Math.max(1, p); this.fetch(); },
        osIcon(os) { return os === 'ios' ? 'fa-apple text-secondary' : 'fa-android text-success'; },
        batteryIcon(pct) {
            if (pct === null || pct === undefined) return 'fa-battery-empty text-muted';
            if (pct >= 80) return 'fa-battery-full text-success';
            if (pct >= 50) return 'fa-battery-three-quarters text-success';
            if (pct >= 25) return 'fa-battery-half text-warning';
            if (pct >= 10) return 'fa-battery-quarter text-warning';
            return 'fa-battery-empty text-danger';
        },
        timeAgo(ts) {
            const diff = Math.floor((Date.now() - new Date(ts).getTime()) / 60000);
            if (diff < 1) return 'ahora';
            if (diff < 60) return `${diff} min`;
            const h = Math.floor(diff / 60);
            if (h < 24) return `${h} h`;
            const d = Math.floor(h / 24);
            return `${d} d`;
        },
    },
};
</script>

<style scoped>
.status-dot { display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: #adb5bd; }
.status-dot.online { background: #38c172; box-shadow: 0 0 0 3px rgba(56,193,114,0.2); }
.status-dot.offline { background: #adb5bd; }
.battery { font-weight: 600; }
.device-card { transition: transform 0.15s; }
.device-card:hover { transform: translateY(-2px); }
</style>
