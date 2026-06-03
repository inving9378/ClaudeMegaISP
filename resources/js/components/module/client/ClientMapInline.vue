<template>
    <div class="cmi">
        <div v-if="hasCoord" ref="mapEl" class="cmi-map"></div>
        <div v-else class="cmi-empty">
            <i class="mdi mdi-map-marker-off"></i>
            <span>Sin ubicación. Usa "Fijar" para definir la coordenada.</span>
        </div>
    </div>
</template>

<script setup>
// Mapa inline read-only (Leaflet/OSM, sin API key ni billing). Solo muestra la
// coordenada del cliente; la edición sigue por el modal de Google ("Fijar/Mover").
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from "vue";
import L from "leaflet";
import "leaflet/dist/leaflet.css";

const props = defineProps({
    lat: { type: [String, Number], default: null },
    lng: { type: [String, Number], default: null },
});

const mapEl = ref(null);
let map = null;
let marker = null;

const coord = computed(() => {
    const la = parseFloat(props.lat);
    const ln = parseFloat(props.lng);
    return isFinite(la) && isFinite(ln) ? [la, ln] : null;
});
const hasCoord = computed(() => coord.value !== null);

function render() {
    if (!coord.value || !mapEl.value) return;
    try {
        if (!map) {
            map = L.map(mapEl.value, { attributionControl: false }).setView(coord.value, 16);
            L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19 }).addTo(map);
            marker = L.circleMarker(coord.value, {
                radius: 9, color: "#fff", weight: 2, fillColor: "#2563eb", fillOpacity: 1,
            }).addTo(map);
        } else {
            map.setView(coord.value, 16);
            marker.setLatLng(coord.value);
        }
        // El contenedor puede montarse oculto (tab/v-if) → recalcular tamaño.
        setTimeout(() => { try { map && map.invalidateSize(); } catch (e) {} }, 150);
    } catch (e) {
        // Defensivo: si Leaflet falla, no rompe la pantalla.
        console.warn("ClientMapInline: no se pudo renderizar el mapa", e);
    }
}

onMounted(async () => {
    await nextTick();
    render();
});

watch(coord, async () => {
    await nextTick();
    render();
});

onBeforeUnmount(() => {
    try { map && map.remove(); } catch (e) {}
    map = null;
    marker = null;
});
</script>

<style scoped>
.cmi-map { width: 100%; height: 240px; border-radius: 10px; overflow: hidden; }
.cmi-empty {
    height: 240px; display: flex; flex-direction: column; align-items: center;
    justify-content: center; gap: 8px; color: #94a3b8; background: #f1f5f9;
    border: 1px dashed #cbd5e1; border-radius: 10px; font-size: 0.85rem; text-align: center; padding: 12px;
}
.cmi-empty i { font-size: 2rem; }
</style>
