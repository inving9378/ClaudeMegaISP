<template>
    <mkt-tarjeta-panel titulo="Campañas activas" :subtitulo="subtitulo">
        <template v-if="total > filas.length" #acciones>
            <span class="mkt-campanas__mas">Mostrando {{ filas.length }} de {{ total }}</span>
        </template>

        <mkt-estado-vacio v-if="error" icono="cloud_off" titulo="No se pudieron cargar las campañas" :texto="error" />

        <mkt-tabla-ejecutiva
            v-else
            :columnas="columnas"
            :filas="filas"
            :cargando="cargando"
            vacio-icono="campaign"
            vacio-titulo="Aún no hay campañas activas"
            vacio-texto="Crea una campaña 360 con IA; cuando esté activa verás aquí su presupuesto, gasto y leads."
        >

            <template #body-cell-nombre="props">
                <q-td :props="props">
                    <div class="mkt-campanas__nombre">{{ props.row.nombre }}</div>
                    <div class="mkt-campanas__tipo">{{ props.row.tipo_etiqueta || props.row.tipo }}</div>
                </q-td>
            </template>

            <template #body-cell-presupuesto="props">
                <q-td :props="props" class="mkt-campanas__monto">
                    {{ formatoMoneda(props.row.presupuesto, props.row.moneda) }}
                    <mkt-insignia-estado v-if="props.row.moneda_distinta" :texto="props.row.moneda" tono="aviso" />
                </q-td>
            </template>

            <template #body-cell-gastado="props">
                <q-td :props="props" class="mkt-campanas__monto">{{ formatoMoneda(props.row.gastado, props.row.moneda) }}</q-td>
            </template>

            <template #body-cell-avance="props">
                <q-td :props="props">
                    <div v-if="props.row.avance_presupuesto !== null && props.row.avance_presupuesto !== undefined" class="mkt-campanas__avance">
                        <q-linear-progress
                            :value="Math.min(Number(props.row.avance_presupuesto) / 100, 1)"
                            :color="Number(props.row.avance_presupuesto) >= 90 ? 'negative' : 'primary'"
                            track-color="grey-3"
                            rounded
                            size="6px"
                            :aria-label="`Avance del presupuesto ${formatoPorcentaje(props.row.avance_presupuesto)}`"
                        />
                        <span>{{ formatoPorcentaje(props.row.avance_presupuesto) }}</span>
                    </div>
                    <span v-else class="mkt-campanas__suave">Sin presupuesto</span>
                </q-td>
            </template>

            <template #body-cell-vigencia="props">
                <q-td :props="props">
                    {{ formatoDia(props.row.inicio) }} – {{ props.row.fin ? formatoDia(props.row.fin) : "sin fin" }}
                </q-td>
            </template>
        </mkt-tabla-ejecutiva>
    </mkt-tarjeta-panel>
</template>

<script>
import MktTarjetaPanel from "../kit/MktTarjetaPanel.vue";
import MktTablaEjecutiva from "../kit/MktTablaEjecutiva.vue";
import MktEstadoVacio from "../kit/MktEstadoVacio.vue";
import MktInsigniaEstado from "../kit/MktInsigniaEstado.vue";
import MktBoton from "../kit/MktBoton.vue";
import { formatoMoneda, formatoNumero, formatoPorcentaje, formatoDia } from "../kit/formato";

/**
 * Campañas activas de la empresa (contrato §2.4 ruta 2). Montos con su moneda, sin convertir.
 */
export default {
    name: "PanelCampanasActivas",
    components: { MktTarjetaPanel, MktTablaEjecutiva, MktEstadoVacio, MktInsigniaEstado, MktBoton },
    props: {
        datos: { type: Object, default: null },
        cargando: { type: Boolean, default: false },
        error: { type: String, default: "" },
    },
    emits: ["reintentar"],
    data() {
        return {
            columnas: [
                { name: "nombre", label: "Campaña", field: "nombre", align: "left" },
                { name: "presupuesto", label: "Presupuesto", field: "presupuesto", align: "right", ocultarEnMovil: true },
                { name: "gastado", label: "Gastado", field: "gastado", align: "right" },
                { name: "avance", label: "Avance", field: "avance_presupuesto", align: "left" },
                { name: "leads", label: "Leads", field: "leads", align: "right", format: (v) => formatoNumero(v) },
                { name: "vigencia", label: "Vigencia", field: "inicio", align: "left", ocultarEnMovil: true },
            ],
        };
    },
    computed: {
        filas() {
            return Array.isArray(this.datos?.data) ? this.datos.data : [];
        },
        total() {
            return Number(this.datos?.total) || 0;
        },
        subtitulo() {
            return "Presupuesto, gasto y leads atribuidos de cada campaña en curso";
        },
    },
    methods: { formatoMoneda, formatoPorcentaje, formatoDia },
};
</script>

<style scoped>
.mkt-campanas__nombre { font-weight: 600; color: var(--mkt-texto); }
.mkt-campanas__tipo { font-size: 12px; color: var(--mkt-texto-suave); }
.mkt-campanas__monto { font-variant-numeric: tabular-nums; white-space: nowrap; }
.mkt-campanas__monto :deep(.mkt-insignia) { margin-left: 6px; }
.mkt-campanas__avance { display: flex; align-items: center; gap: var(--mkt-esp-1); min-width: 110px; }
.mkt-campanas__avance .q-linear-progress { flex: 1 1 auto; }
.mkt-campanas__avance span { white-space: nowrap; font-size: 13px; font-variant-numeric: tabular-nums; color: var(--mkt-texto-suave); }
.mkt-campanas__suave { font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-campanas__mas { font-size: 13px; color: var(--mkt-texto-suave); }
</style>
