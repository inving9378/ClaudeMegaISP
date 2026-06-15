<template>
    <div class="olt-red-map-wrapper">

        <!-- ── Barra de estadísticas ──────────────────────────────────── -->
        <div class="olt-stats-bar q-px-md q-py-sm row q-gutter-sm items-center">
            <div class="stat-chip">
                <span class="stat-label">OLTs</span>
                <span class="stat-val">{{ stats.total_olts ?? '…' }}</span>
            </div>
            <div class="stat-chip">
                <span class="stat-label">ODBs en mapa</span>
                <span class="stat-val">{{ odbsEnMapa }} / {{ stats.total_odbs ?? '…' }}</span>
            </div>
            <div class="stat-chip">
                <span class="stat-label">ONUs en mapa</span>
                <span class="stat-val">{{ onusEnMapa }} / {{ stats.total_onus ?? '…' }}</span>
            </div>
            <div class="stat-chip stat-chip--online">
                <span class="stat-label">Online</span>
                <span class="stat-val">{{ stats.onus_online ?? '…' }}</span>
            </div>
            <div class="stat-chip stat-chip--offline">
                <span class="stat-label">Offline</span>
                <span class="stat-val">{{ stats.onus_offline ?? '…' }}</span>
            </div>
            <div class="olt-notice q-ml-sm" v-if="!loading && onusEnMapa < 50">
                <i class="fas fa-info-circle"></i>
                Pocas ONUs tienen coordenadas registradas — el mapa crece conforme SmartOLT las popula.
            </div>
            <div class="q-ml-auto">
                <button class="btn-icon" @click="loadData" :disabled="loading" title="Recargar datos">
                    <i class="fas fa-sync-alt" :class="{ 'fa-spin': loading }"></i>
                </button>
            </div>
        </div>

        <!-- ── Layout principal ───────────────────────────────────────── -->
        <div class="olt-map-layout">

            <!-- Panel lateral — OLTs (sin geo) -->
            <div class="olt-sidebar">
                <div class="sidebar-title">OLTs</div>
                <div v-for="olt in olts" :key="olt.id" class="olt-item">
                    <span class="olt-dot" :class="olt.status === 'active' ? 'dot-green' : 'dot-grey'"></span>
                    <div>
                        <div class="olt-name">{{ olt.name }}</div>
                        <div class="olt-ip">{{ olt.ip }}</div>
                    </div>
                </div>
                <div v-if="!olts.length && !loading" class="olt-empty">Sin OLTs</div>

                <!-- Leyenda -->
                <div class="sidebar-title q-mt-md">Señal ONUs</div>
                <div class="legend-row"><span class="leg-dot" style="background:#22c55e"></span> Muy buena</div>
                <div class="legend-row"><span class="leg-dot" style="background:#f59e0b"></span> Advertencia</div>
                <div class="legend-row"><span class="leg-dot" style="background:#ef4444"></span> Crítica</div>
                <div class="legend-row"><span class="leg-dot" style="background:#9ca3af"></span> Sin datos / Offline</div>
            </div>

            <!-- Mapa -->
            <div class="olt-map-canvas">
                <div ref="mapEl" class="olt-map-leaflet"></div>
            </div>

        </div>

    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, nextTick } from "vue";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

defineOptions({ name: "OltRedMap" });

// ── Estado ────────────────────────────────────────────────────────────────
const mapEl     = ref(null);
const loading   = ref(false);
const olts      = ref([]);
const odbs      = ref([]);
const onus      = ref([]);
const stats     = ref({});

let map            = null;
let layerOdbs      = null;
let layerOnusVerde = null;
let layerOnusAmbar = null;
let layerOnusRojo  = null;
let layerOnusGris  = null;
let layerControl   = null;

// ── Computed ──────────────────────────────────────────────────────────────
const odbsEnMapa = computed(() => odbs.value.length);
const onusEnMapa = computed(() => onus.value.length);

// ── Colores y radios por tier ─────────────────────────────────────────────
const TIER_COLOR = {
    verde: '#22c55e',
    ambar: '#f59e0b',
    rojo:  '#ef4444',
    gris:  '#9ca3af',
};

function markerCircle(lat, lng, color, title, popupHtml) {
    return L.circleMarker([lat, lng], {
        radius: 7,
        color: color,
        fillColor: color,
        fillOpacity: 0.8,
        weight: 1.5,
        opacity: 1,
    }).bindPopup(popupHtml, { maxWidth: 280 });
}

function odbMarker(lat, lng, name, zone) {
    return L.circleMarker([lat, lng], {
        radius: 9,
        color: '#2563eb',
        fillColor: '#3b82f6',
        fillOpacity: 0.7,
        weight: 2,
    }).bindPopup(`<b>ODB: ${name}</b><br>Zona: ${zone || '—'}`);
}

// ── Inicialización del mapa ───────────────────────────────────────────────
function initMap() {
    if (!mapEl.value) return;

    map = L.map(mapEl.value, {
        center: [19.74, -99.09],
        zoom: 13,
        zoomControl: false,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 20,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    L.control.zoom({ position: 'topleft', zoomInText: '+', zoomOutText: '−' }).addTo(map);

    // Grupos de capas (off por defecto — se activan en el control)
    layerOdbs      = L.layerGroup();
    layerOnusVerde = L.layerGroup();
    layerOnusAmbar = L.layerGroup();
    layerOnusRojo  = L.layerGroup();
    layerOnusGris  = L.layerGroup();

    // Todas las capas de ONUs visibles por defecto en esta página dedicada
    [layerOdbs, layerOnusVerde, layerOnusAmbar, layerOnusRojo, layerOnusGris]
        .forEach(l => l.addTo(map));

    layerControl = L.control.layers(null, {
        'ODBs'               : layerOdbs,
        'ONUs — Muy buena'   : layerOnusVerde,
        'ONUs — Advertencia' : layerOnusAmbar,
        'ONUs — Crítica'     : layerOnusRojo,
        'ONUs — Sin datos'   : layerOnusGris,
    }, { collapsed: false, position: 'topright' }).addTo(map);
}

// ── Carga de datos y render de markers ───────────────────────────────────
async function loadData() {
    loading.value = true;
    try {
        const res = await axios.get('/olts/geo-capas');
        olts.value  = res.data.olts  || [];
        odbs.value  = res.data.odbs  || [];
        onus.value  = res.data.onus  || [];
        stats.value = res.data.stats || {};

        renderLayers();
    } catch (e) {
        console.error('Error cargando capas OLT:', e);
    } finally {
        loading.value = false;
    }
}

function renderLayers() {
    // Limpiar capas
    [layerOdbs, layerOnusVerde, layerOnusAmbar, layerOnusRojo, layerOnusGris]
        .forEach(l => l.clearLayers());

    // ODBs
    odbs.value.forEach(o => {
        if (!o.lat || !o.lng) return;
        odbMarker(o.lat, o.lng, o.name, o.zone_name).addTo(layerOdbs);
    });

    // ONUs por tier
    onus.value.forEach(o => {
        if (!o.lat || !o.lng) return;
        const color = TIER_COLOR[o.tier] || TIER_COLOR.gris;
        const popup = popupHtml(o);
        const marker = markerCircle(o.lat, o.lng, color, o.name, popup);

        switch (o.tier) {
            case 'verde': marker.addTo(layerOnusVerde); break;
            case 'ambar': marker.addTo(layerOnusAmbar); break;
            case 'rojo':  marker.addTo(layerOnusRojo);  break;
            default:      marker.addTo(layerOnusGris);
        }
    });

    // Centrar mapa en los datos si hay algo
    const allMarkers = [
        ...odbs.value.filter(o => o.lat && o.lng).map(o => [o.lat, o.lng]),
        ...onus.value.filter(o => o.lat && o.lng).map(o => [o.lat, o.lng]),
    ];
    if (allMarkers.length > 0) {
        map.fitBounds(allMarkers, { padding: [40, 40], maxZoom: 16 });
    }
}

function popupHtml(o) {
    const tierLabel = { verde: '🟢 Muy buena', ambar: '🟡 Advertencia', rojo: '🔴 Crítica', gris: '⚫ Sin datos' };
    const dbm = o.signal_dbm ? `${o.signal_dbm} dBm` : '—';
    return `
        <div style="min-width:200px">
            <b>${o.name}</b><br>
            <span style="font-size:11px;color:#555">${o.sn}</span><br>
            <hr style="margin:4px 0">
            <table style="font-size:12px;width:100%">
                <tr><td>Estado</td><td><b>${o.status}</b></td></tr>
                <tr><td>Señal</td><td>${tierLabel[o.tier] ?? '—'} (${dbm})</td></tr>
                <tr><td>OLT</td><td>${o.olt_name || '—'}</td></tr>
                <tr><td>Zona</td><td>${o.zone_name || '—'}</td></tr>
            </table>
        </div>
    `;
}

// ── Lifecycle ─────────────────────────────────────────────────────────────
onMounted(async () => {
    await nextTick();
    initMap();
    await loadData();
});

onBeforeUnmount(() => {
    if (map) { map.remove(); map = null; }
});
</script>

<style scoped>
.olt-red-map-wrapper {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 110px);
    min-height: 500px;
}

/* ── Barra stats ────────────────────────────────────────────── */
.olt-stats-bar {
    background: var(--sys-surface, #fff);
    border-bottom: 1px solid var(--sys-outline-variant, #e0e0e0);
    flex-shrink: 0;
    flex-wrap: wrap;
    gap: 6px;
    padding: 8px 16px;
}
.body--dark .olt-stats-bar {
    background: var(--sys-surface-2, #1d1d1d);
    border-color: rgba(255,255,255,0.1);
}

.stat-chip {
    display: flex;
    align-items: center;
    gap: 5px;
    background: var(--sys-surface-1, #f5f5f5);
    border-radius: 16px;
    padding: 4px 10px;
    font-size: 12px;
}
.body--dark .stat-chip { background: rgba(255,255,255,0.06); }
.stat-label { color: #777; }
.stat-val   { font-weight: 600; }
.stat-chip--online .stat-val { color: #22c55e; }
.stat-chip--offline .stat-val { color: #ef4444; }

.olt-notice {
    font-size: 11px;
    color: #6b7280;
    font-style: italic;
}
.btn-icon {
    background: none; border: none; cursor: pointer;
    color: #6b7280; font-size: 14px; padding: 4px 8px;
    border-radius: 4px;
}
.btn-icon:hover { background: rgba(0,0,0,.06); }

/* ── Layout ─────────────────────────────────────────────────── */
.olt-map-layout {
    display: flex;
    flex: 1;
    overflow: hidden;
}

/* ── Sidebar ─────────────────────────────────────────────────── */
.olt-sidebar {
    width: 200px;
    flex-shrink: 0;
    overflow-y: auto;
    background: var(--sys-surface, #fff);
    border-right: 1px solid var(--sys-outline-variant, #e0e0e0);
    padding: 12px 10px;
    font-size: 12px;
}
.body--dark .olt-sidebar {
    background: var(--sys-surface-2, #1d1d1d);
    border-color: rgba(255,255,255,0.1);
}

.sidebar-title {
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .5px;
    color: #9ca3af;
    font-weight: 600;
    margin-bottom: 6px;
}
.olt-item {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    padding: 5px 0;
    border-bottom: 1px solid rgba(0,0,0,.05);
}
.olt-dot {
    width: 8px; height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 3px;
}
.dot-green { background: #22c55e; }
.dot-grey  { background: #9ca3af; }
.olt-name  { font-weight: 600; }
.olt-ip    { color: #9ca3af; font-size: 10px; }
.olt-empty { color: #9ca3af; font-style: italic; }

.legend-row {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 3px 0;
}
.leg-dot {
    width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
}

/* ── Mapa ────────────────────────────────────────────────────── */
.olt-map-canvas {
    flex: 1;
    position: relative;
    overflow: hidden;
    z-index: 1;
}
.olt-map-leaflet {
    width: 100%;
    height: 100%;
}
</style>
