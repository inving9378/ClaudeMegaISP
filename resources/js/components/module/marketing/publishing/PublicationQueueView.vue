<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-colapub">
            <mkt-encabezado-pantalla
                titulo="Fila de publicación"
                subtitulo="Lo que está programado, en curso, publicado o con problemas"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template v-if="estado !== 'sin_permiso'" #filtros>
                    <q-select v-model="filters.status" :options="statusOptions" label="Estado" clearable dense outlined options-dense emit-value map-options @update:model-value="load" />
                    <q-select v-model="filters.platform" :options="platformOptions" label="Plataforma" clearable dense outlined options-dense emit-value map-options @update:model-value="load" />
                </template>
                <template #acciones>
                    <mkt-boton tipo="crear" etiqueta="Nueva publicación" href="/marketing/publishing/campaign" />
                </template>

                <mkt-estado-vacio
                    v-if="estado === 'sin_permiso'"
                    icono="lock"
                    titulo="No tienes acceso a esta sección"
                    texto="No tienes acceso a esta sección. Pídelo a Irving."
                />
                <mkt-estado-vacio v-else-if="estado === 'error'" icono="cloud_off" titulo="No se pudo cargar la fila" :texto="mensajeError" />
                <mkt-tabla-ejecutiva
                    v-else
                    :columnas="columns"
                    :filas="publications"
                    :cargando="estado === 'cargando'"
                    vacio-icono="campaign"
                    :vacio-titulo="hayFiltros ? 'No hay publicaciones con esos filtros' : 'La fila está vacía'"
                    :vacio-texto="hayFiltros ? 'Quita los filtros para ver todo.' : 'Publica una campaña para llenar la fila.'"
                    @actualizar="load"
                >
                    <template #body-cell-channel="props">
                        <q-td :props="props">
                            <span class="mkt-colapub__canal">
                                <q-icon :name="platformIcon(props.row.pub_channel?.platform)" size="16px" aria-hidden="true" />
                                <span class="mkt-colapub__fuerte">{{ props.row.pub_channel?.name || '—' }}</span>
                            </span>
                        </q-td>
                    </template>
                    <template #body-cell-status="props">
                        <q-td :props="props"><mkt-insignia-estado :texto="statusLabel(props.value)" :tono="statusTono(props.value)" /></q-td>
                    </template>
                    <template #body-cell-scheduled_for="props">
                        <q-td :props="props">
                            <span v-if="props.value">{{ formatoFecha(props.value, { conHora: true }) }}</span>
                            <span v-else class="mkt-colapub__suave">Inmediata</span>
                        </q-td>
                    </template>
                    <template #body-cell-metrics="props">
                        <q-td :props="props">
                            <span v-if="props.row.metrics && (props.row.metrics.likes || props.row.metrics.views)" class="mkt-colapub__metricas">
                                <span v-if="props.row.metrics.likes"><q-icon name="favorite" size="14px" aria-hidden="true" /> {{ formatoNumero(props.row.metrics.likes) }}</span>
                                <span v-if="props.row.metrics.views"><q-icon name="visibility" size="14px" aria-hidden="true" /> {{ formatoNumero(props.row.metrics.views) }}</span>
                            </span>
                            <span v-else class="mkt-colapub__suave">—</span>
                        </q-td>
                    </template>
                    <template #body-cell-actions="props">
                        <q-td :props="props" class="mkt-colapub__acciones">
                            <q-btn v-if="props.row.external_post_url" flat round dense icon="open_in_new" :href="props.row.external_post_url" target="_blank" rel="noopener" aria-label="Ver publicación">
                                <q-tooltip>Ver publicación</q-tooltip>
                            </q-btn>
                            <q-btn
                                v-if="['failed', 'waiting_credentials'].includes(props.row.status)"
                                flat
                                round
                                dense
                                icon="replay"
                                aria-label="Reintentar"
                                :loading="enCurso === `retry-${props.row.id}`"
                                :disable="!!enCurso"
                                @click="retry(props.row)"
                            >
                                <q-tooltip>Reintentar</q-tooltip>
                            </q-btn>
                            <q-btn
                                v-if="['queued', 'scheduled', 'waiting_credentials'].includes(props.row.status)"
                                flat
                                round
                                dense
                                color="negative"
                                icon="cancel"
                                aria-label="Cancelar publicación"
                                :disable="!!enCurso"
                                @click="aCancelar = props.row"
                            >
                                <q-tooltip>Cancelar</q-tooltip>
                            </q-btn>
                            <q-btn
                                v-if="props.row.status === 'published'"
                                flat
                                round
                                dense
                                color="positive"
                                icon="bar_chart"
                                aria-label="Actualizar métricas"
                                :loading="enCurso === `metrics-${props.row.id}`"
                                :disable="!!enCurso"
                                @click="fetchMetrics(props.row)"
                            >
                                <q-tooltip>Actualizar métricas</q-tooltip>
                            </q-btn>
                        </q-td>
                    </template>
                </mkt-tabla-ejecutiva>
            </mkt-encabezado-pantalla>

            <mkt-dialogo :model-value="!!aCancelar" titulo="Cancelar publicación" :guardando="enCurso === 'cancel'" :ancho="420" @update:model-value="(v) => !v && (aCancelar = null)">
                <p class="mkt-colapub__texto">¿Cancelar la publicación #{{ aCancelar?.id }} en {{ aCancelar?.pub_channel?.name || 'este canal' }}? Ya no saldrá.</p>
                <template #acciones>
                    <mkt-boton tipo="volver" etiqueta="No, mantener" :deshabilitado="enCurso === 'cancel'" @click="aCancelar = null" />
                    <mkt-boton tipo="eliminar" etiqueta="Sí, cancelar" :cargando="enCurso === 'cancel'" @click="cancel(aCancelar)" />
                </template>
            </mkt-dialogo>
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../../helpers/toastMsg";
import { mensajeDeError, formatoFecha, formatoNumero } from "../kit";

const ESTADOS = {
    draft: { label: "Borrador", tono: "neutro" },
    queued: { label: "En fila", tono: "info" },
    scheduled: { label: "Programada", tono: "primario" },
    publishing: { label: "Publicando", tono: "aviso" },
    published: { label: "Publicada", tono: "exito" },
    failed: { label: "Fallida", tono: "peligro" },
    waiting_credentials: { label: "Esperando credenciales", tono: "aviso" },
    cancelled: { label: "Cancelada", tono: "neutro" },
};
const ICONOS = { facebook: "fab fa-facebook", instagram: "fab fa-instagram", whatsapp: "fab fa-whatsapp", email: "email" };

/** Mensaje del backend: `mensaje` del contrato o el `error` en texto (p. ej. reintentar algo que no falló). */
const mensaje = (e, porDefecto) => {
    const d = e?.response?.data;
    if (!d?.mensaje && typeof d?.error === "string" && d.error.includes(" ")) return d.error;
    return mensajeDeError(e, porDefecto);
};

/** Fila de publicación (/marketing/publishing/queue) — Fase 1, rediseño con el kit, mismos endpoints.
 *  §2.13: una sola tarjeta como el Listado de Clientes (título + filtros en una fila, tabla dentro). */
export default {
    name: "MarketingPublicationQueueView",
    data() {
        return {
            contexto: null,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            publications: [],
            enCurso: null,
            aCancelar: null,
            filters: { status: null, platform: null },
            statusOptions: Object.entries(ESTADOS)
                .filter(([v]) => v !== "draft")
                .map(([value, e]) => ({ value, label: e.label })),
            platformOptions: [
                { label: "Facebook", value: "facebook" },
                { label: "Instagram", value: "instagram" },
                { label: "WhatsApp", value: "whatsapp" },
                { label: "Correo", value: "email" },
            ],
            columns: [
                { name: "id", label: "#", field: "id", sortable: true, align: "left", ocultarEnMovil: true },
                { name: "channel", label: "Canal", field: (r) => r.pub_channel?.name, align: "left" },
                { name: "status", label: "Estado", field: "status", align: "left" },
                { name: "scheduled_for", label: "Programada", field: "scheduled_for", align: "left" },
                { name: "ab_variant_tag", label: "Variante", field: "ab_variant_tag", align: "left", ocultarEnMovil: true },
                { name: "metrics", label: "Métricas", field: "metrics", align: "left", ocultarEnMovil: true },
                { name: "actions", label: "", field: "id", align: "right" },
            ],
        };
    },
    computed: {
        hayFiltros() {
            return !!(this.filters.status || this.filters.platform);
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        formatoFecha,
        formatoNumero,
        async load() {
            this.estado = "cargando";
            try {
                const params = {};
                if (this.filters.status) params.status = this.filters.status;
                if (this.filters.platform) params.platform = this.filters.platform;
                const { data } = await axios.get("/api/marketing/publishing/publications", { params });
                this.publications = data?.data ?? data ?? [];
                this.estado = "listo";
            } catch (e) {
                if (e?.response?.status === 403) {
                    this.estado = "sin_permiso";
                } else {
                    this.mensajeError = mensajeDeError(e, "Intenta de nuevo en un momento.");
                    this.estado = "error";
                }
            }
        },
        async retry(pub) {
            if (this.enCurso) return;
            this.enCurso = `retry-${pub.id}`;
            try {
                await axios.post(`/api/marketing/publishing/publications/${pub.id}/retry`);
                message("La publicación volvió a la fila para reintentarse.");
                this.load();
            } catch (e) {
                message(mensaje(e, "No se pudo reintentar."), "error");
            } finally {
                this.enCurso = null;
            }
        },
        async cancel(pub) {
            if (!pub || this.enCurso) return;
            this.enCurso = "cancel";
            try {
                await axios.post(`/api/marketing/publishing/publications/${pub.id}/cancel`);
                message("Publicación cancelada.", "info");
                this.aCancelar = null;
                this.load();
            } catch (e) {
                message(mensaje(e, "No se pudo cancelar."), "error");
            } finally {
                this.enCurso = null;
            }
        },
        async fetchMetrics(pub) {
            if (this.enCurso) return;
            this.enCurso = `metrics-${pub.id}`;
            try {
                const { data } = await axios.get(`/api/marketing/publishing/publications/${pub.id}/metrics`);
                const idx = this.publications.findIndex((p) => p.id === pub.id);
                if (idx >= 0) this.publications[idx] = { ...this.publications[idx], metrics: data?.metrics };
                message("Métricas actualizadas.");
            } catch (e) {
                message(mensaje(e, "No se pudieron leer las métricas."), "error");
            } finally {
                this.enCurso = null;
            }
        },
        statusLabel(s) {
            return ESTADOS[s]?.label || s || "—";
        },
        statusTono(s) {
            return ESTADOS[s]?.tono || "neutro";
        },
        platformIcon(p) {
            return ICONOS[p] || "share";
        },
    },
};
</script>

<style scoped>
.mkt-colapub { min-width: 0; }
.mkt-colapub__canal { display: inline-flex; align-items: center; gap: 6px; color: var(--mkt-texto-suave); }
.mkt-colapub__fuerte { font-weight: 600; color: var(--mkt-texto); }
.mkt-colapub__suave { color: var(--mkt-texto-suave); }
.mkt-colapub__metricas { display: inline-flex; gap: var(--mkt-esp-1); font-size: 13px; color: var(--mkt-texto-suave); white-space: nowrap; }
.mkt-colapub__metricas > span { display: inline-flex; align-items: center; gap: 2px; }
.mkt-colapub__acciones { white-space: nowrap; }
.mkt-colapub__texto { margin: 0; font-size: 14px; line-height: 1.5; color: var(--mkt-texto); }
</style>
