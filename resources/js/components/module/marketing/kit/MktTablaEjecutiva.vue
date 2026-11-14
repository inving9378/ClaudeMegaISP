<template>
    <div class="mkt-tabla" :class="{ 'mkt-tabla--con-acciones': conColumnaAcciones }">
        <q-table
            ref="qtabla"
            flat
            class="tabla-listado"
            :rows="filas"
            :columns="columnas"
            :row-key="filaKey"
            :visible-columns="columnasVisibles"
            :grid="esMovil"
            :dark="tema.oscuro"
            card-container-class="mkt-tabla__tarjetas"
            :loading="cargando && filas.length > 0"
            :pagination="paginacionInicial"
            :filter="busqueda"
            :rows-per-page-options="[10, 25, 50]"
            rows-per-page-label="Elementos por página"
            :pagination-label="etiquetaPaginacion"
            :selected-rows-label="etiquetaSeleccion"
            no-data-label="No hay elementos para mostrar"
            no-results-label="No se encontraron resultados"
            loading-label="Cargando…"
            v-bind="$attrs"
            @mouseover="tituloSiCorta"
        >
            <!-- Arriba a la derecha, como el Listado de Clientes: menú de tres puntos y «Buscar». -->
            <template v-if="mostrarBarra" #top="props">
                <div class="mkt-tabla__barra">
                    <mkt-menu-tabla
                        v-if="menu"
                        :columnas="columnasConEtiqueta"
                        :ocultas="ocultasActuales"
                        :con-actualizar="tieneActualizar"
                        :en-pantalla-completa="props.inFullscreen"
                        @update:ocultas="alCambiarOcultas"
                        @actualizar="$emit('actualizar')"
                        @expandir="props.toggleFullscreen"
                    />
                    <q-input
                        v-if="mostrarBuscador"
                        v-model="busqueda"
                        class="mkt-tabla__buscar"
                        dense
                        outlined
                        clearable
                        :dark="tema.oscuro"
                        placeholder="Buscar"
                        aria-label="Buscar en la tabla"
                    />
                </div>
            </template>
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
                    <div v-else-if="busqueda && filas.length" class="mkt-tabla__sin-resultados">No se encontraron resultados</div>
                    <mkt-estado-vacio v-else :icono="vacioIcono" :titulo="vacioTitulo" :texto="vacioTexto" />
                </div>
            </template>
        </q-table>
    </div>
</template>

<script>
import MktEstadoVacio from "./MktEstadoVacio.vue";
import MktMenuTabla from "./MktMenuTabla.vue";
import { tituloSiCorta } from "../../../../helpers/tablaListado";

/**
 * Tabla ejecutiva = marcado y props del Listado de Clientes (DatatableClient.vue), §2.13:
 * q-table `:dark`, textos en español («Elementos por página», «No hay elementos para mostrar»), encabezados sin
 * mayúsculas ni fondo, filas de 48 px, clase `tabla-listado` + `tituloSiCorta` (#1342) y, arriba a la derecha,
 * menú de tres puntos (Actualizar · Expandir · Ver columnas) y «Buscar» (filtra lo cargado; en tablas con
 * carga por servidor —`@request`— no se pinta, porque la pantalla trae su propio buscador).
 * - Reexpone los slots `body-cell-*` de q-table tal cual.
 * - Columna con `ocultarEnMovil: true` → se oculta por debajo de 600 px (el resto se desplaza
 *   dentro de la tabla, nunca la página).
 */
export default {
    name: "MktTablaEjecutiva",
    components: { MktEstadoVacio, MktMenuTabla },
    inheritAttrs: false,
    inject: { tema: { from: "mktTema", default: () => ({ oscuro: false }) } },
    emits: ["actualizar", "update:ocultas"],
    props: {
        columnas: { type: Array, required: true },
        filas: { type: Array, default: () => [] },
        filaKey: { type: String, default: "id" },
        cargando: { type: Boolean, default: false },
        vacioTitulo: { type: String, default: "Sin registros todavía" },
        vacioTexto: { type: String, default: "" },
        vacioIcono: { type: String, default: "inbox" },
        /** «Buscar» arriba a la derecha (solo tablas sin carga por servidor). */
        buscador: { type: Boolean, default: true },
        /** Menú de tres puntos arriba a la derecha. */
        menu: { type: Boolean, default: true },
        /** Columnas ocultas controladas desde fuera (cuando el menú va en la fila del título). */
        ocultas: { type: Array, default: null },
    },
    data() {
        return {
            esMovil: false,
            mql: null,
            paginacionInicial: { rowsPerPage: 10 },
            busqueda: "",
            ocultasPropias: [],
        };
    },
    computed: {
        slotsCelda() {
            return Object.keys(this.$slots).filter((n) => n.startsWith("body-cell-") || n === "body-cell");
        },
        columnasVisibles() {
            return this.columnas
                .filter((c) => !(this.esMovil && c.ocultarEnMovil))
                .filter((c) => !this.ocultasActuales.includes(c.name))
                .map((c) => c.name);
        },
        ocultasActuales() {
            return Array.isArray(this.ocultas) ? this.ocultas : this.ocultasPropias;
        },
        /**
         * La última columna solo queda fija a la derecha (con su sombra) si es la de acciones, como en Clientes.
         * Se reconoce por nombre (acciones/actions) o por no tener etiqueta.
         */
        conColumnaAcciones() {
            const visibles = this.columnas.filter((c) => this.columnasVisibles.includes(c.name));
            const ultima = visibles[visibles.length - 1];
            if (!ultima) return false;
            return ["acciones", "actions", "accion"].includes(String(ultima.name).toLowerCase()) || !ultima.label;
        },
        columnasConEtiqueta() {
            return this.columnas.filter((c) => c.label);
        },
        mostrarBuscador() {
            return this.buscador && !this.$attrs.onRequest && this.filas.length > 0;
        },
        tieneActualizar() {
            return !!this.$.vnode.props?.onActualizar;
        },
        mostrarBarra() {
            return this.filas.length > 0 && (this.menu || this.mostrarBuscador) && !this.$slots.top;
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
        tituloSiCorta,
        alCambiarOcultas(lista) {
            this.ocultasPropias = lista;
            this.$emit("update:ocultas", lista);
        },
        /** Pantalla completa (para el menú puesto fuera de la tabla). */
        expandir() {
            this.$refs.qtabla?.toggleFullscreen?.();
        },
        etiquetaSeleccion(n) {
            return `${n} ${n === 1 ? "elemento seleccionado" : "elementos seleccionados"}`;
        },
        clicTarjeta(evt, props) {
            const fn = this.$attrs.onRowClick;
            if (typeof fn === "function") fn(evt, props.row, props.rowIndex);
        },
        alCambiarAncho(e) {
            this.esMovil = e.matches;
        },
        etiquetaPaginacion(inicio, fin, total) {
            return `${inicio}-${fin} de ${total}`;
        },
    },
};
</script>

<style scoped>
.mkt-tabla { min-width: 0; }
/* `tabla-listado` deja la tabla a lo que piden sus columnas: aquí llena la tarjeta como el Listado de Clientes. */
.mkt-tabla :deep(.q-table) { width: 100%; }
/* …y la última columna solo queda fija con sombra si es la de acciones. */
.mkt-tabla:not(.mkt-tabla--con-acciones) :deep(.q-table thead th:last-child),
.mkt-tabla:not(.mkt-tabla--con-acciones) :deep(.q-table tbody td:last-child) {
    position: static;
    box-shadow: none;
}
.mkt-tabla :deep(.q-table__card) { box-shadow: none; }
.mkt-tabla :deep(.q-table__card:not(.q-table--dark)) { background: transparent; }
.mkt-tabla :deep(.q-table--dark) { background: transparent; }
/* Encabezados como Clientes: 12 px, peso 500, sin mayúsculas ni fondo (color por defecto de Quasar). */
.mkt-tabla :deep(.q-table thead th) {
    font-size: 12px;
    font-weight: 500;
    text-transform: none;
    letter-spacing: normal;
    white-space: nowrap;
}
.mkt-tabla :deep(.q-table__top) { padding: 0 8px 8px; }
.mkt-tabla__barra {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-left: auto;
    min-width: 0;
}
.mkt-tabla__buscar { width: 176px; max-width: 100%; }
.mkt-tabla__buscar :deep(.q-field__control) { height: 40px; }
.mkt-tabla :deep(.q-table__bottom--nodata) { border-top: 0; padding: 0; }
.mkt-tabla__sin-datos { width: 100%; }
.mkt-tabla__sin-resultados { width: 100%; padding: 16px; text-align: center; }
/* Tarjetas (modo móvil) */
.mkt-tabla :deep(.mkt-tabla__tarjetas) { display: flex; flex-direction: column; gap: 8px; }
.mkt-tabla__tarjeta {
    border: 1px solid var(--mkt-borde, #e9e9ef);
    border-radius: var(--mkt-radio, 4px);
    padding: 8px 12px;
    background: var(--mkt-fondo-tarjeta, #fff);
}
.mkt-tabla__tarjeta--clic { cursor: pointer; }
.mkt-tabla__campos,
.mkt-tabla__campos tbody { display: block; width: 100%; }
.mkt-tabla__campos tr {
    display: grid;
    grid-template-columns: minmax(0, 38%) minmax(0, 1fr);
    align-items: center;
    gap: 8px;
}
.mkt-tabla__campos tr > :deep(td:only-child) { grid-column: 1 / -1; text-align: right; }
.mkt-tabla__campos th {
    padding: 8px 0;
    font-size: 12px;
    font-weight: 500;
    text-align: left;
    vertical-align: middle;
}
.mkt-tabla__campos :deep(td) {
    padding: 6px 0;
    font-size: 13px;
    text-align: left;
    vertical-align: middle;
    white-space: normal;
    overflow-wrap: anywhere;
    border: 0;
    min-width: 0;
}
.mkt-tabla__campos tr + tr { border-top: 1px solid var(--mkt-borde, #e9e9ef); }
.mkt-tabla__esqueleto {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 16px;
}
@media (max-width: 599px) {
    .mkt-tabla__barra { width: 100%; }
    .mkt-tabla__buscar { flex: 1 1 auto; width: auto; }
}
</style>
