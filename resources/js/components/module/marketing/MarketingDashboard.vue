<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-panel-inicio">
            <mkt-encabezado-pantalla
                titulo="Panel"
                :subtitulo="subtitulo"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template #filtros>
                    <q-select
                        v-model="mes"
                        :options="meses"
                        emit-value
                        map-options
                        outlined
                        dense
                        options-dense
                        class="mkt-panel-inicio__mes"
                        aria-label="Mes del panel"
                        :disable="estado === 'cargando'"
                        @update:model-value="cargarResumen"
                    >
                        <template #prepend><q-icon name="calendar_month" size="18px" /></template>
                        <template #option="scope">
                            <q-item v-bind="scope.itemProps" class="mkt-panel-inicio__opcion">
                                <q-item-section>{{ scope.opt.label }}</q-item-section>
                            </q-item>
                        </template>
                    </q-select>
                </template>
                <template #acciones>
                    <mkt-boton tipo="crear" etiqueta="Nueva campaña" href="/marketing/campanas-360/nueva" />
                </template>
            </mkt-encabezado-pantalla>

            <!-- Sin permiso -->
            <mkt-tarjeta-panel v-if="estado === 'sin_permiso'">
                <mkt-estado-vacio
                    icono="lock"
                    titulo="No puedes ver el panel de Marketing"
                    texto="No tienes acceso a Marketing. Si lo necesitas, pídelo a Irving."
                />
            </mkt-tarjeta-panel>

            <!-- Error -->
            <mkt-tarjeta-panel v-else-if="estado === 'error'">
                <mkt-estado-vacio icono="cloud_off" titulo="No se pudo cargar el panel" :texto="mensajeError" />
            </mkt-tarjeta-panel>

            <template v-else>
                <panel-kpis :resumen="resumen" :cargando="estado === 'cargando'" />

                <div class="mkt-panel-inicio__medio">
                    <panel-embudo :embudo="resumen?.embudo" :cargando="estado === 'cargando'" />

                    <mkt-tarjeta-panel titulo="Accesos rápidos" subtitulo="Lo que más se atiende en el día">
                        <nav class="mkt-panel-inicio__accesos" aria-label="Accesos rápidos">
                            <a class="mkt-panel-inicio__acceso" href="/marketing/leads">
                                <span class="mkt-panel-inicio__acceso-icono" aria-hidden="true"><q-icon name="people" size="22px" /></span>
                                <span class="mkt-panel-inicio__acceso-textos">
                                    <span class="mkt-panel-inicio__acceso-titulo">Leads</span>
                                    <span class="mkt-panel-inicio__acceso-sub">{{ textoLeads }}</span>
                                </span>
                                <q-icon name="chevron_right" size="20px" class="mkt-panel-inicio__acceso-flecha" aria-hidden="true" />
                            </a>
                            <a class="mkt-panel-inicio__acceso" href="/marketing/conversations">
                                <span class="mkt-panel-inicio__acceso-icono" aria-hidden="true"><q-icon name="forum" size="22px" /></span>
                                <span class="mkt-panel-inicio__acceso-textos">
                                    <span class="mkt-panel-inicio__acceso-titulo">Conversaciones</span>
                                    <span class="mkt-panel-inicio__acceso-sub">{{ textoConversaciones }}</span>
                                </span>
                                <q-icon name="chevron_right" size="20px" class="mkt-panel-inicio__acceso-flecha" aria-hidden="true" />
                            </a>
                            <a class="mkt-panel-inicio__acceso" href="/marketing/campanas-360">
                                <span class="mkt-panel-inicio__acceso-icono" aria-hidden="true"><q-icon name="rocket_launch" size="22px" /></span>
                                <span class="mkt-panel-inicio__acceso-textos">
                                    <span class="mkt-panel-inicio__acceso-titulo">Campañas 360</span>
                                    <span class="mkt-panel-inicio__acceso-sub">Estrategia, guiones de video y piezas con IA</span>
                                </span>
                                <q-icon name="chevron_right" size="20px" class="mkt-panel-inicio__acceso-flecha" aria-hidden="true" />
                            </a>
                        </nav>
                    </mkt-tarjeta-panel>
                </div>

                <panel-campanas-activas
                    :datos="campanas"
                    :cargando="cargandoCampanas"
                    :error="errorCampanas"
                    @reintentar="cargarCampanas"
                />
            </template>
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
    MktBoton,
    mensajeDeError,
    mesesRecientes,
    formatoNumero,
} from "./kit";
import PanelKpis from "./dashboard/PanelKpis.vue";
import PanelEmbudo from "./dashboard/PanelEmbudo.vue";
import PanelCampanasActivas from "./dashboard/PanelCampanasActivas.vue";

/**
 * Panel de Marketing (/marketing) — Fase 1, contrato §2.4 rutas 1 y 2.
 * Las funciones legado (campañas, plantillas, leads-legacy, stats) se retiraron de la UI [F1-3]:
 * «Nueva campaña» lleva al asistente de Campañas 360 (Fase 2). Solo Meganet (cambio de alcance 2026-11-13): sin
 * selector de empresa ni acceso a «Empresas y equipo».
 */
export default {
    name: "MarketingDashboard",
    components: {
        MktRaiz,
        MktEncabezadoPantalla,
        MktTarjetaPanel,
        MktEstadoVacio,
        MktBoton,
        PanelKpis,
        PanelEmbudo,
        PanelCampanasActivas,
    },
    props: {
        authUserId: { type: Number, default: null },
    },
    data() {
        const meses = mesesRecientes(12);
        return {
            contexto: null,
            meses,
            mes: meses[0].value,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            resumen: null,
            campanas: null,
            cargandoCampanas: true,
            errorCampanas: "",
            conversacionesSinLeer: null,
            peticion: 0,
        };
    },
    computed: {
        etiquetaMes() {
            return this.meses.find((m) => m.value === this.mes)?.label || "";
        },
        subtitulo() {
            return `Resultados de ${this.etiquetaMes}`;
        },
        textoLeads() {
            const n = this.resumen?.kpis?.leads_mes?.valor;
            if (n === null || n === undefined) return "Ver y dar seguimiento";
            return Number(n) === 1 ? "1 lead este mes" : `${formatoNumero(n)} leads este mes`;
        },
        textoConversaciones() {
            const n = this.conversacionesSinLeer;
            if (n === null) return "Bandeja de WhatsApp";
            if (n === 0) return "Todo leído";
            return n === 1 ? "1 conversación sin leer" : `${formatoNumero(n)} conversaciones sin leer`;
        },
    },
    mounted() {
        this.cargarTodo();
    },
    methods: {
        cargarTodo() {
            this.cargarResumen();
            this.cargarCampanas();
            this.cargarSinLeer();
        },
        async cargarResumen() {
            const id = ++this.peticion;
            this.estado = "cargando";
            try {
                const { data } = await axios.get("/api/marketing/dashboard/resumen", { params: { mes: this.mes } });
                if (id !== this.peticion) return; // llegó una respuesta vieja (cambio de mes rápido)
                this.resumen = data;
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
        async cargarCampanas() {
            this.cargandoCampanas = true;
            this.errorCampanas = "";
            try {
                const { data } = await axios.get("/api/marketing/dashboard/campanas-activas", { params: { limite: 5 } });
                this.campanas = data;
            } catch (e) {
                this.errorCampanas = mensajeDeError(e, "Intenta de nuevo en un momento.");
            } finally {
                this.cargandoCampanas = false;
            }
        },
        async cargarSinLeer() {
            try {
                const { data } = await axios.get("/api/marketing/conversations", { params: { unread_only: 1, per_page: 1 } });
                const total = data?.meta?.total ?? data?.total;
                this.conversacionesSinLeer = Number.isFinite(Number(total)) ? Number(total) : null;
            } catch (e) {
                this.conversacionesSinLeer = null;
            }
        },
    },
};
</script>

<style scoped>
.mkt-panel-inicio {
    display: flex;
    flex-direction: column;
    gap: var(--mkt-esp-2);
    min-width: 0;
}
.mkt-panel-inicio > :first-child { margin-bottom: 0; }
.mkt-panel-inicio__mes { width: 200px; }
.mkt-panel-inicio__opcion { --bs-gutter-x: 0; }
.mkt-panel-inicio__opcion > * { width: auto; max-width: none; margin-top: 0; }
.mkt-panel-inicio__medio {
    display: grid;
    gap: var(--mkt-esp-2);
    grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
    align-items: start;
}
.mkt-panel-inicio__accesos { display: flex; flex-direction: column; gap: var(--mkt-esp-1); }
.mkt-panel-inicio__acceso {
    display: flex;
    align-items: center;
    gap: var(--mkt-esp-2);
    padding: 12px;
    border: 1px solid var(--mkt-borde);
    border-radius: 10px;
    color: var(--mkt-texto);
    text-decoration: none;
    transition: border-color 0.15s ease, background-color 0.15s ease;
}
.mkt-panel-inicio__acceso:hover { border-color: var(--mkt-primario); background: var(--mkt-fondo-suave); }
.mkt-panel-inicio__acceso:focus-visible { outline: 2px solid var(--mkt-primario); outline-offset: 2px; }
.mkt-panel-inicio__acceso-icono {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    border-radius: 8px;
    color: var(--mkt-primario);
    background: color-mix(in srgb, var(--mkt-primario) 10%, transparent);
}
.mkt-panel-inicio__acceso-textos { display: flex; flex-direction: column; min-width: 0; flex: 1 1 auto; }
.mkt-panel-inicio__acceso-titulo { font-weight: 600; font-size: 14px; }
.mkt-panel-inicio__acceso-sub { font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-panel-inicio__acceso-flecha { color: var(--mkt-texto-suave); }

@media (max-width: 1023px) {
    .mkt-panel-inicio__medio { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 599px) {
    .mkt-panel-inicio__mes { width: 100%; }
    .mkt-panel-inicio__medio { gap: var(--mkt-esp-2); }
}
@media (prefers-reduced-motion: reduce) {
    .mkt-panel-inicio__acceso { transition: none; }
}
</style>
