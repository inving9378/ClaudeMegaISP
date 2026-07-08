<template>
    <div class="megafamilia-geofences mt-3">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <h5 class="m-0">
                <i class="fa fa-draw-polygon me-2 text-primary"></i>
                Geofences
            </h5>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" :disabled="loading" @click="fetch">
                    <i class="fa fa-sync" :class="{ 'fa-spin': loading }"></i>
                </button>
                <button id="btn-nueva-geofence" class="btn btn-sm btn-primary" :disabled="!apiReady" @click="openCreate">
                    <i class="fa fa-plus me-1"></i> Nueva Geofence
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Perfil</label>
                        <select v-model="filters.profile_id" class="form-select form-select-sm" @change="fetch">
                            <option value="">Todos</option>
                            <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Estado</label>
                        <select v-model="filters.active" class="form-select form-select-sm" @change="fetch">
                            <option value="">Todas</option>
                            <option value="true">Activas</option>
                            <option value="false">Inactivas</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="apiError" class="alert alert-warning small">
            <i class="fa fa-exclamation-triangle me-1"></i>
            No se pudo cargar Google Maps. Verifica las credenciales en
            <a :href="`${rootUrl}/configuracion/credenciales-google-maps`">Configuración → Google Maps</a>.
        </div>

        <div class="row">
            <!-- Lista -->
            <div class="col-lg-5 mb-3 mf-geo-lista">
                <div v-if="loading && rows.length === 0" class="text-muted py-4 text-center">Cargando…</div>
                <div v-else-if="rows.length === 0" class="text-muted py-4 text-center">
                    <i class="fa fa-draw-polygon fa-2x mb-2 d-block"></i>
                    Sin geofences registradas.
                </div>
                <div v-else>
                    <div
                        v-for="g in rows"
                        :key="g.id"
                        class="card geofence-card mb-2"
                        :class="{ active: selected && selected.id === g.id, inactive: !g.active }"
                        @click="selectGeofence(g)">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start mb-1">
                                <div>
                                    <strong>{{ g.name }}</strong>
                                    <div class="text-muted small">
                                        <i class="fa fa-child me-1"></i>{{ g.profile?.name || '—' }}
                                    </div>
                                </div>
                                <span class="badge" :class="g.active ? 'bg-success' : 'bg-secondary'"
                                      role="button" @click.stop="toggleActive(g)"
                                      :title="g.active ? 'Click para desactivar' : 'Click para activar'">
                                    {{ g.active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </div>
                            <div class="d-flex flex-wrap gap-1 mb-1">
                                <span class="badge bg-light text-dark">
                                    <i class="fa fa-circle-notch me-1"></i> Círculo · {{ g.radius_meters }} m
                                </span>
                                <span class="badge bg-info-subtle text-info">{{ typeLabel(g.type) }}</span>
                                <span class="badge bg-light text-dark" :title="triggerLabel(g)">
                                    {{ triggerIcon(g) }} {{ triggerLabel(g) }}
                                </span>
                            </div>
                            <div v-if="g.address" class="text-muted small">
                                <i class="fa fa-map-pin me-1"></i>{{ g.address }}
                            </div>
                            <div class="d-flex justify-content-end gap-1 mt-2" @click.stop>
                                <button class="btn btn-sm btn-outline-primary" @click="openEdit(g)" :disabled="!apiReady">
                                    <i class="fa fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" @click="destroy(g)">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mapa preview -->
            <div class="col-lg-7 mb-3">
                <div class="card">
                    <div class="card-body p-2">
                        <div v-if="!apiReady && !apiError" class="map-loading">
                            <div class="spinner-border text-primary"></div>
                            <p class="text-muted mt-2 mb-0">Cargando Google Maps…</p>
                        </div>
                        <div ref="previewEl" class="geo-map"></div>
                    </div>
                </div>
            </div>
        </div>

        <button @click="launchTour" class="btn-tour-help" title="Tour guiado">
            <i class="fas fa-question-circle"></i>
        </button>

        <!-- Modal crear/editar -->
        <div v-if="form.open" class="mf-modal-overlay" @click.self="closeForm">
            <div class="mf-modal-panel" style="max-width:760px;">
                <div class="mf-modal-header">
                    <h5 class="m-0">{{ form.id ? 'Editar geofence' : 'Nueva geofence' }}</h5>
                    <button class="btn-close" @click="closeForm"></button>
                </div>
                <div class="mf-modal-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label small mb-1">Buscar dirección</label>
                            <div class="input-group input-group-sm">
                                <input
                                    v-model="form.searchAddress"
                                    type="search"
                                    class="form-control"
                                    placeholder="Ej. Av. Reforma 100, CDMX"
                                    @keyup.enter="geocode" />
                                <button class="btn btn-outline-secondary" :disabled="!form.searchAddress" @click="geocode">
                                    <i class="fa fa-search"></i>
                                </button>
                            </div>
                            <small class="text-muted">O haz click en el mapa para colocar el centro.</small>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small mb-1">Perfil <span class="text-danger">*</span></label>
                            <select v-model.number="form.profile_id" class="form-select form-select-sm">
                                <option :value="null">Selecciona…</option>
                                <option v-for="p in profiles" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div ref="modalMapEl" class="geo-map-modal"></div>
                            <small v-if="form.lat && form.lng" class="text-muted">
                                <strong>Centro:</strong> {{ Number(form.lat).toFixed(5) }}, {{ Number(form.lng).toFixed(5) }}
                            </small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Nombre <span class="text-danger">*</span></label>
                            <input v-model="form.name" class="form-control" placeholder="Casa, Escuela…" />
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Tipo / etiqueta</label>
                            <select v-model="form.type" class="form-select">
                                <option value="home">Casa</option>
                                <option value="school">Escuela</option>
                                <option value="family">Familiar</option>
                                <option value="work">Trabajo</option>
                                <option value="park">Parque</option>
                                <option value="other">Otro</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1">Dirección (opcional)</label>
                            <input v-model="form.address" class="form-control" />
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small mb-1">
                                Radio: <strong>{{ form.radius_meters }} m</strong>
                            </label>
                            <input v-model.number="form.radius_meters" type="range" min="100" max="5000" step="50"
                                   class="form-range" @input="updateRadiusCircle" />
                        </div>
                        <div class="col-md-4 mf-geo-trigger">
                            <label class="form-label small mb-1">Tipo de alerta</label>
                            <select v-model="form.trigger_type" class="form-select">
                                <option value="enter">Entrada</option>
                                <option value="exit">Salida</option>
                                <option value="both">Ambas</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="geo-active" v-model="form.active" />
                                <label class="form-check-label" for="geo-active">Activa</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mf-modal-footer">
                    <button class="btn btn-outline-secondary" @click="closeForm">Cancelar</button>
                    <button class="btn btn-primary" :disabled="!canSave || saving" @click="save">
                        <i class="fa fa-save me-1"></i>
                        {{ saving ? 'Guardando…' : (form.id ? 'Guardar cambios' : 'Crear geofence') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import { startTour, shouldShowTour } from './MegaFamiliaTour.js';

let previewMap = null;
let previewCircle = null;
let modalMap = null;
let modalCircle = null;
let modalMarker = null;
let modalGeocoder = null;

const TYPE_LABELS = {
    home: 'Casa', school: 'Escuela', family: 'Familiar',
    work: 'Trabajo', park: 'Parque', other: 'Otro',
};

function blankForm() {
    return {
        open: false, id: null,
        profile_id: null,
        name: '', address: '', type: 'home',
        lat: null, lng: null, radius_meters: 500,
        trigger_type: 'both',
        active: true,
        searchAddress: '',
    };
}

export default {
    name: 'MegaFamiliaGeofences',
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
            defaultZoom: 12,

            loading: false,
            saving: false,
            rows: [],
            profiles: [],
            filters: { profile_id: '', active: '' },
            selected: null,
            form: blankForm(),
        };
    },
    computed: {
        rootUrl() {
            return this.baseUrl.replace(/\/megafamilia$/, '');
        },
        canSave() {
            return !!(this.form.profile_id && this.form.name && this.form.lat && this.form.lng && this.form.radius_meters);
        },
    },
    async mounted() {
        await this.loadMapsSDK();
        if (this.apiReady) this.initPreviewMap();
        await this.fetch();
        if (shouldShowTour('geofences')) setTimeout(() => startTour('geofences'), 1500);
    },
    beforeUnmount() {
        if (previewCircle) { previewCircle.setMap(null); previewCircle = null; }
        if (modalCircle)   { modalCircle.setMap(null);   modalCircle = null; }
        previewMap = null;
        modalMap = null;
    },
    methods: {
        launchTour() { startTour('geofences'); },
        async loadMapsSDK() {
            try {
                const { data } = await axios.get(`${this.rootUrl}/configuracion/credenciales-google-maps/render-config`);
                const cfg = Array.isArray(data) ? data[0] : data;
                if (!cfg?.api_key) { this.apiError = true; return; }
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
                    s.src = `https://maps.googleapis.com/maps/api/js?key=${this.apiKey}&libraries=geometry,places`;
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
        initPreviewMap() {
            if (!this.$refs.previewEl) return;
            previewMap = new google.maps.Map(this.$refs.previewEl, {
                center: { lat: this.defaultLat, lng: this.defaultLng },
                zoom: this.defaultZoom,
                mapTypeControl: false, streetViewControl: false,
            });
        },
        async fetch() {
            this.loading = true;
            try {
                const params = {};
                if (this.filters.profile_id) params.profile_id = this.filters.profile_id;
                if (this.filters.active)     params.active     = this.filters.active;
                const { data } = await axios.get(`${this.baseUrl}/geofences/data`, { params });
                this.rows = data.list || [];
                this.profiles = data.profiles || [];
            } catch (e) {
                console.error('[MF/Geofences]', e);
            } finally {
                this.loading = false;
            }
        },
        selectGeofence(g) {
            this.selected = g;
            if (!previewMap) return;
            if (previewCircle) previewCircle.setMap(null);
            previewCircle = new google.maps.Circle({
                strokeColor: '#0d6efd', strokeOpacity: 0.8, strokeWeight: 2,
                fillColor: '#0d6efd', fillOpacity: 0.15,
                center: { lat: parseFloat(g.lat), lng: parseFloat(g.lng) },
                radius: parseInt(g.radius_meters, 10),
                map: previewMap,
            });
            previewMap.panTo({ lat: parseFloat(g.lat), lng: parseFloat(g.lng) });
            previewMap.fitBounds(previewCircle.getBounds(), 30);
        },
        openCreate() {
            this.form = { ...blankForm(), open: true };
            this.$nextTick(() => this.initModalMap());
        },
        openEdit(g) {
            const trigger = g.alert_on_enter && g.alert_on_exit ? 'both'
                         : g.alert_on_enter ? 'enter'
                         : g.alert_on_exit  ? 'exit' : 'both';
            this.form = {
                ...blankForm(),
                open: true,
                id: g.id,
                profile_id: g.profile_id,
                name: g.name,
                address: g.address || '',
                type: g.type || 'home',
                lat: parseFloat(g.lat),
                lng: parseFloat(g.lng),
                radius_meters: parseInt(g.radius_meters, 10),
                trigger_type: trigger,
                active: !!g.active,
            };
            this.$nextTick(() => this.initModalMap());
        },
        closeForm() {
            this.form.open = false;
            if (modalCircle) { modalCircle.setMap(null); modalCircle = null; }
            if (modalMarker) { modalMarker.setMap(null); modalMarker = null; }
            modalMap = null;
        },
        initModalMap() {
            if (!this.apiReady || !this.$refs.modalMapEl) return;
            const center = this.form.lat && this.form.lng
                ? { lat: this.form.lat, lng: this.form.lng }
                : { lat: this.defaultLat, lng: this.defaultLng };

            modalMap = new google.maps.Map(this.$refs.modalMapEl, {
                center, zoom: this.form.lat ? 15 : this.defaultZoom,
                mapTypeControl: false, streetViewControl: false,
            });
            modalGeocoder = new google.maps.Geocoder();

            if (this.form.lat && this.form.lng) this.setMarkerAndCircle(this.form.lat, this.form.lng);

            modalMap.addListener('click', (e) => {
                const ll = e.latLng;
                this.form.lat = ll.lat();
                this.form.lng = ll.lng();
                this.setMarkerAndCircle(this.form.lat, this.form.lng);
            });
        },
        setMarkerAndCircle(lat, lng) {
            if (!modalMap) return;
            if (modalMarker) modalMarker.setMap(null);
            if (modalCircle) modalCircle.setMap(null);
            modalMarker = new google.maps.Marker({
                position: { lat, lng }, map: modalMap,
            });
            modalCircle = new google.maps.Circle({
                strokeColor: '#0d6efd', strokeOpacity: 0.8, strokeWeight: 2,
                fillColor: '#0d6efd', fillOpacity: 0.15,
                center: { lat, lng },
                radius: this.form.radius_meters,
                map: modalMap,
            });
        },
        updateRadiusCircle() {
            if (modalCircle) modalCircle.setRadius(this.form.radius_meters);
        },
        geocode() {
            if (!modalGeocoder || !this.form.searchAddress) return;
            modalGeocoder.geocode({ address: this.form.searchAddress }, (results, status) => {
                if (status !== 'OK' || !results?.length) {
                    alert('No se encontró la dirección.');
                    return;
                }
                const r = results[0];
                this.form.address = r.formatted_address;
                this.form.lat = r.geometry.location.lat();
                this.form.lng = r.geometry.location.lng();
                modalMap.panTo(r.geometry.location);
                modalMap.setZoom(15);
                this.setMarkerAndCircle(this.form.lat, this.form.lng);
            });
        },
        async save() {
            this.saving = true;
            try {
                const payload = {
                    profile_id: this.form.profile_id,
                    name: this.form.name,
                    address: this.form.address || null,
                    type: this.form.type,
                    lat: this.form.lat,
                    lng: this.form.lng,
                    radius_meters: this.form.radius_meters,
                    trigger_type: this.form.trigger_type,
                    active: !!this.form.active,
                };
                const headers = { 'X-CSRF-TOKEN': this.csrfToken };
                if (this.form.id) {
                    await axios.put(`${this.baseUrl}/geofences/${this.form.id}`, payload, { headers });
                } else {
                    await axios.post(`${this.baseUrl}/geofences`, payload, { headers });
                }
                this.closeForm();
                await this.fetch();
            } catch (e) {
                alert(e.response?.data?.message || e.message);
            } finally {
                this.saving = false;
            }
        },
        async destroy(g) {
            if (!confirm(`¿Eliminar la geofence "${g.name}"?`)) return;
            try {
                await axios.delete(`${this.baseUrl}/geofences/${g.id}`, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                if (this.selected?.id === g.id) {
                    this.selected = null;
                    if (previewCircle) { previewCircle.setMap(null); previewCircle = null; }
                }
                await this.fetch();
            } catch (e) { alert(e.response?.data?.message || e.message); }
        },
        async toggleActive(g) {
            try {
                const { data } = await axios.post(`${this.baseUrl}/geofences/${g.id}/toggle`, {}, {
                    headers: { 'X-CSRF-TOKEN': this.csrfToken },
                });
                g.active = !!data.active;
            } catch (e) { alert(e.response?.data?.message || e.message); }
        },
        typeLabel(t) { return TYPE_LABELS[t] || t || '—'; },
        triggerIcon(g) {
            if (g.alert_on_enter && g.alert_on_exit) return '↔';
            if (g.alert_on_enter) return '→';
            if (g.alert_on_exit)  return '←';
            return '—';
        },
        triggerLabel(g) {
            if (g.alert_on_enter && g.alert_on_exit) return 'Entrada y salida';
            if (g.alert_on_enter) return 'Entrada';
            if (g.alert_on_exit)  return 'Salida';
            return 'Sin alertas';
        },
    },
};
</script>

<style scoped>
.geo-map { width: 100%; height: 520px; border-radius: 6px; background: #f1f3f5; }
.geo-map-modal { width: 100%; height: 320px; border-radius: 6px; background: #f1f3f5; border: 1px solid #e9ecef; }
.map-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 520px; }

.geofence-card { cursor: pointer; border: 2px solid #e9ecef; transition: border-color 0.1s, transform 0.1s; }
.geofence-card:hover { transform: translateY(-1px); }
.geofence-card.active { border-color: #0d6efd; background: rgba(13, 110, 253, 0.04); }
.geofence-card.inactive { opacity: 0.7; }

.mf-modal-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center; z-index: 9999;
}
.mf-modal-panel {
    background: #fff; border-radius: 8px; width: min(760px, 92vw);
    max-height: 92vh; overflow: hidden; display: flex; flex-direction: column;
}
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
