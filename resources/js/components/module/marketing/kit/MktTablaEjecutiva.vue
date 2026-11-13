<template>
    <div class="mkt-tabla">
        <q-table
            flat
            :rows="filas"
            :columns="columnas"
            :row-key="filaKey"
            :visible-columns="columnasVisibles"
            :grid="esMovil"
            card-container-class="mkt-tabla__tarjetas"
            :loading="cargando && filas.length > 0"
            :pagination="paginacionInicial"
            :rows-per-page-options="[10, 25, 50]"
            rows-per-page-label="Filas por página"
            :pagination-label="etiquetaPaginacion"
            :hide-bottom="filas.length > 0 && filas.length <= 10"
            no-data-label="Sin registros"
            loading-label="Cargando…"
            v-bind="$attrs"
        >
            <template v-for="nombre in slotsCelda" #[nombre]="props" :key="nombre">
                <slot :name="nombre" v-bind="props" />
            </template>
            <!-- Móvil (< 600 px): cada fila es una tarjeta «etiqueta: valor»; reusa los mismos slots body-cell-*. -->
            <template #item="props">
                <div class="mkt-tabla__tarjeta" :class="{ 'mkt-tabla__tarjeta--clic': !!$attrs.onRowClick }" @click="clicTarjeta($event, props)">
                    <table class="mkt-tabla__campos">
                        <tbody>
                            <tr v-for="col in props.cols" :key="col.name">
                                <th v-if="col.label" scope="row">{{ col.label }}</th>
                                <slot
                                    v-if="$slots[`body-cell-${col.name}`]"
                                    :name="`body-cell-${col.name}`"
                                    v-bind="{ ...props, col, value: col.value }"
                                />
                                <td v-else>{{ col.value }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
            <template #no-data>
                <div class="mkt-tabla__sin-datos">
                    <div v-if="cargando" class="mkt-tabla__esqueleto" aria-busy="true" aria-label="Cargando">
                        <q-skeleton v-for="n in 4" :key="n" type="text" height="28px" />
                    </div>
                    <mkt-estado-vacio v-else :icono="vacioIcono" :titulo="vacioTitulo" :texto="vacioTexto">
                        <template v-if="$slots['vacio-accion']" #accion>
                            <slot name="vacio-accion" />
                        </template>
                    </mkt-estado-vacio>
                </div>
            </template>
        </q-table>
    </div>
</template>

<script>
import MktEstadoVacio from "./MktEstadoVacio.vue";

/**
 * Tabla ejecutiva: q-table con encabezados claros, estado vacío y de carga del kit.
 * - Reexpone los slots `body-cell-*` de q-table tal cual.
 * - Slot propio `vacio-accion` (botón del estado vacío).
 * - Columna con `ocultarEnMovil: true` → se oculta por debajo de 600 px (el resto se desplaza
 *   dentro de la tabla, nunca la página).
 */
export default {
    name: "MktTablaEjecutiva",
    components: { MktEstadoVacio },
    inheritAttrs: false,
    props: {
        columnas: { type: Array, required: true },
        filas: { type: Array, default: () => [] },
        filaKey: { type: String, default: "id" },
        cargando: { type: Boolean, default: false },
        vacioTitulo: { type: String, default: "Sin registros todavía" },
        vacioTexto: { type: String, default: "" },
        vacioIcono: { type: String, default: "inbox" },
    },
    data() {
        return {
            esMovil: false,
            mql: null,
            paginacionInicial: { rowsPerPage: 10 },
        };
    },
    computed: {
        slotsCelda() {
            return Object.keys(this.$slots).filter((n) => n.startsWith("body-cell-") || n === "body-cell");
        },
        columnasVisibles() {
            return this.columnas
                .filter((c) => !(this.esMovil && c.ocultarEnMovil))
                .map((c) => c.name);
        },
    },
    mounted() {
        if (window.matchMedia) {
            this.mql = window.matchMedia("(max-width: 599px)");
            this.esMovil = this.mql.matches;
            this.mql.addEventListener
                ? this.mql.addEventListener("change", this.alCambiarAncho)
                : this.mql.addListener(this.alCambiarAncho);
        }
    },
    beforeUnmount() {
        if (!this.mql) return;
        this.mql.removeEventListener
            ? this.mql.removeEventListener("change", this.alCambiarAncho)
            : this.mql.removeListener(this.alCambiarAncho);
    },
    methods: {
        clicTarjeta(evt, props) {
            const fn = this.$attrs.onRowClick;
            if (typeof fn === "function") fn(evt, props.row, props.rowIndex);
        },
        alCambiarAncho(e) {
            this.esMovil = e.matches;
        },
        etiquetaPaginacion(inicio, fin, total) {
            return `${inicio}–${fin} de ${total}`;
        },
    },
};
</script>

<style scoped>
.mkt-tabla { min-width: 0; }
.mkt-tabla :deep(.q-table__card) {
    background: transparent;
    color: inherit;
    box-shadow: none;
}
.mkt-tabla :deep(.q-table) { color: var(--mkt-texto, #343a40); }
.mkt-tabla :deep(.q-table thead th) {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: var(--mkt-texto-suave, #74788d);
    background: var(--mkt-fondo-suave, #f8f8fb);
    border-bottom: 1px solid var(--mkt-borde, #e9e9ef);
    white-space: nowrap;
}
.mkt-tabla :deep(.q-table tbody td) {
    font-size: 14px;
    height: 52px;
    border-bottom: 1px solid var(--mkt-borde, #e9e9ef);
}
.mkt-tabla :deep(.q-table tbody tr:last-child td) { border-bottom: 0; }
.mkt-tabla :deep(.q-table__bottom) {
    color: var(--mkt-texto-suave, #74788d);
    border-top: 1px solid var(--mkt-borde, #e9e9ef);
}
.mkt-tabla :deep(.q-table__bottom--nodata) { border-top: 0; padding: 0; }
.mkt-tabla__sin-datos { width: 100%; }
/* Tarjetas (modo móvil) */
.mkt-tabla :deep(.mkt-tabla__tarjetas) { display: flex; flex-direction: column; gap: var(--mkt-esp-1, 8px); }
.mkt-tabla__tarjeta {
    border: 1px solid var(--mkt-borde, #e9e9ef);
    border-radius: var(--mkt-radio, 8px);
    padding: var(--mkt-esp-1, 8px) var(--mkt-esp-2, 16px);
    background: var(--mkt-fondo-tarjeta, #fff);
}
.mkt-tabla__tarjeta--clic { cursor: pointer; }
.mkt-tabla__campos,
.mkt-tabla__campos tbody { display: block; width: 100%; }
.mkt-tabla__campos tr {
    display: grid;
    grid-template-columns: minmax(0, 38%) minmax(0, 1fr);
    align-items: center;
}
.mkt-tabla__campos tr > :deep(td:only-child) { grid-column: 1 / -1; text-align: right; }
.mkt-tabla__campos th {
    padding: 8px 8px 8px 0;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    text-align: left;
    vertical-align: middle;
    color: var(--mkt-texto-suave, #74788d);
}
.mkt-tabla__campos :deep(td) {
    padding: 6px 0;
    font-size: 14px;
    text-align: left;
    vertical-align: middle;
    white-space: normal;
    overflow-wrap: anywhere;
    border: 0;
}
.mkt-tabla__campos tr + tr { border-top: 1px solid var(--mkt-borde, #e9e9ef); }
.mkt-tabla__esqueleto {
    display: flex;
    flex-direction: column;
    gap: var(--mkt-esp-1, 8px);
    padding: var(--mkt-esp-2, 16px);
}
</style>
