<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-plantillas">
            <mkt-encabezado-pantalla
                titulo="Plantillas"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template v-if="estado !== 'sin_permiso'" #filtros>
                    <q-input
                        v-model="filters.search"
                        class="mkt-filtro--busqueda"
                        dense
                        outlined
                        clearable
                        placeholder="Buscar plantilla…"
                        aria-label="Buscar plantilla"
                        @update:model-value="debouncedLoad"
                    >
                        <template #prepend><q-icon name="search" /></template>
                    </q-input>
                    <q-select
                        v-model="filters.category"
                        :options="categoryOptions"
                        emit-value
                        map-options
                        dense
                        outlined
                        clearable
                        options-dense
                        label="Categoría"
                        @update:model-value="load"
                    />
                    <q-select
                        v-model="filters.aspect_ratio"
                        :options="ratioOptions"
                        emit-value
                        map-options
                        dense
                        outlined
                        clearable
                        options-dense
                        label="Formato"
                        @update:model-value="load"
                    />
                </template>
                <template #acciones>
                    <mkt-boton v-if="estado !== 'sin_permiso' && puedeGenerar" tipo="crear" etiqueta="Generar video" href="/marketing/video-generator" />
                </template>

                <div class="mkt-plantillas__contenido">
                    <mkt-estado-vacio
                        v-if="estado === 'sin_permiso'"
                        icono="lock"
                        titulo="No puedes ver las plantillas de video"
                        texto="No tienes acceso a esta sección. Pídelo a Irving."
                    />

                    <mkt-estado-vacio v-else-if="estado === 'error'" icono="cloud_off" titulo="No se pudieron cargar las plantillas" :texto="mensajeError" />

                    <div v-else-if="estado === 'cargando'" class="mkt-plantillas__rejilla" aria-busy="true">
                        <div v-for="n in 8" :key="n" class="mkt-plantillas__tarjeta">
                            <q-skeleton height="160px" square />
                            <div class="mkt-plantillas__cuerpo">
                                <q-skeleton type="text" width="70%" />
                                <q-skeleton type="text" width="90%" />
                            </div>
                        </div>
                    </div>

                    <mkt-estado-vacio
                        v-else-if="templates.length === 0"
                        icono="video_library"
                        titulo="No hay plantillas con esos filtros"
                        texto="Quita o cambia los filtros para ver todo el catálogo." />

                    <div v-else class="mkt-plantillas__rejilla">
                        <article
                            v-for="tpl in templates"
                            :key="tpl.id"
                            class="mkt-plantillas__tarjeta mkt-plantillas__tarjeta--clic"
                            tabindex="0"
                            role="button"
                            :aria-label="`Ver detalle de ${tpl.name}`"
                            @click="selectTemplate(tpl)"
                            @keydown.enter.prevent="selectTemplate(tpl)"
                        >
                            <div class="mkt-plantillas__miniatura">
                                <img v-if="tpl.preview_thumbnail" :src="'/storage/' + tpl.preview_thumbnail" :alt="`Miniatura de ${tpl.name}`" loading="lazy" />
                                <div v-else class="mkt-plantillas__sin-miniatura">
                                    <q-icon name="videocam" size="40px" aria-hidden="true" />
                                    <span>{{ ratioLabel(tpl.aspect_ratios?.[0]) }}</span>
                                </div>
                            </div>
                            <div class="mkt-plantillas__cuerpo">
                                <h3 class="mkt-plantillas__nombre">{{ tpl.name }}</h3>
                                <p class="mkt-plantillas__descripcion">{{ tpl.description }}</p>
                                <div class="mkt-plantillas__etiquetas">
                                    <mkt-insignia-estado :texto="categoryLabel(tpl.category)" tono="primario" />
                                    <mkt-insignia-estado v-if="tpl.aspect_ratios?.[0]" :texto="tpl.aspect_ratios[0]" tono="neutro" />
                                    <mkt-insignia-estado v-if="tpl.requires_voiceover" texto="Voz" tono="exito" />
                                    <mkt-insignia-estado v-if="soloCampanas360(tpl)" texto="Solo desde Campañas 360" tono="info" />
                                </div>
                            </div>
                            <div v-if="!soloCampanas360(tpl)" class="mkt-plantillas__pie">
                                <mkt-boton tipo="enlace" chico etiqueta="Usar plantilla" @click.stop="goGenerate(tpl)" />
                            </div>
                        </article>
                    </div>
                </div>
            </mkt-encabezado-pantalla>

            <!-- Detalle -->
            <mkt-dialogo v-model="detailDialog" :titulo="selected?.name || 'Plantilla'" :ancho="560">
                <template v-if="selected">
                    <p class="mkt-plantillas__texto">{{ selected.description }}</p>
                    <dl class="mkt-plantillas__datos">
                        <div><dt>Categoría</dt><dd>{{ categoryLabel(selected.category) }}</dd></div>
                        <div><dt>Formatos</dt><dd>{{ (selected.aspect_ratios || []).join(', ') || '—' }}</dd></div>
                        <div><dt>Duración</dt><dd>{{ selected.duration_seconds ? `~${selected.duration_seconds} s` : '—' }}</dd></div>
                        <div><dt>Tiempo de producción</dt><dd>{{ selected.estimated_render_seconds ? `~${selected.estimated_render_seconds} s` : '—' }}</dd></div>
                    </dl>
                    <div>
                        <div class="mkt-plantillas__subtitulo">Datos que pide</div>
                        <div v-if="requiredVars.length" class="mkt-plantillas__etiquetas">
                            <mkt-insignia-estado v-for="v in requiredVars" :key="v.key" :texto="v.label || v.key" tono="aviso" />
                        </div>
                        <p v-else class="mkt-plantillas__texto-suave">No pide datos obligatorios.</p>
                    </div>
                </template>
                <template #acciones>
                    <mkt-boton tipo="volver" etiqueta="Cerrar" @click="detailDialog = false" />
                    <mkt-boton v-if="!soloCampanas360(selected)" tipo="guardar" etiqueta="Usar esta plantilla" @click="goGenerate(selected)" />
                    <mkt-boton v-else tipo="guardar" etiqueta="Ir a Campañas 360" href="/marketing/campanas-360" />
                </template>
            </mkt-dialogo>
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { mensajeDeError, puedePermiso } from "./kit";

/** Plantillas de video (/marketing/video-templates) — Fase 1, rediseño con el kit. Solo lectura. */
export default {
    name: "MarketingVideoTemplatesView",
    data() {
        return {
            contexto: null,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            templates: [],
            selected: null,
            detailDialog: false,
            filters: { search: "", category: null, aspect_ratio: null },
            categoryOptions: [
                { label: "Promo de plan", value: "plan_promo" },
                { label: "Anuncio de cobertura", value: "coverage_announcement" },
                { label: "Testimonio", value: "testimonial" },
                { label: "Recordatorio de pago", value: "payment_reminder" },
                { label: "Bienvenida", value: "welcome" },
                { label: "Oferta flash", value: "flash_promo" },
                { label: "Aviso de mantenimiento", value: "maintenance_notice" },
                { label: "Genérico", value: "generic" },
                { label: "Guion por escenas", value: "guion_escenas" },
            ],
            ratioOptions: [
                { label: "Vertical 9:16", value: "9:16" },
                { label: "Cuadrado 1:1", value: "1:1" },
                { label: "Horizontal 16:9", value: "16:9" },
            ],
            _timer: null,
        };
    },
    computed: {
        puedeGenerar() {
            return puedePermiso(this.$store, "generate-video-content");
        },
        requiredVars() {
            return (this.selected?.schema?.variables ?? []).filter((v) => v.required);
        },
        hayFiltros() {
            return !!(this.filters.search || this.filters.category || this.filters.aspect_ratio);
        },
    },
    mounted() {
        this.load();
    },
    beforeUnmount() {
        clearTimeout(this._timer);
    },
    methods: {
        async load() {
            this.estado = "cargando";
            try {
                const params = {};
                if (this.filters.search) params.search = this.filters.search;
                if (this.filters.category) params.category = this.filters.category;
                if (this.filters.aspect_ratio) params.aspect_ratio = this.filters.aspect_ratio;
                const { data } = await axios.get("/api/marketing/video-templates", { params });
                this.templates = data?.data ?? [];
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
        debouncedLoad() {
            clearTimeout(this._timer);
            this._timer = setTimeout(() => this.load(), 350);
        },
        limpiarFiltros() {
            this.filters = { search: "", category: null, aspect_ratio: null };
            this.load();
        },
        selectTemplate(tpl) {
            this.selected = tpl;
            this.detailDialog = true;
        },
        /** Fase 2: las plantillas «Guion por escenas» reciben escenas desde Campañas 360; no se generan a mano. */
        soloCampanas360(tpl) {
            return tpl?.category === "guion_escenas";
        },
        goGenerate(tpl) {
            if (this.soloCampanas360(tpl)) return;
            if (tpl?.id) window.location.href = `/marketing/video-generator?template_id=${tpl.id}`;
        },
        categoryLabel(cat) {
            return this.categoryOptions.find((o) => o.value === cat)?.label ?? cat ?? "—";
        },
        ratioLabel(ratio) {
            return this.ratioOptions.find((o) => o.value === ratio)?.label ?? ratio ?? "";
        },
    },
};
</script>

<style scoped>
.mkt-plantillas { min-width: 0; }
.mkt-plantillas__contenido { padding: 4px 16px 16px; min-width: 0; }
.mkt-plantillas__rejilla {
    display: grid;
    gap: 16px;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
}
.mkt-plantillas__tarjeta {
    display: flex;
    flex-direction: column;
    background: var(--mkt-fondo-tarjeta);
    border: 1px solid var(--mkt-borde);
    border-radius: 4px;
    overflow: hidden;
    min-width: 0;
}
.mkt-plantillas__tarjeta--clic { cursor: pointer; transition: border-color 0.15s ease; }
.mkt-plantillas__tarjeta--clic:hover { border-color: var(--mkt-carpeta); }
.mkt-plantillas__tarjeta--clic:focus-visible { outline: 2px solid var(--mkt-carpeta); outline-offset: 2px; }
.mkt-plantillas__miniatura { height: 160px; background: var(--mkt-kpi-fondo); display: flex; align-items: center; justify-content: center; }
.mkt-plantillas__miniatura img { width: 100%; height: 100%; object-fit: cover; }
.mkt-plantillas__sin-miniatura { display: flex; flex-direction: column; align-items: center; gap: 4px; font-size: 12px; color: var(--mkt-texto-suave); }
.mkt-plantillas__cuerpo { display: flex; flex-direction: column; gap: 6px; padding: 12px 16px; flex: 1 1 auto; }
.mkt-plantillas__nombre { margin: 0; font-size: 15px; font-weight: 600; line-height: 22px; color: var(--mkt-texto); overflow-wrap: anywhere; }
.mkt-plantillas__descripcion {
    margin: 0;
    font-size: 13px;
    line-height: 18px;
    color: var(--mkt-texto-suave);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 36px;
}
.mkt-plantillas__etiquetas { display: flex; flex-wrap: wrap; gap: 6px; }
.mkt-plantillas__pie { display: flex; justify-content: flex-end; padding: 0 8px 8px; }
.mkt-plantillas__texto { margin: 0; font-size: 14px; line-height: 1.5; color: var(--mkt-texto); }
.mkt-plantillas__texto-suave { margin: 0; font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-plantillas__subtitulo { font-size: 13px; font-weight: 600; color: var(--mkt-texto-suave); margin-bottom: 6px; }
.mkt-plantillas__datos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 16px; margin: 0; }
.mkt-plantillas__datos dt { font-size: 12px; font-weight: 500; color: var(--mkt-texto-suave); }
.mkt-plantillas__datos dd { margin: 0; font-size: 14px; color: var(--mkt-texto); }
@media (max-width: 599px) {
    .mkt-plantillas__contenido { padding: 4px 12px 12px; }
    .mkt-plantillas__rejilla { grid-template-columns: minmax(0, 1fr); }
}
@media (prefers-reduced-motion: reduce) {
    .mkt-plantillas__tarjeta--clic { transition: none; }
}
</style>
