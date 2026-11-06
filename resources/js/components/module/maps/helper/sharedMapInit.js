import L from "leaflet";
import { getMapRenderConfig } from "./request";

// CDMX: fallback cuando no hay credencial de mapas configurada.
const DEFAULT_CENTER = [19.4326, -99.1332];
const OSM_TILE_URL = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
const OSM_ATTRIBUTION =
    '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

// Inicializa un mapa Leaflet base (OSM) honrando centro/zoom del servicio de
// mapas compartido (credenciales-google-maps/render-config) en vez de
// hardcodear CDMX. No expone la api_key (solo se usa lat/lng/zoom).
export const initBaseLeafletMap = async (
    container,
    { zoom = 12, mapOptions = {}, tileOptions = {} } = {}
) => {
    let center = DEFAULT_CENTER;
    let resolvedZoom = zoom;

    try {
        const config = await getMapRenderConfig();
        const lat = parseFloat(config?.latitude);
        const lng = parseFloat(config?.longitude);
        if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
            center = [lat, lng];
        }
        if (config?.zoom) {
            resolvedZoom = config.zoom;
        }
    } catch (e) {
        // Config no disponible: se mantiene el centro/zoom por defecto (CDMX).
    }

    const map = L.map(container, { center, zoom: resolvedZoom, ...mapOptions });

    L.tileLayer(OSM_TILE_URL, {
        maxZoom: 19,
        attribution: OSM_ATTRIBUTION,
        ...tileOptions,
    }).addTo(map);

    return map;
};
