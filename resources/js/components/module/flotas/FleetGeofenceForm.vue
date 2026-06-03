<template>
    <div class="flt-gf-wrap">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">
                <i class="bi bi-bounding-box-circles me-2 text-primary"></i>{{ isEdit ? 'Editar geocerca' : 'Nueva geocerca' }}
            </h4>
            <a :href="`${baseUrl}/geocercas`" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
        </div>

        <div v-if="loadingInitial" class="text-center text-muted py-5">
            <div class="spinner-border text-primary mb-2"></div><div>Cargando…</div>
        </div>

        <div v-else class="row g-3">
            <!-- ── IZQUIERDA: mapa ─────────────────────────────────────── -->
            <div class="col-lg-7">
                <div class="flt-gf-toolbar">
                    <button class="btn btn-sm" :class="drawing ? 'btn-warning' : 'btn-primary'" @click="toggleDraw">
                        <i class="bi" :class="drawing ? 'bi-x-circle' : 'bi-pencil-square'"></i>
                        {{ drawing ? 'Cancelar dibujo' : (vertices.length ? 'Redibujar polígono' : 'Dibujar polígono') }}
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" :disabled="!vertices.length && !drawing" @click="clearPolygon">
                        <i class="bi bi-eraser me-1"></i>Limpiar
                    </button>
                    <span class="flt-gf-hint" v-if="drawing">
                        <i class="bi bi-info-circle me-1"></i>Clic para agregar vértices · doble clic para cerrar
                    </span>
                    <span class="flt-gf-hint text-success" v-else-if="vertices.length >= 3">
                        <i class="bi bi-check-circle me-1"></i>{{ vertices.length }} vértices
                    </span>
                </div>

                <div class="flt-gf-mapbox">
                    <div ref="mapEl" class="flt-gf-map"></div>
                </div>

                <div class="flt-gf-centroid mt-2" v-if="centroid">
                    <i class="bi bi-geo-alt me-1 text-primary"></i>
                    Centroide: <strong>{{ centroid[0].toFixed(5) }}, {{ centroid[1].toFixed(5) }}</strong>
                    <span class="text-muted ms-2">· {{ vertices.length }} vértices</span>
                </div>
                <div class="flt-gf-centroid mt-2 text-muted" v-else>
                    <i class="bi bi-geo me-1"></i>Dibuja un polígono de al menos 3 puntos en el mapa.
                </div>
            </div>

            <!-- ── DERECHA: formulario ─────────────────────────────────── -->
            <div class="col-lg-5">
                <div class="flt-gf-card">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                        <input class="form-control" v-model="form.name" maxlength="150" placeholder="Ej. Oficina central" />
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Descripción</label>
                        <textarea class="form-control" rows="2" v-model="form.description" placeholder="Opcional"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de alerta</label>
                        <div class="flt-gf-radios">
                            <label class="flt-gf-radio" :class="{ active: form.type === 'enter' }">
                                <input type="radio" value="enter" v-model="form.type"><i class="bi bi-box-arrow-in-down-right me-1"></i>Solo entrada
                            </label>
                            <label class="flt-gf-radio" :class="{ active: form.type === 'exit' }">
                                <input type="radio" value="exit" v-model="form.type"><i class="bi bi-box-arrow-up-right me-1"></i>Solo salida
                            </label>
                            <label class="flt-gf-radio" :class="{ active: form.type === 'both' }">
                                <input type="radio" value="both" v-model="form.type"><i class="bi bi-arrow-down-up me-1"></i>Ambos
                            </label>
                        </div>
                    </div>
                    <div class="mb-3 d-flex align-items-center gap-3">
                        <div>
                            <label class="form-label fw-semibold d-block">Color</label>
                            <input type="color" class="form-control form-control-color" v-model="form.color" @input="redraw" />
                        </div>
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" id="gfActive" v-model="form.active">
                            <label class="form-check-label" for="gfActive">Activa</label>
                        </div>
                    </div>

                    <!-- Vehículos asignados -->
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Vehículos asignados</label>
                        <div class="flt-gf-veh-box">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start" @click.stop="vehDropdown = !vehDropdown">
                                    <i class="bi bi-truck me-1"></i>
                                    {{ selectedVehicleIds.length ? `${selectedVehicleIds.length} seleccionado(s)` : 'Seleccionar vehículos…' }}
                                </button>
                                <div class="flt-gf-veh-menu" v-if="vehDropdown" @click.stop>
                                    <input class="form-control form-control-sm mb-2" placeholder="Filtrar…" v-model="vehSearch" />
                                    <div class="flt-gf-veh-scroll">
                                        <label class="flt-gf-veh-opt" v-for="v in filteredVehicles" :key="v.id">
                                            <input type="checkbox" :value="v.id" v-model="selectedVehicleIds">
                                            <span class="text-truncate">{{ v.display_name }}</span>
                                        </label>
                                        <div v-if="!filteredVehicles.length" class="text-muted small p-2">Sin vehículos.</div>
                                    </div>
                                </div>
                            </div>
                            <div class="flt-gf-chips mt-2" v-if="selectedVehicleIds.length">
                                <span class="flt-gf-chip" v-for="v in selectedVehicleObjs" :key="v.id">
                                    {{ v.display_name }}
                                    <i class="bi bi-x-lg" @click="removeVehicle(v.id)"></i>
                                </span>
                            </div>
                        </div>
                        <div class="alert alert-light border small mt-2 mb-0">
                            <i class="bi bi-info-circle me-1"></i>
                            Las alertas de entrada/salida se aplicarán solo a los vehículos seleccionados
                            (esta lógica se activa en Sub-fase 3.2).
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="d-flex justify-content-between gap-2 mt-3">
                    <a :href="`${baseUrl}/geocercas`" class="btn btn-outline-secondary">Cancelar</a>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary" :disabled="saving" @click="save(false)">
                            <i class="bi bi-file-earmark me-1"></i>Guardar borrador
                        </button>
                        <button class="btn btn-primary" :disabled="saving" @click="save(true)">
                            <i class="bi bi-check2 me-1"></i>{{ saving ? 'Guardando…' : 'Guardar y activar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast -->
        <transition name="flt-toast-fade">
            <div v-if="toast.visible" class="flt-toast" :class="`flt-toast-${toast.type}`">
                <i :class="toast.icon" class="me-2"></i>{{ toast.message }}
            </div>
        </transition>
    </div>
</template>

<script>
import { ref, reactive, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export default {
    name: 'FleetGeofenceForm',
    props: { baseUrl: { type: String, default: '/flotas' } },
    setup(props) {
        // ── id desde la URL (.../geocercas/{id}/editar) ──────────────────
        const parts = window.location.pathname.split('/').filter(Boolean);
        let geofenceId = null;
        if (parts.includes('editar')) {
            geofenceId = parts[parts.indexOf('editar') - 1];
        }
        const isEdit = ref(!!geofenceId);

        const mapEl = ref(null);
        const loadingInitial = ref(true);
        const saving = ref(false);
        const drawing = ref(false);
        const vertices = ref([]); // [[lat,lng], ...]

        const form = reactive({ name: '', description: '', type: 'both', color: '#3388ff', active: true });

        const vehicles = ref([]);
        const selectedVehicleIds = ref([]);
        const vehDropdown = ref(false);
        const vehSearch = ref('');

        const toast = reactive({ visible: false, message: '', type: 'success', icon: '' });
        function notify(message, type = 'success') {
            toast.message = message; toast.type = type;
            toast.icon = type === 'success' ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-circle-fill';
            toast.visible = true;
            setTimeout(() => { toast.visible = false; }, 3500);
        }

        // ── Leaflet ──────────────────────────────────────────────────────
        let map = null;
        let polygonLayer = null;
        let vertexLayer = null;
        let clickTimer = null;

        const centroid = computed(() => {
            if (vertices.value.length < 3) return null;
            let lat = 0, lng = 0;
            vertices.value.forEach((p) => { lat += p[0]; lng += p[1]; });
            const n = vertices.value.length;
            return [lat / n, lng / n];
        });

        function redraw() {
            if (!map) return;
            if (polygonLayer) { map.removeLayer(polygonLayer); polygonLayer = null; }
            if (vertexLayer) { map.removeLayer(vertexLayer); vertexLayer = null; }
            if (!vertices.value.length) return;

            const color = form.color || '#3388ff';
            polygonLayer = L.polygon(vertices.value, {
                color, weight: 2, fillColor: color, fillOpacity: 0.3,
            }).addTo(map);

            vertexLayer = L.layerGroup(
                vertices.value.map((p) => L.circleMarker(p, {
                    radius: 4, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1,
                }))
            ).addTo(map);
        }

        function addVertex(latlng) {
            vertices.value.push([latlng.lat, latlng.lng]);
            redraw();
        }

        function toggleDraw() {
            if (drawing.value) { drawing.value = false; return; }
            // Iniciar nuevo dibujo: limpiar lo previo
            vertices.value = [];
            redraw();
            drawing.value = true;
            if (map) map.getContainer().classList.add('flt-gf-drawing');
        }

        function finishDrawing() {
            drawing.value = false;
            if (map) map.getContainer().classList.remove('flt-gf-drawing');
            if (vertices.value.length < 3) {
                notify('El polígono necesita al menos 3 puntos.', 'error');
            }
        }

        function clearPolygon() {
            vertices.value = [];
            drawing.value = false;
            if (map) map.getContainer().classList.remove('flt-gf-drawing');
            redraw();
        }

        // ── Vehículos ──────────────────────────────────────────────────
        const filteredVehicles = computed(() => {
            const s = vehSearch.value.trim().toLowerCase();
            if (!s) return vehicles.value;
            return vehicles.value.filter((v) => (v.display_name || '').toLowerCase().includes(s));
        });
        const selectedVehicleObjs = computed(() =>
            vehicles.value.filter((v) => selectedVehicleIds.value.includes(v.id))
        );
        function removeVehicle(id) {
            selectedVehicleIds.value = selectedVehicleIds.value.filter((x) => x !== id);
        }
        function closeVehDropdown() { vehDropdown.value = false; }

        // ── Guardar ──────────────────────────────────────────────────────
        function save(activate) {
            if (!form.name.trim()) { notify('El nombre es obligatorio.', 'error'); return; }
            if (vertices.value.length < 3) { notify('Dibuja un polígono de al menos 3 puntos.', 'error'); return; }

            saving.value = true;
            const payload = {
                name: form.name.trim(),
                description: form.description || null,
                type: form.type,
                polygon: vertices.value,
                color: form.color,
                active: activate ? true : false,
                vehicle_ids: selectedVehicleIds.value,
            };

            const req = isEdit.value
                ? axios.put(`${props.baseUrl}/api/geocercas/${geofenceId}`, payload)
                : axios.post(`${props.baseUrl}/api/geocercas`, payload);

            req.then(() => {
                notify(isEdit.value ? 'Geocerca actualizada.' : 'Geocerca creada.');
                setTimeout(() => { window.location.href = `${props.baseUrl}/geocercas`; }, 800);
            }).catch((e) => {
                notify(e?.response?.data?.message || 'No se pudo guardar la geocerca.', 'error');
                saving.value = false;
            });
        }

        // ── Carga inicial ──────────────────────────────────────────────
        async function loadVehicles() {
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/vehiculos`);
                vehicles.value = data?.vehicles ?? [];
            } catch (e) { vehicles.value = []; }
        }

        async function loadGeofence() {
            if (!geofenceId) return;
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/geocercas/${geofenceId}`);
                const g = data?.geofence;
                if (!g) return;
                form.name = g.name || '';
                form.description = g.description || '';
                form.type = g.type || 'both';
                form.color = g.color || '#3388ff';
                form.active = !!g.active;
                vertices.value = Array.isArray(g.polygon) ? g.polygon.map((p) => [Number(p[0]), Number(p[1])]) : [];
                selectedVehicleIds.value = (g.vehicles ?? []).map((v) => v.id);
            } catch (e) {
                notify('No se pudo cargar la geocerca.', 'error');
            }
        }

        onMounted(async () => {
            // 1. Cargar datos PRIMERO. El contenedor del mapa (ref=mapEl) vive dentro
            //    del bloque v-else, que NO existe en el DOM mientras loadingInitial=true.
            await Promise.all([loadVehicles(), loadGeofence()]);
            loadingInitial.value = false;

            // 2. Esperar a que Vue renderice el v-else (ya con mapEl montado).
            await nextTick();
            if (!mapEl.value) return;

            map = L.map(mapEl.value).setView([19.4326, -99.1332], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);
            map.doubleClickZoom.disable();

            // Click con debounce para no duplicar vértices al hacer doble clic.
            map.on('click', (e) => {
                if (!drawing.value) return;
                if (clickTimer) clearTimeout(clickTimer);
                clickTimer = setTimeout(() => { addVertex(e.latlng); clickTimer = null; }, 220);
            });
            map.on('dblclick', () => {
                if (!drawing.value) return;
                if (clickTimer) { clearTimeout(clickTimer); clickTimer = null; }
                finishDrawing();
            });

            map.invalidateSize();
            redraw();
            if (vertices.value.length >= 3) {
                map.fitBounds(vertices.value, { padding: [40, 40], maxZoom: 16 });
            }

            document.addEventListener('click', closeVehDropdown);
        });

        onBeforeUnmount(() => {
            document.removeEventListener('click', closeVehDropdown);
            if (clickTimer) clearTimeout(clickTimer);
            if (map) map.remove();
        });

        return {
            mapEl, isEdit, loadingInitial, saving, drawing, vertices, form, centroid,
            vehicles, selectedVehicleIds, vehDropdown, vehSearch, filteredVehicles, selectedVehicleObjs,
            toggleDraw, clearPolygon, redraw, removeVehicle, save, toast,
        };
    },
};
</script>

<style scoped>
.flt-gf-wrap { padding: 4px; }
.flt-gf-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 8px; }
.flt-gf-hint { font-size: .8rem; color: #6b7280; }
.flt-gf-mapbox { position: relative; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.flt-gf-map { width: 100%; height: 460px; }
.flt-gf-map.flt-gf-drawing, .flt-gf-drawing { cursor: crosshair !important; }
.flt-gf-centroid { font-size: .85rem; }
.flt-gf-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
.flt-gf-radios { display: flex; flex-direction: column; gap: 6px; }
.flt-gf-radio { border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px 12px; cursor: pointer; font-size: .9rem; display: flex; align-items: center; }
.flt-gf-radio input { margin-right: 8px; }
.flt-gf-radio.active { border-color: #3b82f6; background: #eff6ff; color: #1d4ed8; }
.flt-gf-veh-box { position: relative; }
.flt-gf-veh-menu { position: absolute; z-index: 1200; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px; margin-top: 2px; box-shadow: 0 6px 20px rgba(0,0,0,.12); }
.flt-gf-veh-scroll { max-height: 200px; overflow-y: auto; }
.flt-gf-veh-opt { display: flex; align-items: center; gap: 8px; padding: 5px 4px; cursor: pointer; font-size: .88rem; border-radius: 6px; }
.flt-gf-veh-opt:hover { background: #f8fafc; }
.flt-gf-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.flt-gf-chip { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; border-radius: 999px; padding: 2px 10px; font-size: .8rem; display: inline-flex; align-items: center; gap: 6px; }
.flt-gf-chip i { cursor: pointer; font-size: .7rem; }
.flt-toast { position: fixed; bottom: 24px; right: 24px; z-index: 10001; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,.15); display: flex; align-items: center; color: #fff; }
.flt-toast-success { background: #16a34a; }
.flt-toast-error { background: #dc2626; }
.flt-toast-fade-enter-active, .flt-toast-fade-leave-active { transition: all .25s; }
.flt-toast-fade-enter-from, .flt-toast-fade-leave-to { opacity: 0; transform: translateY(12px); }
</style>
