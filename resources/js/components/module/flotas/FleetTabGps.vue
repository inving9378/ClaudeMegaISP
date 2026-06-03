<template>
    <section>
        <!-- CON GPS -->
        <div v-if="vehicle.has_gps" class="flt-gps-track">
            <div class="row g-3">
                <!-- Mapa -->
                <div class="col-lg-7">
                    <div class="flt-gps-mapbox">
                        <div ref="mapEl" class="flt-gps-map"></div>
                        <div class="flt-gps-overlay">
                            <span v-if="refreshing" class="badge bg-primary"><i class="bi bi-arrow-repeat spin me-1"></i>actualizando…</span>
                            <button class="btn btn-sm btn-light" :title="paused ? 'Reanudar' : 'Pausar'" @click="togglePause">
                                <i class="bi" :class="paused ? 'bi-play-circle' : 'bi-pause-circle'"></i>
                            </button>
                        </div>
                        <div v-if="mapError" class="flt-gps-errbar">{{ mapError }}</div>
                    </div>
                    <div class="flt-gps-ranges mt-2">
                        <div class="btn-group btn-group-sm">
                            <button v-for="r in ranges" :key="r.key" class="btn"
                                :class="range === r.key ? 'btn-primary' : 'btn-outline-secondary'"
                                @click="setRange(r.key)">{{ r.label }}</button>
                        </div>
                        <button class="btn btn-sm btn-link ms-2" @click="refresh"><i class="bi bi-arrow-clockwise me-1"></i>Refrescar</button>
                        <div v-if="range === 'custom'" class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                            <input type="datetime-local" class="form-control form-control-sm" style="max-width:210px" v-model="custom.from" />
                            <span>→</span>
                            <input type="datetime-local" class="form-control form-control-sm" style="max-width:210px" v-model="custom.to" />
                            <button class="btn btn-sm btn-primary" @click="loadHistory">Aplicar</button>
                        </div>
                    </div>
                </div>
                <!-- Panel -->
                <div class="col-lg-5">
                    <div class="flt-gps-card">
                        <h6><i class="bi bi-speedometer2 me-1 text-success"></i>Estado actual</h6>
                        <div v-if="last">
                            <div class="flt-gps-row"><span>Último ping</span><strong>{{ lastSeen }}</strong></div>
                            <div class="flt-gps-row"><span>Velocidad</span><strong>{{ Math.round(last.speed) }} km/h</strong></div>
                            <div class="flt-gps-row"><span>Rumbo</span><strong>{{ Math.round(last.heading) }}° {{ cardinal(last.heading) }}</strong></div>
                            <div class="flt-gps-row"><span>Estado</span><span class="badge" :style="{ background: statusColor }">{{ statusLabel }}</span></div>
                        </div>
                        <div v-else class="text-muted small">Sin posiciones registradas aún.</div>
                    </div>
                    <div class="flt-gps-card">
                        <h6><i class="bi bi-cpu me-1 text-info"></i>Dispositivo</h6>
                        <div class="flt-gps-row"><span>Marca</span><strong>{{ (device && device.brand) || vehicle.gps_brand || '—' }}</strong></div>
                        <div class="flt-gps-row"><span>IMEI</span><strong>{{ (device && device.imei) || vehicle.gps_imei || '—' }}</strong></div>
                        <div class="flt-gps-row"><span>SIM</span><strong>{{ (device && device.sim_number) || vehicle.gps_sim || '—' }}</strong></div>
                    </div>
                    <div class="flt-gps-card">
                        <h6><i class="bi bi-graph-up me-1 text-primary"></i>Estadísticas hoy</h6>
                        <div class="flt-gps-row"><span>Km recorridos</span><strong>{{ stats ? stats.km : 0 }} km</strong></div>
                        <div class="flt-gps-row"><span>Tiempo en movimiento</span><strong>{{ stats ? stats.moving_minutes : 0 }} min</strong></div>
                        <div class="flt-gps-row"><span>Paradas</span><strong>{{ stats ? stats.stops : 0 }}</strong></div>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-secondary btn-sm" @click="center"><i class="bi bi-crosshair me-1"></i>Centrar mapa</button>
                        <button class="btn btn-outline-secondary btn-sm" :disabled="!history.length" @click="downloadGpx"><i class="bi bi-download me-1"></i>Descargar GPX</button>
                    </div>
                </div>
            </div>

            <!-- Eventos de geocercas -->
            <div class="flt-gps-card flt-geo-events mt-3">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <h6 class="mb-0"><i class="bi bi-bounding-box-circles me-1 text-primary"></i>Eventos de geocercas recientes</h6>
                    <div class="d-flex align-items-center gap-2">
                        <a v-if="rulesCount > 0" :href="`${baseUrl}/reglas`" class="badge bg-primary text-decoration-none">
                            <i class="bi bi-funnel me-1"></i>{{ rulesCount }} regla(s) activa(s)
                        </a>
                        <a v-else :href="`${baseUrl}/reglas`" class="text-muted small text-decoration-none">
                            <i class="bi bi-funnel me-1"></i>Sin reglas — recibes todas las alertas
                        </a>
                        <button class="btn btn-sm btn-outline-primary" @click="openAlertModal">
                            <i class="bi bi-bell me-1"></i>Configurar mis alertas
                        </button>
                    </div>
                </div>
                <div v-if="!geoEvents.length" class="text-muted small">
                    <i class="bi bi-info-circle me-1"></i>Sin eventos de geocercas registrados aún.
                </div>
                <div v-else class="flt-geo-ev-list">
                    <button type="button" class="flt-geo-ev" v-for="e in geoEvents" :key="e.id"
                            :class="{ disabled: e.lat == null }" @click="focusEvent(e)">
                        <i class="bi" :class="e.type === 'enter' ? 'bi-arrow-right-circle-fill text-success' : 'bi-arrow-left-circle-fill text-warning'"></i>
                        <span class="flex-grow-1 text-truncate">
                            {{ e.type === 'enter' ? 'Entró a' : 'Salió de' }} <strong>{{ e.geofence_name }}</strong>
                        </span>
                        <span class="text-muted small flt-geo-ev-time">{{ relTime(e.occurred_at) }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- SIN GPS: wizard de activación -->
        <div v-else class="flt-empty">
            <i class="bi bi-broadcast-pin"></i>
            <h6>Este vehículo no tiene GPS</h6>
            <p class="text-muted">Activa el tracking registrando el dispositivo instalado.</p>
            <button v-if="!showActivateForm" class="btn btn-primary" @click="showActivateForm = true">
                <i class="bi bi-plus-lg me-1"></i>Activar tracking GPS
            </button>
            <div v-if="showActivateForm" class="flt-inline-form mt-3 text-start mx-auto" style="max-width:640px">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Marca</label>
                        <select class="form-select" v-model="gpsForm.brand">
                            <option value="ruptela">Ruptela</option>
                            <option value="concox">Concox</option>
                            <option value="gt06_generic">GT06 genérico</option>
                            <option value="mock">Mock (para pruebas)</option>
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label fw-semibold">Modelo</label><input class="form-control" v-model="gpsForm.model" /></div>
                    <div class="col-md-6"><label class="form-label fw-semibold">IMEI</label><input class="form-control" v-model="gpsForm.imei" placeholder="Obligatorio y único" /></div>
                    <div class="col-md-3"><label class="form-label fw-semibold">SIM</label><input class="form-control" v-model="gpsForm.sim_number" /></div>
                    <div class="col-md-3"><label class="form-label fw-semibold">Operadora</label><input class="form-control" v-model="gpsForm.sim_carrier" /></div>
                </div>
                <div class="text-end mt-3">
                    <button class="btn btn-sm btn-outline-secondary me-2" @click="showActivateForm = false">Cancelar</button>
                    <button class="btn btn-sm btn-primary" :disabled="savingGps" @click="activateGps">
                        <i class="bi bi-check2 me-1"></i>{{ savingGps ? 'Guardando…' : 'Activar' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: mis alertas -->
        <div v-if="alertModal" class="modal fade show flt-delete-modal" tabindex="-1" style="display:block" @click.self="alertModal = false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-bell me-2 text-primary"></i>Mis alertas de este vehículo</h5>
                        <button type="button" class="btn-close" @click="alertModal = false"></button>
                    </div>
                    <div class="modal-body">
                        <div v-if="alertPrefs.loading" class="text-center text-muted py-3">
                            <div class="spinner-border spinner-border-sm me-2"></div>Cargando…
                        </div>
                        <template v-else>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="alertActive" v-model="alertPrefs.active">
                                <label class="form-check-label fw-semibold" for="alertActive">Recibir alertas de este vehículo</label>
                            </div>
                            <fieldset :disabled="!alertPrefs.active" :class="{ 'opacity-50': !alertPrefs.active }">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Geocercas</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="alertAllGeo" v-model="alertPrefs.all_geofences">
                                        <label class="form-check-label" for="alertAllGeo">Todas las geocercas del vehículo</label>
                                    </div>
                                    <select v-if="!alertPrefs.all_geofences" class="form-select form-select-sm mt-2" multiple size="4" v-model="alertPrefs.geofence_ids">
                                        <option v-for="g in alertPrefs.geofences" :key="g.id" :value="g.id">{{ g.name }}</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Tipos de evento</label>
                                    <div class="d-flex gap-3">
                                        <div class="form-check"><input class="form-check-input" type="checkbox" id="evEnter" value="enter" v-model="alertPrefs.event_types"><label class="form-check-label" for="evEnter">Entrada</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" id="evExit" value="exit" v-model="alertPrefs.event_types"><label class="form-check-label" for="evExit">Salida</label></div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label fw-semibold">Canales</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="chEmail" value="email" v-model="alertPrefs.channels">
                                        <label class="form-check-label" for="chEmail">Email <span class="text-muted small">→ {{ alertPrefs.user_email || 'sin email' }}</span></label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="chWa" value="whatsapp" v-model="alertPrefs.channels">
                                        <label class="form-check-label" for="chWa">WhatsApp <span class="text-muted small">→ {{ alertPrefs.user_phone || 'sin teléfono' }}</span></label>
                                    </div>
                                </div>
                            </fieldset>
                        </template>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" @click="alertModal = false">Cancelar</button>
                        <button class="btn btn-primary" :disabled="alertPrefs.saving" @click="saveAlertPrefs">
                            <i class="bi bi-check2 me-1"></i>{{ alertPrefs.saving ? 'Guardando…' : 'Guardar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="alertModal" class="modal-backdrop fade show"></div>

        <!-- Modal: instrucciones GPS físico -->
        <div v-if="instructions" class="modal fade show flt-delete-modal" tabindex="-1" style="display:block" @click.self="instructions = null">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-broadcast me-2 text-primary"></i>Configura tu GPS</h5>
                        <button type="button" class="btn-close" @click="instructions = null"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Dispositivo registrado. Configura el GPS apuntando al servidor:</p>
                        <table class="table table-sm">
                            <tbody>
                                <tr><td class="text-muted">Servidor (IP)</td><td><strong>{{ instructions.ip }}</strong></td></tr>
                                <tr><td class="text-muted">Puerto</td><td><strong>{{ instructions.port }}</strong></td></tr>
                                <tr><td class="text-muted">Protocolo</td><td><strong>{{ instructions.protocol }}</strong></td></tr>
                                <tr><td class="text-muted">IMEI</td><td><strong>{{ instructions.imei }}</strong></td></tr>
                            </tbody>
                        </table>
                        <div class="alert alert-light border small mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Usa el <strong>Ruptela Configurator</strong>. Al conectarse por primera vez el estado pasará a <em>activo</em>.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-primary" @click="instructions = null">Entendido</button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="instructions" class="modal-backdrop fade show"></div>
    </section>
</template>

<script>
import { ref, reactive, computed, watch, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export default {
    name: 'FleetTabGps',
    props: {
        vehicle:   { type: Object, required: true },
        vehicleId: { type: [String, Number], required: true },
        baseUrl:   { type: String, default: '/flotas' },
        active:    { type: Boolean, default: false },
    },
    emits: ['reload', 'toast'],
    setup(props, { emit }) {
        const GPS_COLORS = { moving: '#22c55e', stopped: '#f59e0b', idle: '#9ca3af', offline: '#ef4444' };
        const GPS_LABELS = { moving: 'En movimiento', stopped: 'Detenido', idle: 'Inactivo', offline: 'Sin señal' };

        const mapEl     = ref(null);
        const last      = ref(null);
        const device    = ref(null);
        const stats     = ref(null);
        const history   = ref([]);
        const liveStatus = ref('offline');
        const range     = ref('24h');
        const custom    = reactive({ from: '', to: '' });
        const refreshing = ref(false);
        const paused    = ref(false);
        const mapError  = ref('');
        const geoEvents = ref([]);
        const rulesCount = ref(0);

        const ranges = [
            { key: '6h', label: 'Últimas 6h' }, { key: '24h', label: '24h' },
            { key: '7d', label: '7 días' },      { key: 'custom', label: 'Personalizado' },
        ];

        const statusColor = computed(() => GPS_COLORS[liveStatus.value] || GPS_COLORS.offline);
        const statusLabel = computed(() => GPS_LABELS[liveStatus.value] || GPS_LABELS.offline);
        const lastSeen    = computed(() => {
            const iso = last.value?.recorded_at;
            if (!iso) return '—';
            const diff = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
            if (diff < 1) return 'ahora';
            if (diff < 60) return `hace ${diff} min`;
            return `hace ${Math.round(diff / 60)} h`;
        });

        function cardinal(deg) {
            return ['N','NE','E','SE','S','SO','O','NO'][Math.round((deg % 360) / 45) % 8];
        }
        function relTime(iso) {
            if (!iso) return '—';
            const diff = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
            if (diff < 1) return 'ahora';
            if (diff < 60) return `hace ${diff} min`;
            const h = Math.round(diff / 60);
            return h < 24 ? `hace ${h} h` : `hace ${Math.round(h / 24)} d`;
        }

        function rangeBounds() {
            const to = new Date();
            let from = new Date(to.getTime() - 24 * 3600 * 1000);
            if (range.value === '6h') from = new Date(to.getTime() - 6 * 3600 * 1000);
            else if (range.value === '7d') from = new Date(to.getTime() - 7 * 24 * 3600 * 1000);
            else if (range.value === 'custom') return { from: custom.from ? new Date(custom.from).toISOString() : from.toISOString(), to: custom.to ? new Date(custom.to).toISOString() : to.toISOString() };
            return { from: from.toISOString(), to: to.toISOString() };
        }

        let map = null; let marker = null; let path = null; let timer = null;

        function ensureMap() {
            if (!props.vehicle?.has_gps) return;
            nextTick(() => {
                if (!mapEl.value) return;
                if (!map) {
                    map = L.map(mapEl.value).setView([19.4326, -99.1332], 12);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
                    refresh();
                    timer = setInterval(() => { if (!paused.value) loadStatus(); }, 30000);
                } else { map.invalidateSize(); }
            });
        }

        function updateMarker() {
            if (!map || !last.value || last.value.lat == null) return;
            const ll = [last.value.lat, last.value.lng];
            if (!marker) {
                marker = L.circleMarker(ll, { radius: 9, color: '#fff', weight: 2, fillColor: statusColor.value, fillOpacity: 1 }).addTo(map);
            } else { marker.setLatLng(ll); marker.setStyle({ fillColor: statusColor.value }); }
        }

        function drawPath() {
            if (!map) return;
            const latlngs = history.value.filter((p) => p.lat != null).map((p) => [p.lat, p.lng]);
            if (path) { map.removeLayer(path); path = null; }
            if (latlngs.length) {
                path = L.polyline(latlngs, { color: '#2563eb', weight: 4, opacity: 0.75 }).addTo(map);
                map.fitBounds(path.getBounds(), { padding: [30, 30], maxZoom: 16 });
            } else if (last.value?.lat != null) { map.setView([last.value.lat, last.value.lng], 14); }
        }

        async function loadStatus() {
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${props.vehicleId}/gps`);
                last.value = data?.last ?? null; device.value = data?.device ?? null;
                stats.value = data?.stats_today ?? null; liveStatus.value = data?.live_status ?? 'offline';
                mapError.value = ''; updateMarker();
            } catch { mapError.value = 'No se pudo cargar el estado GPS.'; }
        }

        async function loadHistory() {
            const { from, to } = rangeBounds();
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${props.vehicleId}/gps/history`, { params: { from, to } });
                history.value = data?.positions ?? []; drawPath();
            } catch { mapError.value = 'No se pudo cargar el historial GPS.'; }
        }

        async function loadGeoEvents() {
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${props.vehicleId}/geofence-events`, { params: { limit: 20 } });
                geoEvents.value = data?.events ?? [];
            } catch { geoEvents.value = []; }
        }

        async function loadRulesCount() {
            try { const { data } = await axios.get(`${props.baseUrl}/api/reglas`); rulesCount.value = (data?.rules ?? []).filter((r) => r.active).length; }
            catch { rulesCount.value = 0; }
        }

        async function refresh() {
            refreshing.value = true;
            try { await Promise.all([loadStatus(), loadHistory(), loadGeoEvents(), loadRulesCount()]); }
            finally { refreshing.value = false; }
        }

        function setRange(key) { range.value = key; if (key !== 'custom') loadHistory(); }
        function center() {
            if (path && map) map.fitBounds(path.getBounds(), { padding: [30, 30], maxZoom: 16 });
            else if (last.value?.lat != null && map) map.setView([last.value.lat, last.value.lng], 15);
        }
        function togglePause() { paused.value = !paused.value; if (!paused.value) loadStatus(); }
        function focusEvent(e) { if (e?.lat == null || !map) return; map.setView([e.lat, e.lng], 15, { animate: true }); }

        function downloadGpx() {
            if (!history.value.length) return;
            const pts = history.value.filter((p) => p.lat != null)
                .map((p) => `<trkpt lat="${p.lat}" lon="${p.lng}"><time>${p.recorded_at}</time></trkpt>`).join('\n');
            const gpx = `<?xml version="1.0" encoding="UTF-8"?><gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1"><trk><name>${props.vehicleId}</name><trkseg>${pts}</trkseg></trk></gpx>`;
            const a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([gpx], { type: 'application/gpx+xml' }));
            a.download = `flota-${props.vehicleId}-${range.value}.gpx`;
            a.click();
        }

        // Wizard de activación
        const showActivateForm = ref(false); const savingGps = ref(false);
        const gpsForm = reactive({ brand: 'mock', model: '', imei: '', sim_number: '', sim_carrier: '' });
        const instructions = ref(null);

        async function activateGps() {
            if (!gpsForm.imei?.trim()) { emit('toast', { message: 'El IMEI es obligatorio.', type: 'error' }); return; }
            savingGps.value = true;
            try {
                const { data } = await axios.post(`${props.baseUrl}/api/vehiculos/${props.vehicleId}/gps/device`, { ...gpsForm });
                showActivateForm.value = false;
                if (data?.listener) instructions.value = { ...data.listener, imei: gpsForm.imei };
                emit('reload'); emit('toast', { message: 'Tracking GPS activado.', type: 'success' });
                nextTick(() => ensureMap());
            } catch (e) { emit('toast', { message: 'No se pudo activar el GPS.', type: 'error' }); }
            finally { savingGps.value = false; }
        }

        // Modal de alertas
        const alertModal = ref(false);
        const alertPrefs = reactive({
            loading: false, saving: false,
            active: false, all_geofences: true, geofence_ids: [],
            event_types: ['enter', 'exit'], channels: ['email'],
            geofences: [], user_email: '', user_phone: '',
        });

        async function openAlertModal() {
            alertModal.value = true; alertPrefs.loading = true;
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/vehiculos/${props.vehicleId}/notification-preference`);
                Object.assign(alertPrefs, { active: data?.active ?? false, all_geofences: data?.all_geofences ?? true, geofence_ids: data?.geofence_ids ?? [], event_types: data?.event_types ?? ['enter','exit'], channels: data?.channels ?? ['email'], geofences: data?.geofences ?? [], user_email: data?.user_email ?? '', user_phone: data?.user_phone ?? '' });
            } catch (e) { emit('toast', { message: 'No se pudieron cargar tus preferencias.', type: 'error' }); }
            finally { alertPrefs.loading = false; }
        }

        async function saveAlertPrefs() {
            if (alertPrefs.active && !alertPrefs.event_types.length) { emit('toast', { message: 'Selecciona al menos un tipo de evento.', type: 'error' }); return; }
            if (alertPrefs.active && !alertPrefs.channels.length) { emit('toast', { message: 'Selecciona al menos un canal.', type: 'error' }); return; }
            alertPrefs.saving = true;
            try {
                await axios.post(`${props.baseUrl}/api/vehiculos/${props.vehicleId}/notification-preference`, { active: alertPrefs.active, all_geofences: alertPrefs.all_geofences, geofence_ids: alertPrefs.geofence_ids, event_types: alertPrefs.event_types, channels: alertPrefs.channels });
                alertModal.value = false; emit('toast', { message: 'Preferencias guardadas.', type: 'success' });
            } catch (e) { emit('toast', { message: 'No se pudieron guardar tus preferencias.', type: 'error' }); }
            finally { alertPrefs.saving = false; }
        }

        watch(() => props.active, (isActive) => { if (isActive) ensureMap(); });
        onBeforeUnmount(() => { if (timer) clearInterval(timer); if (map) { map.remove(); map = null; } });

        return {
            mapEl, last, device, stats, history, liveStatus, range, custom, ranges,
            refreshing, paused, mapError, geoEvents, rulesCount,
            statusColor, statusLabel, lastSeen, cardinal, relTime,
            setRange, refresh, loadHistory, center, togglePause, focusEvent, downloadGpx,
            showActivateForm, savingGps, gpsForm, activateGps, instructions,
            alertModal, alertPrefs, openAlertModal, saveAlertPrefs,
        };
    },
};
</script>
