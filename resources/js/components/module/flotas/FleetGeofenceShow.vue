<template>
    <div class="flt-gs-wrap">

        <div v-if="loading" class="text-center text-muted py-5">
            <div class="spinner-border text-primary mb-2"></div><div>Cargando geocerca…</div>
        </div>

        <div v-else-if="loadError" class="alert alert-danger">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>No se pudo cargar la geocerca.
            <a :href="`${baseUrl}/geocercas`" class="ms-1">Volver</a>
        </div>

        <template v-else-if="geofence">
            <!-- Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="flt-gs-swatch" :style="{ background: geofence.color }"><i class="bi bi-bounding-box"></i></span>
                    <div>
                        <h4 class="mb-0">{{ geofence.name }}</h4>
                        <div class="flt-gs-badges">
                            <span class="badge" :class="typeBadge(geofence.type)">{{ typeLabel(geofence.type) }}</span>
                            <span class="badge" :class="geofence.active ? 'bg-success' : 'bg-secondary'">{{ geofence.active ? 'Activa' : 'Inactiva' }}</span>
                            <span class="badge bg-light text-dark border"><i class="bi bi-truck me-1"></i>{{ geofence.vehicles_count }} vehículo(s)</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a :href="`${baseUrl}/geocercas`" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
                    <a :href="`${baseUrl}/geocercas/${geofence.id}/editar`" class="btn btn-sm btn-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
                    <button class="btn btn-sm btn-outline-danger" @click="showDelete = true"><i class="bi bi-trash me-1"></i>Eliminar</button>
                </div>
            </div>

            <!-- Banner Sub-fase 3.2 pendiente -->
            <div class="flt-gs-banner mb-3">
                <i class="bi bi-cone-striped me-2"></i>
                Esta geocerca está creada pero la detección automática de entrada/salida está pendiente
                (Sub-fase 3.2). Por ahora sirve como referencia visual.
            </div>

            <div class="row g-3">
                <!-- Mapa solo lectura -->
                <div class="col-lg-8">
                    <div class="flt-gs-mapbox"><div ref="mapEl" class="flt-gs-map"></div></div>
                    <div v-if="geofence.description" class="text-muted small mt-2">{{ geofence.description }}</div>
                </div>

                <!-- Vehículos asignados -->
                <div class="col-lg-4">
                    <div class="flt-gs-card">
                        <h6 class="mb-3"><i class="bi bi-truck me-1 text-primary"></i>Vehículos asignados</h6>
                        <div v-if="!geofence.vehicles || !geofence.vehicles.length" class="text-muted small">
                            <i class="bi bi-dash-circle me-1"></i>Sin vehículos asignados.
                        </div>
                        <div v-else class="flt-gs-veh-list">
                            <a v-for="v in geofence.vehicles" :key="v.id" :href="`${baseUrl}/${v.id}`" class="flt-gs-veh">
                                <i class="bi bi-truck-front me-2 text-secondary"></i>
                                <span class="flex-grow-1 text-truncate">{{ v.display_name }}</span>
                                <i class="bi bi-box-arrow-up-right text-muted small"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Modal eliminar -->
        <div v-if="showDelete" class="modal fade show flt-gs-modal" tabindex="-1" style="display:block" @click.self="!deleting && (showDelete = false)">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>¿Eliminar geocerca?</h5>
                        <button type="button" class="btn-close" :disabled="deleting" @click="showDelete = false"></button>
                    </div>
                    <div class="modal-body" v-if="geofence">
                        Estás a punto de eliminar <strong>{{ geofence.name }}</strong>. Los datos quedan en base de datos (soft delete).
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" :disabled="deleting" @click="showDelete = false">Cancelar</button>
                        <button class="btn btn-danger" :disabled="deleting" @click="confirmDelete">
                            <i class="bi bi-trash me-1"></i>{{ deleting ? 'Eliminando…' : 'Sí, eliminar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="showDelete" class="modal-backdrop fade show"></div>

        <!-- Toast -->
        <transition name="flt-toast-fade">
            <div v-if="toast.visible" class="flt-toast" :class="`flt-toast-${toast.type}`">
                <i :class="toast.icon" class="me-2"></i>{{ toast.message }}
            </div>
        </transition>
    </div>
</template>

<script>
import { ref, reactive, onMounted, onBeforeUnmount, nextTick } from 'vue';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export default {
    name: 'FleetGeofenceShow',
    props: { baseUrl: { type: String, default: '/flotas' } },
    setup(props) {
        const parts = window.location.pathname.split('/').filter(Boolean);
        const geofenceId = parts[parts.length - 1];

        const geofence = ref(null);
        const loading = ref(true);
        const loadError = ref(false);
        const showDelete = ref(false);
        const deleting = ref(false);
        const mapEl = ref(null);
        let map = null;

        const toast = reactive({ visible: false, message: '', type: 'success', icon: '' });
        function notify(message, type = 'success') {
            toast.message = message; toast.type = type;
            toast.icon = type === 'success' ? 'bi bi-check-circle-fill' : 'bi bi-exclamation-circle-fill';
            toast.visible = true;
            setTimeout(() => { toast.visible = false; }, 3500);
        }

        const typeLabel = (t) => ({ enter: 'Entrada', exit: 'Salida', both: 'Ambos' }[t] || t);
        const typeBadge = (t) => ({ enter: 'bg-info', exit: 'bg-warning text-dark', both: 'bg-primary' }[t] || 'bg-secondary');

        function drawPolygon() {
            if (!map || !geofence.value) return;
            const poly = Array.isArray(geofence.value.polygon) ? geofence.value.polygon : [];
            if (poly.length < 3) return;
            const color = geofence.value.color || '#3388ff';
            const layer = L.polygon(poly, { color, weight: 2, fillColor: color, fillOpacity: 0.3 }).addTo(map);
            map.fitBounds(layer.getBounds(), { padding: [40, 40], maxZoom: 16 });
        }

        async function load() {
            loading.value = true;
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/geocercas/${geofenceId}`);
                geofence.value = data?.geofence ?? null;
                if (!geofence.value) loadError.value = true;
            } catch (e) {
                loadError.value = true;
            } finally {
                loading.value = false;
            }
        }

        function confirmDelete() {
            deleting.value = true;
            axios.delete(`${props.baseUrl}/api/geocercas/${geofenceId}`)
                .then(() => {
                    notify('Geocerca eliminada.');
                    setTimeout(() => { window.location.href = `${props.baseUrl}/geocercas`; }, 800);
                })
                .catch((e) => { notify(e?.response?.data?.message || 'No se pudo eliminar.', 'error'); deleting.value = false; });
        }

        onMounted(async () => {
            await load();
            if (loadError.value) return;
            await nextTick();
            if (!mapEl.value) return;
            map = L.map(mapEl.value).setView([19.4326, -99.1332], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);
            await nextTick();
            map.invalidateSize();
            drawPolygon();
        });

        onBeforeUnmount(() => { if (map) map.remove(); });

        return {
            geofence, loading, loadError, showDelete, deleting, mapEl, toast,
            typeLabel, typeBadge, confirmDelete,
        };
    },
};
</script>

<style scoped>
.flt-gs-wrap { padding: 4px; }
.flt-gs-swatch { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 1.3rem; text-shadow: 0 1px 2px rgba(0,0,0,.3); }
.flt-gs-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
.flt-gs-banner { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; border-radius: 10px; padding: 12px 16px; font-size: .9rem; }
.flt-gs-mapbox { border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.flt-gs-map { width: 100%; height: 460px; }
.flt-gs-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
.flt-gs-veh-list { display: flex; flex-direction: column; gap: 6px; }
.flt-gs-veh { display: flex; align-items: center; gap: 4px; padding: 8px 10px; border: 1px solid #eef0f3; border-radius: 8px; text-decoration: none; color: inherit; }
.flt-gs-veh:hover { background: #f8fafc; border-color: #cbd5e1; }
.flt-gs-modal { z-index: 9999; }
.flt-gs-modal .modal-content { border: none; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,.2); }
.modal-backdrop.show { z-index: 9998; opacity: .5; }
.flt-toast { position: fixed; bottom: 24px; right: 24px; z-index: 10001; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 16px rgba(0,0,0,.15); display: flex; align-items: center; color: #fff; }
.flt-toast-success { background: #16a34a; }
.flt-toast-error { background: #dc2626; }
.flt-toast-fade-enter-active, .flt-toast-fade-leave-active { transition: all .25s; }
.flt-toast-fade-enter-from, .flt-toast-fade-leave-to { opacity: 0; transform: translateY(12px); }
</style>
