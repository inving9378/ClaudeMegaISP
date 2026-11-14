<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-cola">
            <mkt-encabezado-pantalla
                titulo="Fila de producción"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template v-if="estado !== 'sin_permiso'" #filtros>
                    <q-select
                        v-model="filters.status"
                        :options="statusOptions"
                        emit-value
                        map-options
                        dense
                        outlined
                        clearable
                        options-dense
                        label="Estado"
                        @update:model-value="cambiarFiltro"
                    />
                </template>
                <template #acciones>
                    <mkt-boton v-if="puedeGenerar" tipo="crear" etiqueta="Nuevo video" href="/marketing/video-generator" />
                </template>

                <div v-if="estado === 'sin_permiso' || estado === 'error'" class="mkt-cola__estado">
                    <mkt-estado-vacio
                        v-if="estado === 'sin_permiso'"
                        icono="lock"
                        titulo="No puedes ver la fila de producción"
                        texto="No tienes acceso a esta sección. Pídelo a Irving."
                    />
                    <mkt-estado-vacio v-else icono="cloud_off" titulo="No se pudo cargar la fila de producción" :texto="mensajeError" />
                </div>

                <mkt-tabla-ejecutiva
                    v-else
                    :columnas="columnas"
                    :filas="items"
                    :cargando="estado === 'cargando' || refrescando"
                    :pagination="paginacionTabla"
                    :rows-per-page-options="[paginacionTabla.rowsPerPage]"
                    :table-row-class-fn="claseFila"
                    vacio-icono="video_library"
                    :vacio-titulo="filters.status ? 'No hay videos con ese estado' : 'Aún no hay videos'"
                    :vacio-texto="filters.status ? 'Quita el filtro para ver todos.' : 'Genera tu primer video desde una plantilla.'"
                    @request="alPedirPagina"
                    @actualizar="load({ silencioso: true })"
                >

                    <template #body-cell-video="props">
                        <q-td :props="props">
                            <div class="mkt-cola__video">
                                <span class="mkt-cola__nombre">Video #{{ props.row.id }}</span>
                                <span class="mkt-cola__meta">
                                    <span v-if="props.row.generation_metadata?.template_slug">{{ props.row.generation_metadata.template_slug }}</span>
                                    <span v-if="props.row.generation_metadata?.aspect_ratio"> · {{ props.row.generation_metadata.aspect_ratio }}</span>
                                    <!-- Fase 2: videos producidos desde una Campaña 360 -->
                                    <template v-if="props.row.source_campaign_id">
                                        · <a class="mkt-cola__campana" :href="`/marketing/campanas-360/${props.row.source_campaign_id}`" @click.stop>Ver campaña</a>
                                    </template>
                                </span>
                            </div>
                        </q-td>
                    </template>

                    <template #body-cell-estado="props">
                        <q-td :props="props">
                            <div class="mkt-cola__estado-celda">
                                <mkt-insignia-estado :texto="statusLabel(props.row.status)" :tono="tono(props.row.status)" />
                                <div v-if="props.row.status === 'generating'" class="mkt-cola__progreso">
                                    <q-linear-progress :value="(Number(props.row.render_progress) || 0) / 100" rounded size="6px" class="mkt-cola__barra" />
                                    <span class="mkt-cola__meta">{{ props.row.render_stage || 'Procesando…' }} · {{ Number(props.row.render_progress) || 0 }} %</span>
                                </div>
                                <div v-if="props.row.status === 'failed'" class="mkt-cola__error">{{ ultimoError(props.row) }}</div>
                            </div>
                        </q-td>
                    </template>

                    <template #body-cell-acciones="props">
                        <q-td :props="props">
                            <div class="mkt-cola__acciones">
                                <q-btn v-if="props.row.status === 'generating'" flat round dense icon="autorenew" :aria-label="`Actualizar avance del video ${props.row.id}`" @click.stop="pollOnce(props.row)">
                                    <q-tooltip>Actualizar avance</q-tooltip>
                                </q-btn>
                                <q-btn
                                    v-if="props.row.status === 'ready' && props.row.output_path"
                                    flat
                                    round
                                    dense
                                    icon="download"
                                    :href="'/api/marketing/generated-content/' + props.row.id + '/download'"
                                    target="_blank"
                                    :aria-label="`Descargar video ${props.row.id}`"
                                >
                                    <q-tooltip>Descargar</q-tooltip>
                                </q-btn>
                                <q-btn v-if="puedeEliminar" flat round dense icon="delete" class="mkt-cola__borrar" :aria-label="`Eliminar video ${props.row.id}`" @click.stop="confirmDelete(props.row)">
                                    <q-tooltip>Eliminar</q-tooltip>
                                </q-btn>
                            </div>
                        </q-td>
                    </template>
                </mkt-tabla-ejecutiva>
            </mkt-encabezado-pantalla>

            <mkt-dialogo v-model="deleteDialog" titulo="Eliminar video" :guardando="deleting" :ancho="420">
                <p class="mkt-cola__texto">¿Eliminar el video #{{ deleteTarget?.id }}? Esta acción no se puede deshacer.</p>
                <template #acciones>
                    <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="deleting" @click="deleteDialog = false" />
                    <mkt-boton tipo="eliminar" etiqueta="Eliminar" :cargando="deleting" @click="deleteItem" />
                </template>
            </mkt-dialogo>
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../helpers/toastMsg";
import { currentSearch } from "../../../hook/comunValues";
import { mensajeDeError, formatoFecha, formatoNumero, puedePermiso } from "./kit";

/** Fecha del sistema: dd/mm/aaaa hh:mm (hora de México). */
const fechaCorta = (v) => {
    if (!v) return "—";
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return "—";
    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit", hour12: false,
        timeZone: "America/Mexico_City",
    }).format(d).replace(",", "");
};

const ESTADOS = {
    pending: { label: "Pendiente", icon: "hourglass_empty", tono: "neutro" },
    generating: { label: "Generando", icon: "sync", tono: "info" },
    ready: { label: "Listo", icon: "check_circle", tono: "exito" },
    failed: { label: "Fallido", icon: "error", tono: "peligro" },
};

/**
 * Fila de producción (/marketing/video-queue) — Fase 1, rediseño con el kit, mismo funcionamiento.
 * §2.13: una sola tarjeta como el Listado de Clientes (título + filtro «Estado» en la fila, tabla adentro).
 */
export default {
    name: "MarketingVideoQueueView",
    data() {
        return {
            contexto: null,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            refrescando: false,
            deleting: false,
            items: [],
            filters: { status: null },
            pagination: { current_page: 1, last_page: 1, total: 0, per_page: 15 },
            columnas: [
                { name: "video", label: "Video", field: "id", align: "left" },
                { name: "estado", label: "Estado", field: "status", align: "left" },
                { name: "fecha", label: "Fecha", field: "created_at", align: "left", format: (v) => fechaCorta(v), ocultarEnMovil: false },
                { name: "duracion", label: "Duración", field: (r) => r, align: "left", format: (r) => (r.status === "ready" ? this.duracion(r) : "—") },
                { name: "acciones", label: "Acciones", field: "id", align: "right" },
            ],
            deleteDialog: false,
            deleteTarget: null,
            highlightId: null,
            pollTimer: null,
            statusOptions: Object.entries(ESTADOS).map(([value, e]) => ({ value, label: e.label })),
        };
    },
    computed: {
        puedeGenerar() {
            return puedePermiso(this.$store, "generate-video-content");
        },
        puedeEliminar() {
            return puedePermiso(this.$store, "delete-video-content");
        },
        subtitulo() {
            return this.estado === "listo" ? `${formatoNumero(this.pagination.total)} en total` : "";
        },
        /** Paginación por servidor en el formato de q-table (como el Listado de Clientes). */
        paginacionTabla() {
            return {
                page: this.pagination.current_page,
                rowsPerPage: this.pagination.per_page || 15,
                rowsNumber: this.pagination.total,
            };
        },
    },
    mounted() {
        const id = parseInt(new URLSearchParams(currentSearch()).get("id"), 10);
        if (id > 0) this.highlightId = id;
        this.load();
        this.pollTimer = setInterval(() => {
            if (this.estado === "listo" && this.items.some((i) => i.status === "generating" || i.status === "pending")) {
                this.load({ silencioso: true });
            }
        }, 5000);
    },
    beforeUnmount() {
        if (this.pollTimer) clearInterval(this.pollTimer);
    },
    methods: {
        formatoFecha,
        async load({ silencioso = false } = {}) {
            if (silencioso) this.refrescando = true;
            else this.estado = "cargando";
            try {
                const params = { page: this.pagination.current_page };
                if (this.filters.status) params.status = this.filters.status;
                const { data } = await axios.get("/api/marketing/generated-content", { params });
                this.items = data?.data ?? [];
                this.pagination = {
                    current_page: data?.current_page ?? 1,
                    last_page: data?.last_page ?? 1,
                    total: data?.total ?? 0,
                    per_page: data?.per_page ?? this.pagination.per_page,
                };
                this.estado = "listo";
            } catch (e) {
                if (e?.response?.status === 403) {
                    this.estado = "sin_permiso";
                } else if (!silencioso || this.estado !== "listo") {
                    this.mensajeError = mensajeDeError(e, "Intenta de nuevo en un momento.");
                    this.estado = "error";
                }
            } finally {
                this.refrescando = false;
            }
        },
        alPedirPagina({ pagination }) {
            if (!pagination || pagination.page === this.pagination.current_page) return;
            this.pagination.current_page = pagination.page;
            this.load();
        },
        claseFila(fila) {
            return fila.id === this.highlightId ? "mkt-cola__fila--resaltada" : "";
        },
        cambiarFiltro() {
            this.pagination.current_page = 1;
            this.load();
        },
        async pollOnce(item) {
            try {
                const { data } = await axios.get(`/api/marketing/generated-content/${item.id}/progress`);
                const idx = this.items.findIndex((i) => i.id === item.id);
                if (idx !== -1) this.items[idx] = { ...this.items[idx], ...data };
            } catch (e) {
                /* el sondeo periódico lo vuelve a intentar */
            }
        },
        confirmDelete(item) {
            this.deleteTarget = item;
            this.deleteDialog = true;
        },
        async deleteItem() {
            if (!this.deleteTarget || this.deleting) return;
            this.deleting = true;
            try {
                await axios.delete(`/api/marketing/generated-content/${this.deleteTarget.id}`);
                this.deleteDialog = false;
                message("Video eliminado.");
                this.deleteTarget = null;
                await this.load({ silencioso: true });
            } catch (e) {
                message(mensajeDeError(e, "No se pudo eliminar el video."), "error");
            } finally {
                this.deleting = false;
            }
        },
        duracion(item) {
            const s = Number(item.generation_metadata?.duration_sec);
            return Number.isFinite(s) && s > 0 ? `${s.toFixed(1)} s` : "—";
        },
        ultimoError(item) {
            const log = Array.isArray(item.render_log) ? item.render_log : [];
            return log.slice(-1)[0]?.error || "La producción del video falló. Revisa la plantilla e intenta de nuevo.";
        },
        statusIcon(s) {
            return ESTADOS[s]?.icon ?? "help";
        },
        statusLabel(s) {
            return ESTADOS[s]?.label ?? s;
        },
        tono(s) {
            return ESTADOS[s]?.tono ?? "neutro";
        },
    },
};
</script>

<style scoped>
.mkt-cola { min-width: 0; }
.mkt-cola__estado { padding: 0 16px 16px; }
.mkt-cola__video,
.mkt-cola__estado-celda { display: flex; flex-direction: column; gap: 4px; min-width: 0; white-space: normal; }
.mkt-cola__nombre { font-weight: 500; }
.mkt-cola__meta { font-size: 12px; color: var(--mkt-texto-suave); overflow-wrap: anywhere; }
.mkt-cola__progreso { display: flex; flex-direction: column; gap: 4px; min-width: 160px; max-width: 280px; }
.mkt-cola__barra { color: var(--mkt-info); }
.mkt-cola__campana { font-weight: 500; text-decoration: none; }
.mkt-cola__campana:hover { text-decoration: underline; }
.mkt-cola__error { font-size: 12px; color: var(--mkt-peligro); overflow-wrap: anywhere; max-width: 320px; }
.mkt-cola__acciones { display: inline-flex; align-items: center; gap: 4px; }
.mkt-cola__borrar { color: var(--mkt-peligro); }
.mkt-cola :deep(.mkt-cola__fila--resaltada > td:first-child) { box-shadow: inset 3px 0 0 var(--mkt-carpeta); }
.mkt-cola__texto { margin: 0; font-size: 14px; line-height: 1.5; color: var(--mkt-texto); }
@media (max-width: 599px) {
    .mkt-cola__estado { padding: 0 12px 12px; }
    .mkt-cola__progreso { min-width: 0; }
}
</style>
