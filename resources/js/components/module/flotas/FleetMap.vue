<template>
    <div class="flt-map-wrap">
        <!-- Sidebar de vehículos -->
        <aside class="flt-map-side">
            <div class="flt-map-side-head">
                <h6 class="mb-2"><i class="bi bi-truck me-2"></i>Flota en vivo</h6>
                <div class="btn-group btn-group-sm w-100 mb-2" role="group">
                    <button class="btn" :class="filter === 'all' ? 'btn-primary' : 'btn-outline-secondary'" @click="filter = 'all'">Todos</button>
                    <button class="btn" :class="filter === 'moving' ? 'btn-primary' : 'btn-outline-secondary'" @click="filter = 'moving'">En movimiento</button>
                    <button class="btn" :class="filter === 'alerts' ? 'btn-primary' : 'btn-outline-secondary'" @click="filter = 'alerts'">Alertas</button>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <small class="text-muted">{{ filtered.length }} de {{ vehicles.length }}</small>
                    <div class="d-flex align-items-center gap-2">
                        <span v-if="refreshing" class="flt-live-dot text-primary"><i class="bi bi-arrow-repeat spin"></i></span>
                        <button class="btn btn-sm btn-link p-0" :title="paused ? 'Reanudar' : 'Pausar actualización'" @click="togglePause">
                            <i class="bi" :class="paused ? 'bi-play-circle' : 'bi-pause-circle'"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flt-map-list">
                <div v-for="v in filtered" :key="v.vehicle_id"
                     class="flt-map-item" :class="{ active: selectedId === v.vehicle_id }"
                     @click="focusVehicle(v)">
                    <span class="flt-status-pill" :style="{ background: statusColor(v.live_status) }"></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ v.display_name }}</div>
                        <small class="text-muted">{{ statusLabel(v.live_status) }} · {{ lastSeen(v) }}</small>
                    </div>
                    <a :href="`${baseUrl}/${v.vehicle_id}?tab=gps`" class="btn btn-sm btn-link p-0 ms-1" @click.stop title="Ver detalle">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
                <div v-if="!loading && filtered.length === 0" class="text-center text-muted py-4">
                    <i class="bi bi-geo-alt d-block mb-1" style="font-size:1.5rem"></i>
                    Sin vehículos en esta vista
                </div>
            </div>
        </aside>

        <!-- Mapa -->
        <div class="flt-map-canvas">
            <div ref="mapEl" class="flt-leaflet"></div>
            <div v-if="loadError" class="flt-map-error">{{ loadError }}</div>
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

const COLORS = { moving: '#22c55e', stopped: '#f59e0b', idle: '#9ca3af', offline: '#ef4444' };
const LABELS = { moving: 'En movimiento', stopped: 'Detenido', idle: 'Inactivo', offline: 'Sin señal' };

export default {
    name: 'FleetMap',
    props: {
        baseUrl: { type: String, default: '/flotas' },
        pollSeconds: { type: Number, default: 30 },
    },
    setup(props) {
        const mapEl = ref(null);
        const vehicles = ref([]);
        const loading = ref(true);
        const loadError = ref('');
        const refreshing = ref(false);
        const paused = ref(false);
        const filter = ref('all');
        const selectedId = ref(null);

        let map = null;
        const markers = {};
        let timer = null;

        const toast = reactive({ visible: false, message: '', type: 'success', icon: '' });
        function notify(message, type = 'success') {
            toast.message = message;
            toast.type = type;
            toast.icon = type === 'success' ? 'bi bi-check-circle' : 'bi bi-exclamation-triangle';
            toast.visible = true;
            setTimeout(() => { toast.visible = false; }, 3000);
        }

        const statusColor = (s) => COLORS[s] || COLORS.offline;
        const statusLabel = (s) => LABELS[s] || LABELS.offline;

        function lastSeen(v) {
            const iso = v?.position?.recorded_at;
            if (!iso) return 'sin datos';
            const diff = Math.round((Date.now() - new Date(iso).getTime()) / 60000);
            if (diff < 1) return 'ahora';
            if (diff < 60) return `hace ${diff} min`;
            const h = Math.round(diff / 60);
            return `hace ${h} h`;
        }

        const isAlert = (s) => s === 'offline' || s === 'idle';
        const filtered = computed(() => {
            if (filter.value === 'moving') return vehicles.value.filter((v) => v.live_status === 'moving');
            if (filter.value === 'alerts') return vehicles.value.filter((v) => isAlert(v.live_status));
            return vehicles.value;
        });

        function popupHtml(v) {
            const p = v.position || {};
            return `<div class="flt-popup">
                <strong>${v.display_name ?? 'Vehículo'}</strong><br>
                <span class="text-muted">${v.plates ?? 's/placas'}</span><br>
                <span>Operador: ${v.operator ?? '—'}</span><br>
                <span>Estado: ${statusLabel(v.live_status)}</span><br>
                <span>Vel: ${p.speed != null ? Math.round(p.speed) + ' km/h' : '—'}</span><br>
                <span class="text-muted small">${lastSeen(v)}</span><br>
                <a href="${props.baseUrl}/${v.vehicle_id}?tab=gps">Ver detalle →</a>
            </div>`;
        }

        function renderMarkers() {
            if (!map) return;
            const seen = new Set();
            const bounds = [];

            vehicles.value.forEach((v) => {
                if (!v.position || v.position.lat == null) return;
                seen.add(v.vehicle_id);
                const ll = [v.position.lat, v.position.lng];
                bounds.push(ll);

                if (markers[v.vehicle_id]) {
                    markers[v.vehicle_id].setLatLng(ll);
                    markers[v.vehicle_id].setStyle({ fillColor: statusColor(v.live_status) });
                    markers[v.vehicle_id].setPopupContent(popupHtml(v));
                } else {
                    const m = L.circleMarker(ll, {
                        radius: 9, color: '#fff', weight: 2,
                        fillColor: statusColor(v.live_status), fillOpacity: 1,
                    }).addTo(map);
                    m.bindPopup(popupHtml(v));
                    m.on('click', () => { selectedId.value = v.vehicle_id; });
                    markers[v.vehicle_id] = m;
                }
            });

            // Remueve markers de vehículos que ya no vienen
            Object.keys(markers).forEach((id) => {
                if (!seen.has(Number(id))) { map.removeLayer(markers[id]); delete markers[id]; }
            });

            if (bounds.length && !map._fittedOnce) {
                map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
                map._fittedOnce = true;
            }
        }

        async function load(initial = false) {
            if (initial) loading.value = true;
            refreshing.value = true;
            try {
                const { data } = await axios.get(`${props.baseUrl}/api/gps/flota`);
                vehicles.value = data?.vehicles ?? [];
                loadError.value = '';
                renderMarkers();
            } catch (e) {
                loadError.value = 'No se pudo cargar la flota.';
                if (initial) notify('No se pudo cargar la flota.', 'error');
            } finally {
                loading.value = false;
                refreshing.value = false;
            }
        }

        function focusVehicle(v) {
            selectedId.value = v.vehicle_id;
            if (v.position && v.position.lat != null && map) {
                map.setView([v.position.lat, v.position.lng], 15, { animate: true });
                markers[v.vehicle_id]?.openPopup();
            }
        }

        function startPolling() {
            stopPolling();
            timer = setInterval(() => { if (!paused.value) load(false); }, props.pollSeconds * 1000);
        }
        function stopPolling() { if (timer) { clearInterval(timer); timer = null; } }
        function togglePause() {
            paused.value = !paused.value;
            if (!paused.value) load(false);
        }

        onMounted(async () => {
            await nextTick();
            map = L.map(mapEl.value).setView([19.4326, -99.1332], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);
            await load(true);
            startPolling();
        });

        onBeforeUnmount(() => { stopPolling(); if (map) map.remove(); });

        return {
            mapEl, vehicles, loading, loadError, refreshing, paused, filter, selectedId,
            filtered, toast, statusColor, statusLabel, lastSeen, focusVehicle, togglePause,
        };
    },
};
</script>

<style scoped>
.flt-map-wrap { display: flex; gap: 12px; height: calc(100vh - 170px); min-height: 460px; }
.flt-map-side { width: 320px; flex-shrink: 0; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; }
.flt-map-side-head { padding: 12px; border-bottom: 1px solid #eef0f3; }
.flt-map-list { overflow-y: auto; flex-grow: 1; }
.flt-map-item { display: flex; align-items: center; gap: 8px; padding: 10px 12px; cursor: pointer; border-bottom: 1px solid #f3f4f6; }
.flt-map-item:hover { background: #f8fafc; }
.flt-map-item.active { background: #eff6ff; }
.flt-status-pill { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
.min-w-0 { min-width: 0; }
.flt-map-canvas { position: relative; flex-grow: 1; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.flt-leaflet { width: 100%; height: 100%; }
.flt-map-error { position: absolute; top: 12px; left: 50%; transform: translateX(-50%); background: #fef2f2; color: #b91c1c; padding: 6px 14px; border-radius: 8px; z-index: 500; font-size: .85rem; }
.flt-live-dot .spin, .spin { animation: flt-spin 1s linear infinite; display: inline-block; }
@keyframes flt-spin { to { transform: rotate(360deg); } }
.flt-toast { position: fixed; bottom: 24px; right: 24px; background: #fff; border-radius: 10px; padding: 12px 18px; box-shadow: 0 8px 24px rgba(0,0,0,.15); z-index: 3000; }
.flt-toast-success { border-left: 4px solid #22c55e; }
.flt-toast-error { border-left: 4px solid #ef4444; }
.flt-toast-fade-enter-active, .flt-toast-fade-leave-active { transition: opacity .3s; }
.flt-toast-fade-enter-from, .flt-toast-fade-leave-to { opacity: 0; }
</style>
