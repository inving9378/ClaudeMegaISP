<template>
    <q-dialog v-model="dialogo" full-width @hide="alCerrar">
        <q-card style="max-width: 1000px">
            <q-card-section class="row items-center">
                <div class="text-h6">{{ conceptoNombre }}</div>
                <q-space />
                <q-btn flat dense icon="close" v-close-popup />
            </q-card-section>

            <q-separator />

            <q-card-section style="height: 65vh" class="relative-position q-pa-none">
                <q-inner-loading :showing="cargando">
                    <q-spinner size="34px" color="primary" />
                </q-inner-loading>

                <div v-if="!cargando && sinUbicaciones" class="text-center text-grey q-pa-lg">
                    <i class="bi bi-geo-alt" style="font-size: 30px"></i>
                    <div class="q-mt-sm">Sin ubicaciones capturadas todavía.</div>
                </div>

                <div v-if="mapaListo" ref="mapEl" class="dc-mapa-lienzo"></div>
            </q-card-section>
        </q-card>
    </q-dialog>
</template>

<script>
/**
 * Fase 3.3 (item roadmap #752, sub-item #783) — mapa Leaflet de los conceptos
 * de dc_activos con config.mapa=true (torres, postería, fibra, redes
 * troncales, centros de distribución, almacenes y bodegas). Sigue el mismo
 * patrón de Leaflet vía npm ya usado en Flotas
 * (resources/js/components/module/flotas/FleetMap.vue): sin CDN, tiles OSM
 * sin API key, L.circleMarker por fila.
 *
 * Mismo endpoint y patrón de fetch que DcRegistros.vue para el recurso
 * `activos`: trae todo y filtra en el cliente por `metricas.filtros` (igual
 * criterio que `InventarioResolver::resolver()`), aquí sumando el filtro de
 * "tiene lat/lng".
 */
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export default {
    name: 'DcActivosMapa',

    data() {
        return {
            dialogo: false,
            cargando: false,
            mapaListo: false,
            conceptoNombre: '',
            filtro: {},
            filas: [],
            map: null,
        };
    },

    computed: {
        filasConCoords() {
            return this.filas.filter((f) => f.lat !== null && f.lat !== undefined && f.lng !== null && f.lng !== undefined);
        },

        sinUbicaciones() {
            return this.filasConCoords.length === 0;
        },
    },

    beforeUnmount() {
        this.destruirMapa();
    },

    methods: {
        async abrirParaConcepto(concepto) {
            this.conceptoNombre = concepto.nombre;
            this.filtro = (concepto.metricas && concepto.metricas.filtros) || {};
            this.filas = [];
            this.dialogo = true;
            await this.cargar();
        },

        async cargar() {
            this.cargando = true;
            try {
                const { data } = await axios.get('/documentacion-corporativa/api/registros/activos');
                this.filas = (data.data || []).filter((fila) => this.cumpleFiltro(fila));
            } catch (e) {
                this.filas = [];
                this.aviso('No se pudieron cargar las ubicaciones.', 'negative');
            } finally {
                this.cargando = false;
            }

            await this.$nextTick();
            if (this.filasConCoords.length) {
                this.mapaListo = true;
                await this.$nextTick();
                this.pintarMapa();
            } else {
                this.mapaListo = false;
                this.destruirMapa();
            }
        },

        /** Mismo criterio que `InventarioResolver::resolver()`: {columna: valor|[valores]}. */
        cumpleFiltro(fila) {
            return Object.entries(this.filtro).every(([columna, valor]) => (
                Array.isArray(valor) ? valor.includes(fila[columna]) : fila[columna] === valor
            ));
        },

        pintarMapa() {
            if (!this.$refs.mapEl) return;
            this.destruirMapa();

            this.map = L.map(this.$refs.mapEl).setView([19.4326, -99.1332], 11);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(this.map);

            const bounds = [];
            this.filasConCoords.forEach((fila) => {
                const ll = [Number(fila.lat), Number(fila.lng)];
                bounds.push(ll);
                L.circleMarker(ll, {
                    radius: 8, color: '#fff', weight: 2, fillColor: '#0057A8', fillOpacity: 1,
                }).addTo(this.map).bindPopup(this.popupHtml(fila));
            });

            this.map.invalidateSize();
            if (bounds.length) {
                this.map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
            }
        },

        popupHtml(fila) {
            const categoria = this.etiquetaCategoria(fila.categoria);
            return `<div>
                <strong>${fila.nombre ?? ''}</strong><br>
                ${categoria}<br>
                <span class="text-muted">${fila.ubicacion || 'sin dirección registrada'}</span>
            </div>`;
        },

        etiquetaCategoria(categoria) {
            const etiquetas = {
                torre: 'Torre', antena: 'Antena', posteria: 'Postería', fibra: 'Fibra óptica',
                red_troncal: 'Red troncal', equipo_transmision: 'Equipo de transmisión',
                vehiculo: 'Vehículo', computo: 'Cómputo', herramienta: 'Herramienta',
                centro_distribucion: 'Centro de distribución', bodega: 'Bodega', otro: 'Otro',
            };
            return etiquetas[categoria] || categoria || '—';
        },

        destruirMapa() {
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
        },

        alCerrar() {
            this.destruirMapa();
            this.mapaListo = false;
        },

        aviso(mensaje, color) {
            if (this.$q && this.$q.notify) {
                this.$q.notify({ message: mensaje, color, position: 'top' });
            }
        },
    },
};
</script>

<style scoped>
.dc-mapa-lienzo {
    width: 100%;
    height: 100%;
}
</style>
