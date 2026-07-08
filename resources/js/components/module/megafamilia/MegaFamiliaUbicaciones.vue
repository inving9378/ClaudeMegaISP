<template>
    <div class="megafamilia-ubicaciones mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-map-marker-alt me-2 text-primary"></i>
                Ubicaciones en tiempo real
            </h5>
            <div class="d-flex gap-2">
                <div id="btn-toggle-geofences" class="form-check form-switch m-0 align-self-center">
                    <input class="form-check-input" type="checkbox" id="show-geofences" v-model="showGeofences" @change="drawGeofences" />
                    <label class="form-check-label small" for="show-geofences">
                        <i class="fa fa-draw-polygon me-1"></i>Geofences
                    </label>
                </div>
                <button class="btn btn-sm btn-outline-secondary" :disabled="refreshing" @click="refresh">
                    <i class="fa fa-sync" :class="{ 'fa-spin': refreshing }"></i> Actualizar
                </button>
            </div>
        </div>

        <div v-if="error" class="alert alert-danger small">{{ error }}</div>

        <div class="row">
            <!-- Panel izquierdo: perfiles -->
            <div class="col-lg-4 col-xl-3 mb-3 mf-ubi-perfiles">
                <div class="card">
                    <div class="card-header py-2 bg-light">
                        <strong>Perfiles activos ({{ profiles.length }})</strong>
                    </div>
                    <div class="profile-list">
                        <div v-if="profilesLoading" class="text-muted small p-3 text-center">
                            Cargando…
                        </div>
                        <button
                            v-for="p in profiles"
                            :key="p.id"
                            class="profile-item"
                            :class="{ active: selected && selected.id === p.id }"
                            @click="selectProfile(p)">
                            <div class="profile-avatar">
                                <img v-if="p.photo" :src="p.photo" alt="" />
                                <span v-else>{{ initials(p.name) }}</span>
                            </div>
                            <div class="profile-info">
                                <div class="fw-semibold">{{ p.name }}</div>
                                <div class="text-muted small">
                                    {{ p.last_location ? relativeTime(p.last_location.recorded_at) : 'sin ubicación' }}
                                </div>
                            </div>
                            <div class="profile-meta text-end">
                                <span v-if="p.battery_level !== null && p.battery_level !== undefined" :class="batteryTextClass(p.battery_level)">
                                    <i class="fa fa-battery-half"></i> {{ p.battery_level }}%
                                </span>
                            </div>
                        </button>
                        <div v-if="!profilesLoading && profiles.length === 0" class="text-muted small p-3 text-center">
                            Sin perfiles activos.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mapa -->
            <div class="col-lg-8 col-xl-9 mb-3">
                <div class="card">
                    <div class="card-body p-2">
                        <div v-if="!apiReady && !apiError" class="map-loading">
                            <div class="spinner-border text-primary"></div>
                            <p class="text-muted mt-2">Cargando Google Maps…</p>
                        </div>
                        <div v-else-if="apiError" class="alert alert-warning small m-2">
                            <i class="fa fa-exclamation-triangle me-1"></i>
                            No se pudo cargar Google Maps. Verifica las credenciales en
                            <a :href="`${rootUrl}/configuracion/credenciales-google-maps`">Configuración → Google Maps</a>.
                        </div>
                        <div id="mf-mapa" ref="mapEl" class="mf-map"></div>

                        <div class="map-actions mt-2 d-flex justify-content-between flex-wrap gap-2">
                            <div>
                                <button class="btn btn-sm btn-outline-primary"
                                        :disabled="!selected || historyLoading"
                                        @click="loadHistory(true)">
                                    <i class="fa fa-route me-1"></i> Ver historial
                                </button>
                                <button v-if="historyLine" class="btn btn-sm btn-outline-secondary ms-1" @click="clearHistory">
                                    <i class="fa fa-times me-1"></i> Limpiar historial
                                </button>
                            </div>
                            <small class="text-muted align-self-center">
                                <i class="fa fa-sync me-1"></i> Auto-actualiza cada 2 min
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Panel inferior con tabla -->
                <div v-if="selected" class="card mt-3">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
                        <strong>Últimas 20 posiciones — {{ selected.name }}</strong>
                        <button class="btn btn-sm btn-link p-0" @click="historyCollapsed = !historyCollapsed">
                            <i class="fa" :class="historyCollapsed ? 'fa-chevron-down' : 'fa-chevron-up'"></i>
                        </button>
                    </div>
                    <div v-show="!historyCollapsed" class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Lat</th>
                                    <th>Lng</th>
                                    <th>Precisión</th>
                                    <th>Batería</th>
                                    <th>Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(loc, idx) in historySlice(20)" :key="loc.id"
                                    @click="centerOn(loc)" role="button">
                                    <td>{{ idx + 1 }}</td>
                                    <td>{{ Number(loc.lat).toFixed(5) }}</td>
                                    <td>{{ Number(loc.lng).toFixed(5) }}</td>
                                    <td>{{ loc.accuracy || '—' }}m</td>
                                    <td>{{ loc.battery !== null ? loc.battery + '%' : '—' }}</td>
                                    <td class="small">{{ formatDate(loc.recorded_at) }}</td>
                                </tr>
                                <tr v-if="!history.length">
                                    <td colspan="6" class="text-center text-muted py-3">
                                        Sin posiciones registradas.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
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

let mapInstance = null;
let markerMap = new Map();  // profile_id -> google.maps.Marker
let infoMap = new Map();    // profile_id -> google.maps.InfoWindow
let historyPolyline = null;
let geofenceShapes = [];

export default {
    name: 'MegaFamiliaUbicaciones',
    props: {
        baseUrl: { type: String, required: true },
        csrfToken: { type: String, required: true },
    },
    data() {
        return {
            apiReady: false,
            apiError: false,
            apiKey: null,
            defaultLat: 19.4326,
            defaultLng: -99.1332,
            defaultZoom: 10,

            profilesLoading: false,
            profiles: [],
            selected: null,

            history: [],
            historyLoading: false,
            historyCollapsed: false,
            historyLine: null,

            showGeofences: false,
            geofences: [],

            refreshing: false,
            pollTimer: null,
            error: '',
        };
    },
    computed: {
        rootUrl() {
            return this.baseUrl.replace(/\/megafamilia$/, '');
        },
    },
    async mounted() {
        await this.loadMapsSDK();
        if (this.apiReady) {
            this.initMap();
            await this.fetchProfiles();
        }
        this.pollTimer = setInterval(() => this.refresh(true), 120000);
        if (shouldShowTour('ubicaciones')) setTimeout(() => startTour('ubicaciones'), 1500);
    },
    beforeUnmount() {
        if (this.pollTimer) clearInterval(this.pollTimer);
        markerMap.forEach(m => m.setMap(null));
        markerMap = new Map();
        geofenceShapes.forEach(s => s.setMap(null));
        geofenceShapes = [];
        if (historyPolyline) { historyPolyline.setMap(null); historyPolyline = null; }
        mapInstance = null;
    },
    methods: {
        launchTour() { startTour('ubicaciones'); },
        /**
         * Carga la API key desde el backend (`/configuracion/credenciales-google-maps/render-config`)
         * y luego inyecta el script SDK. Idéntico patrón al módulo Maps.
         */
        async loadMapsSDK() {
            try {
                const { data } = await axios.get(`${this.rootUrl}/configuracion/credenciales-google-maps/render-config`);
                const cfg = Array.isArray(data) ? data[0] : data;
                if (!cfg?.api_key) {
                    this.apiError = true;
                    return;
                }
                this.apiKey = cfg.api_key;
                if (cfg.latitude)  this.defaultLat = parseFloat(cfg.latitude);
                if (cfg.longitude) this.defaultLng = parseFloat(cfg.longitude);
                if (cfg.zoom)      this.defaultZoom = parseInt(cfg.zoom, 10);
            } catch (e) {
                this.apiError = true;
                return;
            }

            if (typeof window.google === 'undefined' || !window.google.maps) {
                await new Promise((resolve, reject) => {
                    const existing = document.querySelector(`script[data-mf-gmaps]`);
                    if (existing) { existing.addEventListener('load', resolve); existing.addEventListener('error', reject); return; }
                    const s = document.createElement('script');
                    s.src = `https://maps.googleapis.com/maps/api/js?key=${this.apiKey}&libraries=geometry`;
                    s.async = true; s.defer = true;
                    s.setAttribute('data-mf-gmaps', '1');
                    s.onload = resolve;
                    s.onerror = reject;
                    document.head.appendChild(s);
                });
            }
            this.apiReady = !!(window.google && window.google.maps);
            if (!this.apiReady) this.apiError = true;
        },
        initMap() {
            if (!this.$refs.mapEl) return;
            mapInstance = new google.maps.Map(this.$refs.mapEl, {
                center: { lat: this.defaultLat, lng: this.defaultLng },
                zoom: this.defaultZoom,
                mapTypeControl: false,
                streetViewControl: false,
            });
        },
        async fetchProfiles() {
            this.profilesLoading = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/ubicaciones/profiles`);
                this.profiles = data.profiles || [];
                this.renderMarkers();
            } catch (e) {
                this.error = e.response?.data?.message || e.message;
            } finally {
                this.profilesLoading = false;
            }
        },
        renderMarkers() {
            if (!mapInstance) return;

            // Limpiar marcadores previos
            markerMap.forEach(m => m.setMap(null));
            markerMap = new Map();

            for (const p of this.profiles) {
                const loc = p.last_location;
                if (!loc || loc.lat == null || loc.lng == null) continue;

                const marker = new google.maps.Marker({
                    position: { lat: parseFloat(loc.lat), lng: parseFloat(loc.lng) },
                    map: mapInstance,
                    title: `${p.name} — ${this.relativeTime(loc.recorded_at)}`,
                    label: {
                        text: this.initials(p.name),
                        color: '#fff',
                        fontWeight: '600',
                        fontSize: '11px',
                    },
                    icon: {
                        path: google.maps.SymbolPath.CIRCLE,
                        scale: 14,
                        fillColor: this.markerColor(p),
                        fillOpacity: 1,
                        strokeColor: '#fff',
                        strokeWeight: 2,
                    },
                });

                const info = new google.maps.InfoWindow({
                    content: `<div style="font-size:13px"><strong>${this.escape(p.name)}</strong><br>
                        <span style="color:#6c757d">${p.profile_type || ''} · ${p.age || '?'} años</span><br>
                        <small>${this.relativeTime(loc.recorded_at)}</small></div>`,
                });
                marker.addListener('click', () => {
                    infoMap.forEach(i => i.close());
                    info.open(mapInstance, marker);
                    this.selectProfile(p);
                });

                markerMap.set(p.id, marker);
                infoMap.set(p.id, info);
            }

            // Fit bounds si hay markers
            if (markerMap.size > 0) {
                const bounds = new google.maps.LatLngBounds();
                markerMap.forEach(m => bounds.extend(m.getPosition()));
                mapInstance.fitBounds(bounds, 80);
                if (markerMap.size === 1) mapInstance.setZoom(15);
            }
        },
        async selectProfile(p) {
            this.selected = p;
            this.clearHistory();
            if (p.last_location && mapInstance) {
                mapInstance.panTo({
                    lat: parseFloat(p.last_location.lat),
                    lng: parseFloat(p.last_location.lng),
                });
                mapInstance.setZoom(15);
            }
            await this.loadHistory(false);
            if (this.showGeofences) await this.drawGeofences();
        },
        async loadHistory(drawLine) {
            if (!this.selected) return;
            this.historyLoading = true;
            try {
                const { data } = await axios.get(`${this.baseUrl}/ubicaciones/history/${this.selected.id}`);
                this.history = data.history || [];
                if (drawLine) this.drawHistoryPolyline();
            } catch (e) {
                this.error = e.response?.data?.message || e.message;
            } finally {
                this.historyLoading = false;
            }
        },
        drawHistoryPolyline() {
            if (!mapInstance || !this.history.length) return;
            if (historyPolyline) historyPolyline.setMap(null);

            const path = this.history.map(h => ({ lat: parseFloat(h.lat), lng: parseFloat(h.lng) }));
            historyPolyline = new google.maps.Polyline({
                path,
                geodesic: true,
                strokeColor: '#0d6efd',
                strokeOpacity: 0.85,
                strokeWeight: 3,
                map: mapInstance,
            });
            this.historyLine = historyPolyline;

            const bounds = new google.maps.LatLngBounds();
            path.forEach(p => bounds.extend(p));
            mapInstance.fitBounds(bounds, 60);
        },
        clearHistory() {
            if (historyPolyline) { historyPolyline.setMap(null); historyPolyline = null; }
            this.historyLine = null;
        },
        async drawGeofences() {
            // Limpiar previas
            geofenceShapes.forEach(s => s.setMap(null));
            geofenceShapes = [];

            if (!this.showGeofences || !mapInstance) return;

            // Cargar geofences del perfil seleccionado (o todas si no hay)
            try {
                const params = this.selected ? { profile_id: this.selected.id } : {};
                const { data } = await axios.get(`${this.baseUrl}/geofences/data`, { params });
                this.geofences = (data.list || []).filter(g => g.active);
            } catch (e) {
                return;
            }

            for (const g of this.geofences) {
                const circle = new google.maps.Circle({
                    strokeColor: '#28a745',
                    strokeOpacity: 0.7,
                    strokeWeight: 2,
                    fillColor: '#28a745',
                    fillOpacity: 0.12,
                    map: mapInstance,
                    center: { lat: parseFloat(g.lat), lng: parseFloat(g.lng) },
                    radius: parseInt(g.radius_meters, 10),
                });
                geofenceShapes.push(circle);
            }
        },
        async refresh(silent = false) {
            this.refreshing = true;
            try {
                await this.fetchProfiles();
                if (this.selected) {
                    // Reseleccionar para mantener referencias frescas
                    const fresh = this.profiles.find(x => x.id === this.selected.id);
                    if (fresh) this.selected = fresh;
                    await this.loadHistory(!!this.historyLine);
                }
                if (this.showGeofences) await this.drawGeofences();
            } catch (e) {
                if (!silent) this.error = e.response?.data?.message || e.message;
            } finally {
                this.refreshing = false;
            }
        },
        centerOn(loc) {
            if (!mapInstance) return;
            mapInstance.panTo({ lat: parseFloat(loc.lat), lng: parseFloat(loc.lng) });
            mapInstance.setZoom(17);
        },
        historySlice(n) { return this.history.slice(-n).reverse(); },
        markerColor(p) {
            return ({
                nino: '#0dcaf0',
                preadolescente: '#fd7e14',
                adolescente: '#6f42c1',
            })[p.profile_type] || '#0d6efd';
        },
        initials(name) {
            if (!name) return '?';
            return name.split(/\s+/).map(s => s[0]).slice(0, 2).join('').toUpperCase();
        },
        batteryTextClass(level) {
            if (level > 50) return 'text-success';
            if (level > 20) return 'text-warning';
            return 'text-danger';
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
            return d.toLocaleString('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        },
        escape(s) { return (s || '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); },
    },
};
</script>

<style scoped>
.mf-map { width: 100%; height: 560px; border-radius: 6px; background: #f1f3f5; }
.map-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 560px; }

.profile-list { max-height: 620px; overflow-y: auto; }
.profile-item {
    display: flex; align-items: center; gap: 0.6rem;
    width: 100%; padding: 0.6rem 0.75rem;
    border: 0; border-bottom: 1px solid #f1f3f5;
    background: transparent; text-align: left;
    cursor: pointer; transition: background 0.1s;
}
.profile-item:hover { background: rgba(13, 110, 253, 0.06); }
.profile-item.active { background: rgba(13, 110, 253, 0.10); border-left: 3px solid #0d6efd; padding-left: calc(0.75rem - 3px); }

.profile-avatar {
    width: 38px; height: 38px;
    background: rgba(13, 110, 253, 0.15); color: #0d6efd;
    font-weight: 600; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden; flex-shrink: 0;
}
.profile-avatar img { width: 100%; height: 100%; object-fit: cover; }

.profile-info { flex: 1; min-width: 0; font-size: 13px; }
.profile-meta { font-size: 12px; min-width: 60px; }

.btn-tour-help {
    position: fixed; bottom: 30px; right: 30px; z-index: 999;
    width: 48px; height: 48px; border-radius: 50%;
    background: #0d6efd; color: white; border: none;
    font-size: 20px; box-shadow: 0 4px 12px rgba(13,110,253,0.4);
    cursor: pointer; transition: transform 0.2s;
}
.btn-tour-help:hover { transform: scale(1.1); }
</style>
