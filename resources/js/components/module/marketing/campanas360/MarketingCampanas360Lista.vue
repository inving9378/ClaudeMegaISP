<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-c360-lista">
            <mkt-encabezado-pantalla
                titulo="Campañas 360"
                subtitulo="Campañas con estrategia, guiones de video, textos, anuncios y mensajes generados con IA"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template v-if="estado !== 'sin_permiso'" #filtros>
                    <q-select
                        v-model="filtro"
                        class="mkt-c360-lista__filtro"
                        :options="opcionesFiltro"
                        emit-value
                        map-options
                        dense
                        outlined
                        options-dense
                        label="Estado"
                        :disable="estado === 'cargando'"
                        aria-label="Filtrar por estado"
                        @update:model-value="alFiltrar"
                    />
                </template>
                <template v-if="estado !== 'sin_permiso'" #acciones>
                    <mkt-boton tipo="crear" icono-fa="fa-plus" etiqueta="Nueva campaña" href="/marketing/campanas-360/nueva" />
                </template>

                <mkt-estado-vacio
                    v-if="estado === 'sin_permiso'"
                    icono="lock"
                    titulo="No puedes ver las campañas 360"
                    texto="No tienes acceso a Marketing. Si lo necesitas, pídelo a Irving."
                />

                <mkt-estado-vacio v-else-if="estado === 'error'" icono="cloud_off" titulo="No se pudieron cargar las campañas" :texto="mensajeError" />

                <template v-else>
                    <mkt-tabla-ejecutiva
                        :columnas="columnas"
                        :filas="filas"
                        :cargando="estado === 'cargando'"
                        :pagination="{ rowsPerPage: 0 }"
                        :hide-bottom="filas.length > 0"
                        vacio-icono="rocket_launch"
                        :vacio-titulo="filtro ? 'No hay campañas en este estado' : 'Aún no hay campañas 360'"
                        :vacio-texto="filtro ? 'Prueba con otro filtro o crea una campaña nueva.' : 'Crea tu primera campaña: eliges objetivo, público, oferta y canales, y la IA prepara la estrategia y todas las piezas.'"
                        @actualizar="cargar"
                        @row-click="(_, fila) => abrir(fila)"
                    >

                        <template #body-cell-nombre="props">
                            <q-td :props="props">
                                <a class="mkt-c360-lista__nombre" :href="`/marketing/campanas-360/${props.row.id}`" @click.stop>{{ props.row.nombre }}</a>
                                <div class="mkt-c360-lista__suave">{{ props.row.objetivo_etiqueta || "Sin objetivo" }}</div>
                            </q-td>
                        </template>

                        <template #body-cell-estado="props">
                            <q-td :props="props">
                                <mkt-insignia-estado :texto="props.row.estado_etiqueta || etiquetaEstadoCampana(props.row.estado)" :tono="tonoEstadoCampana(props.row.estado)" />
                            </q-td>
                        </template>

                        <template #body-cell-canales="props">
                            <q-td :props="props">
                                <div class="mkt-c360-lista__canales">
                                    <span v-for="c in props.row.canales || []" :key="c" class="mkt-c360-lista__canal" :title="etiquetaCanal(c)">
                                        <q-icon :name="iconoCanal(c)" size="16px" aria-hidden="true" />
                                        <span class="mkt-c360-lista__canal-txt">{{ etiquetaCanal(c) }}</span>
                                    </span>
                                </div>
                            </q-td>
                        </template>

                        <template #body-cell-presupuesto="props">
                            <q-td :props="props" class="mkt-c360-lista__monto">{{ formatoMXN(props.row.presupuesto_mxn) }}</q-td>
                        </template>

                        <template #body-cell-vigencia="props">
                            <q-td :props="props">{{ vigencia(props.row) }}</q-td>
                        </template>

                        <template #body-cell-generacion="props">
                            <q-td :props="props">
                                <div class="mkt-c360-lista__gen">
                                    <mkt-insignia-estado :texto="estadoGen(props.row).label" :tono="estadoGen(props.row).tono" />
                                    <span v-if="props.row.generacion?.piezas_total" class="mkt-c360-lista__suave">
                                        {{ props.row.generacion.piezas_listas }} de {{ props.row.generacion.piezas_total }} piezas
                                    </span>
                                </div>
                            </q-td>
                        </template>

                        <template #body-cell-videos="props">
                            <q-td :props="props" class="mkt-c360-lista__num">
                                <span v-if="props.row.videos_resumen?.total">
                                    {{ props.row.videos_resumen.listos }} / {{ props.row.videos_resumen.total }}
                                    <q-icon v-if="props.row.videos_resumen.error" name="error" size="16px" class="mkt-c360-lista__error" aria-label="Con error" />
                                </span>
                                <span v-else class="mkt-c360-lista__suave">—</span>
                            </q-td>
                        </template>
                    </mkt-tabla-ejecutiva>

                    <!-- Pie como el del Listado de Clientes: elementos por página, rango y páginas. -->
                    <div v-if="filas.length" class="mkt-c360-lista__paginas">
                        <span>Elementos por página {{ meta.por_pagina || 20 }}</span>
                        <span>{{ textoPaginacion }}</span>
                        <q-pagination
                            v-if="paginas > 1"
                            v-model="pagina"
                            :max="paginas"
                            :max-pages="5"
                            direction-links
                            boundary-numbers
                            :disable="estado === 'cargando'"
                            @update:model-value="cargar"
                        />
                    </div>
                </template>
            </mkt-encabezado-pantalla>
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import {
    MktRaiz,
    MktEncabezadoPantalla,
    MktTarjetaPanel,
    MktEstadoVacio,
    MktTablaEjecutiva,
    MktInsigniaEstado,
    MktBoton,
    mensajeDeError,
    formatoMXN,
    formatoDia,
    formatoNumero,
} from "../kit";
import {
    ESTADOS_CAMPANA,
    ESTADOS_GENERACION,
    etiquetaCanal,
    iconoCanal,
    etiquetaEstadoCampana,
    tonoEstadoCampana,
} from "./catalogos360";

/**
 * Campañas 360 — lista (/marketing/campanas-360). Contrato Fase 2 §2.4 ruta 1 (índice paginado de 20).
 */
export default {
    name: "MarketingCampanas360Lista",
    components: {
        MktRaiz,
        MktEncabezadoPantalla,
        MktTarjetaPanel,
        MktEstadoVacio,
        MktTablaEjecutiva,
        MktInsigniaEstado,
        MktBoton,
    },
    data() {
        return {
            contexto: null,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            filas: [],
            filtro: "",
            pagina: 1,
            meta: { pagina: 1, por_pagina: 20, total: 0 },
            peticion: 0,
            columnas: [
                { name: "nombre", label: "Campaña", field: "nombre", align: "left" },
                { name: "estado", label: "Estado", field: "estado", align: "left" },
                { name: "canales", label: "Canales", field: "canales", align: "left", ocultarEnMovil: true },
                { name: "presupuesto", label: "Presupuesto", field: "presupuesto_mxn", align: "right" },
                { name: "vigencia", label: "Vigencia", field: "fecha_inicio", align: "left", ocultarEnMovil: true },
                { name: "generacion", label: "Generación con IA", field: "generacion", align: "left" },
                { name: "videos", label: "Videos listos", field: "videos_resumen", align: "right", ocultarEnMovil: true },
            ],
        };
    },
    computed: {
        opcionesFiltro() {
            return [{ label: "Todas", value: "" }, ...ESTADOS_CAMPANA.map((e) => ({ label: e.label, value: e.value }))];
        },
        paginas() {
            const por = Number(this.meta.por_pagina) || 20;
            return Math.max(1, Math.ceil((Number(this.meta.total) || 0) / por));
        },
        subtitulo() {
            if (this.estado === "cargando") return "Cargando…";
            const n = Number(this.meta.total) || 0;
            return n === 1 ? "1 campaña" : `${formatoNumero(n)} campañas`;
        },
        textoPaginacion() {
            const por = Number(this.meta.por_pagina) || 20;
            const desde = (this.pagina - 1) * por + 1;
            const hasta = Math.min(this.pagina * por, Number(this.meta.total) || 0);
            return `${desde}-${hasta} de ${formatoNumero(this.meta.total)}`;
        },
    },
    mounted() {
        this.cargar();
    },
    methods: {
        formatoMXN,
        etiquetaCanal,
        iconoCanal,
        etiquetaEstadoCampana,
        tonoEstadoCampana,
        alFiltrar() {
            this.pagina = 1;
            this.cargar();
        },
        async cargar() {
            const id = ++this.peticion;
            this.estado = "cargando";
            try {
                const params = { pagina: this.pagina };
                if (this.filtro) params.estado = this.filtro;
                const { data } = await axios.get("/api/marketing/campanas-360", { params });
                if (id !== this.peticion) return;
                this.filas = Array.isArray(data?.data) ? data.data : [];
                this.meta = { pagina: 1, por_pagina: 20, total: this.filas.length, ...(data?.meta || {}) };
                this.estado = "listo";
            } catch (e) {
                if (id !== this.peticion) return;
                if (e?.response?.status === 403) {
                    this.estado = "sin_permiso";
                    return;
                }
                this.mensajeError = mensajeDeError(e, "Intenta de nuevo en un momento.");
                this.estado = "error";
            }
        },
        abrir(fila) {
            if (fila?.id) window.location.href = `/marketing/campanas-360/${fila.id}`;
        },
        vigencia(fila) {
            if (!fila.fecha_inicio && !fila.fecha_fin) return "—";
            return `${formatoDia(fila.fecha_inicio)} – ${formatoDia(fila.fecha_fin)}`;
        },
        estadoGen(fila) {
            return ESTADOS_GENERACION[fila?.generacion?.estado] || ESTADOS_GENERACION.sin_generar;
        },
    },
};
</script>

<style scoped>
.mkt-c360-lista { min-width: 0; }
.mkt-c360-lista__nombre { font-weight: 600; color: inherit; text-decoration: none; }
.mkt-c360-lista__nombre:hover { color: var(--mkt-primario); text-decoration: underline; }
.mkt-c360-lista__nombre:focus-visible { outline: 2px solid var(--mkt-primario); outline-offset: 2px; }
.mkt-c360-lista__suave { font-size: 12px; color: var(--mkt-texto-suave); }
.mkt-c360-lista__canales { display: flex; flex-wrap: wrap; gap: 4px; }
.mkt-c360-lista__canal {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 12px;
    color: var(--mkt-texto-suave);
    background: var(--mkt-fondo-suave);
    border: 1px solid var(--mkt-borde);
}
.mkt-c360-lista__monto, .mkt-c360-lista__num { font-variant-numeric: tabular-nums; white-space: nowrap; }
.mkt-c360-lista__gen { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; min-width: 0; }
.mkt-c360-lista__error { color: var(--mkt-peligro); }
.mkt-c360-lista__paginas {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px 24px;
    min-height: 50px;
    padding: 4px 8px;
    font-size: 12px;
    border-top: 1px solid var(--mkt-borde);
}
:deep(.q-tr) { cursor: pointer; }

@media (max-width: 1279px) {
    .mkt-c360-lista__canal-txt { display: none; }
}
@media (max-width: 599px) {
    .mkt-c360-lista__paginas { justify-content: flex-start; }
}
</style>
