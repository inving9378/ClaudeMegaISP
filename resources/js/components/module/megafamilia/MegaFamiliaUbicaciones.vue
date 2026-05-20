<template>
    <div class="megafamilia-ubicaciones mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="m-0">Ubicaciones en tiempo real</h5>
            <button class="btn btn-sm btn-outline-secondary" @click="fetch" :disabled="loading">
                <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
            </button>
        </div>

        <div class="row">
            <div class="col-lg-8 mb-3">
                <div class="card map-card">
                    <div class="card-body p-0">
                        <div class="map-placeholder">
                            <div class="text-center text-muted">
                                <i class="fa fa-map fa-3x mb-2"></i>
                                <div>Mapa de Google Maps / Leaflet pendiente de cablear</div>
                                <div class="small mt-1">
                                    Se integrará con MIX_VUE_APP_GOOGLEMAPS_KEY del proyecto.<br>
                                    Por ahora se listan las últimas posiciones a la derecha.
                                </div>
                            </div>
                            <div v-if="locations.length" class="markers">
                                <div v-for="loc in locations" :key="loc.id" class="marker">
                                    <i class="fa fa-map-marker-alt text-danger"></i>
                                    {{ loc.device?.profile?.name || loc.device?.name }}
                                    ({{ Number(loc.lat).toFixed(4) }}, {{ Number(loc.lng).toFixed(4) }})
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">Últimas posiciones ({{ locations.length }})</h6>
                        <div v-if="loading && locations.length === 0" class="text-muted small">Cargando…</div>
                        <div v-else-if="locations.length === 0" class="text-muted small">Sin reportes recientes.</div>
                        <ul v-else class="list-unstyled m-0">
                            <li v-for="loc in locations" :key="loc.id" class="loc-item">
                                <div class="fw-bold">
                                    <i class="fa fa-mobile-screen text-success me-1"></i>
                                    {{ loc.device?.profile?.name || loc.device?.name || `Device ${loc.device_id}` }}
                                </div>
                                <div class="small text-muted">
                                    <span>{{ Number(loc.lat).toFixed(5) }}, {{ Number(loc.lng).toFixed(5) }}</span>
                                    <span class="ms-2" v-if="loc.battery !== null">
                                        <i class="fa fa-battery-half"></i> {{ loc.battery }}%
                                    </span>
                                </div>
                                <div class="small text-muted">{{ timeAgo(loc.recorded_at) }}</div>
                            </li>
                        </ul>
                    </div>
                </div>

                <div v-if="offline.length" class="card mt-2">
                    <div class="card-body">
                        <h6 class="card-title text-warning">
                            <i class="fa fa-wifi"></i> Offline ({{ offline.length }})
                        </h6>
                        <ul class="list-unstyled m-0">
                            <li v-for="d in offline" :key="d.id" class="small">
                                {{ d.profile?.name || d.name }}
                                <span class="text-muted">— última vez {{ d.last_seen_at ? timeAgo(d.last_seen_at) : 'nunca' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'MegaFamiliaUbicaciones',
    props: { baseUrl: { type: String, required: true }, csrfToken: { type: String, required: true } },
    data() { return { locations: [], offline: [], loading: false, errorMsg: '' }; },
    mounted() { this.fetch(); },
    methods: {
        async fetch() {
            this.loading = true; this.errorMsg = '';
            try {
                const { data } = await axios.get(`${this.baseUrl}/ubicaciones/latest`);
                this.locations = data.locations || [];
                this.offline = data.offline_devices || [];
            } catch (e) { this.errorMsg = e.response?.data?.error || e.message; }
            finally { this.loading = false; }
        },
        timeAgo(ts) {
            const diff = Math.floor((Date.now() - new Date(ts).getTime()) / 60000);
            if (diff < 1) return 'ahora';
            if (diff < 60) return `hace ${diff} min`;
            const h = Math.floor(diff / 60);
            if (h < 24) return `hace ${h} h`;
            return `hace ${Math.floor(h / 24)} d`;
        },
    },
};
</script>

<style scoped>
.map-placeholder {
    position: relative;
    min-height: 420px;
    background: repeating-linear-gradient(45deg, #eef0f4, #eef0f4 12px, #f5f7fa 12px, #f5f7fa 24px);
    display: flex; align-items: center; justify-content: center;
}
.markers { position: absolute; top: 0.5rem; left: 0.5rem; right: 0.5rem; display: flex; flex-direction: column; gap: 0.3rem; }
.marker { background: rgba(255,255,255,0.9); padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 12px; }
.loc-item { padding: 0.4rem 0; border-bottom: 1px dashed #eef0f4; }
.loc-item:last-child { border-bottom: 0; }
</style>
