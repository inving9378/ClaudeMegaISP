<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-generador">
            <mkt-encabezado-pantalla
                titulo="Generar"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template #acciones>
                    <mkt-boton tipo="secundario" etiqueta="Ver fila de producción" href="/marketing/video-queue" />
                    <!-- Acción principal (UNA, arriba a la derecha). -->
                    <mkt-boton
                        v-if="puedeGenerar && estado !== 'sin_permiso' && estado !== 'error'"
                        tipo="guardar"
                        etiqueta="Generar video"
                        :cargando="generating"
                        :deshabilitado="!selectedTemplateId || generating"
                        @click="generate"
                    />
                </template>
            </mkt-encabezado-pantalla>

            <mkt-tarjeta-panel v-if="estado === 'sin_permiso'">
                <mkt-estado-vacio
                    icono="lock"
                    titulo="No puedes generar videos"
                    texto="No tienes acceso a esta sección. Pídelo a Irving."
                />
            </mkt-tarjeta-panel>

            <mkt-tarjeta-panel v-else-if="estado === 'error'">
                <mkt-estado-vacio icono="cloud_off" titulo="No se pudieron cargar las plantillas" :texto="mensajeError" />
            </mkt-tarjeta-panel>

            <div v-else class="mkt-generador__rejilla">
                <div class="mkt-generador__principal">
                    <!-- 1. Plantilla -->
                    <mkt-tarjeta-panel titulo="1. Plantilla" tono="lila" icono-fa="fa-film" :cargando="estado === 'cargando'">
                        <mkt-estado-vacio
                            v-if="!templates.length"
                            icono="video_library"
                            :titulo="soloGuionEscenas ? 'Las plantillas de video se usan desde Campañas 360' : 'No hay plantillas disponibles'"
                            :texto="soloGuionEscenas
                                ? 'Las plantillas que hay son de «Guion por escenas»: el guion, la voz y la música salen de una campaña. Abre una campaña en Campañas 360 y produce el video desde su pestaña Reels o Video.'
                                : 'El catálogo de plantillas de Meganet está vacío. Pide a un administrador que cargue plantillas.'" />
                        <template v-else>
                            <q-select
                                v-model="selectedTemplateId"
                                :options="templateOptions"
                                emit-value
                                map-options
                                outlined
                                dense
                                options-dense
                                label="Selecciona una plantilla"
                                @update:model-value="onTemplateChange"
                            />
                            <div v-if="selectedTemplate" class="mkt-generador__datos">
                                <span v-if="selectedTemplate.aspect_ratios?.[0]"><q-icon name="aspect_ratio" size="16px" aria-hidden="true" /> {{ selectedTemplate.aspect_ratios[0] }}</span>
                                <span v-if="selectedTemplate.duration_seconds"><q-icon name="schedule" size="16px" aria-hidden="true" /> ~{{ selectedTemplate.duration_seconds }} s</span>
                                <span v-if="selectedTemplate.estimated_render_seconds"><q-icon name="timer" size="16px" aria-hidden="true" /> Producción ~{{ selectedTemplate.estimated_render_seconds }} s</span>
                                <span v-if="selectedTemplate.requires_voiceover"><q-icon name="mic" size="16px" aria-hidden="true" /> Incluye narración</span>
                            </div>
                        </template>
                    </mkt-tarjeta-panel>

                    <!-- 2. Contenido -->
                    <mkt-tarjeta-panel v-if="selectedTemplate" titulo="2. Contenido" tono="azul" icono-fa="fa-align-left" :cargando="cargandoVariables">
                        <div v-if="errorVariables" class="mkt-generador__aviso" role="alert">
                            <q-icon name="error_outline" size="18px" aria-hidden="true" />
                            <span>{{ errorVariables }}</span>
                            <mkt-boton tipo="secundario" chico etiqueta="Reintentar" @click="onTemplateChange(selectedTemplateId)" />
                        </div>
                        <p v-else-if="!templateVariables.length" class="mkt-generador__suave">Esta plantilla no pide datos.</p>
                        <div v-else class="mkt-generador__campos">
                            <q-input
                                v-for="varDef in templateVariables"
                                :key="varDef.key"
                                v-model="variables[varDef.key]"
                                :label="varDef.label + (varDef.required ? ' *' : '')"
                                :type="varDef.key === 'voiceover_text' ? 'textarea' : 'text'"
                                :autogrow="varDef.key === 'voiceover_text'"
                                :class="{ 'mkt-generador__campo-ancho': varDef.key === 'voiceover_text' }"
                                outlined
                                dense
                                :error="faltantes.includes(varDef.key)"
                                error-message="Este dato es obligatorio"
                                :hint="varDef.example ? 'Ej.: ' + varDef.example : varDef.default ?? ''"
                            />
                        </div>
                    </mkt-tarjeta-panel>

                    <!-- 3. Opciones -->
                    <mkt-tarjeta-panel v-if="selectedTemplate" titulo="3. Opciones" tono="verde" icono-fa="fa-sliders-h">
                        <div class="mkt-generador__campos">
                            <q-select
                                v-model="options.tts_driver"
                                :options="ttsOptions"
                                emit-value
                                map-options
                                outlined
                                dense
                                options-dense
                                label="Motor de voz"
                            />
                        </div>
                        <q-expansion-item
                            class="mkt-generador__extra"
                            icon="tune"
                            label="Personalizar para este video (opcional)"
                            caption="Logo o colores distintos a los del Brand Kit"
                            dense
                        >
                            <div class="mkt-generador__extra-cuerpo">
                                <q-checkbox v-model="useLogoOverride" dense label="Usar otro logo en este video" />
                                <div v-if="useLogoOverride" class="mkt-generador__sangria">
                                    <q-file
                                        v-model="overrides.logoFile"
                                        label="Logo para este video"
                                        accept=".png,.jpg,.jpeg,.svg,.webp"
                                        :max-file-size="5 * 1024 * 1024"
                                        outlined
                                        dense
                                        clearable
                                    >
                                        <template #prepend><q-icon name="image" /></template>
                                    </q-file>
                                    <img v-if="logoPreviewUrl" :src="logoPreviewUrl" alt="Vista previa del logo" class="mkt-generador__logo" />
                                </div>

                                <q-checkbox v-model="useColorOverride" dense label="Usar otros colores en este video" />
                                <div v-if="useColorOverride" class="mkt-generador__sangria mkt-generador__colores">
                                    <div v-for="c in camposColor" :key="c.key" class="mkt-generador__color">
                                        <label class="mkt-generador__etiqueta" :for="`mkt-gen-${c.key}`">{{ c.label }}</label>
                                        <div class="mkt-generador__color-campo">
                                            <input :id="`mkt-gen-${c.key}`" v-model="overrides[c.key]" type="color" class="mkt-generador__picker" />
                                            <q-input v-model="overrides[c.key]" dense outlined :aria-label="c.label" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </q-expansion-item>
                    </mkt-tarjeta-panel>
                </div>

                <!-- Lateral: acción y resultado -->
                <aside class="mkt-generador__lateral">
                    <mkt-tarjeta-panel titulo="Generar" tono="oliva" icono-fa="fa-play-circle">
                        <p class="mkt-generador__suave">
                            El video entra a la fila de producción. Puedes seguir trabajando y revisar su avance ahí.
                        </p>
                        <p v-if="!puedeGenerar" class="mkt-generador__suave mkt-generador__sin-permiso">
                            <q-icon name="lock" size="16px" aria-hidden="true" /> No tienes el permiso «Generar contenido de video».
                        </p>
                    </mkt-tarjeta-panel>

                    <mkt-tarjeta-panel v-if="renderResult" titulo="Video en la fila" tono="crema" icono-fa="fa-check-circle">
                        <div class="mkt-generador__exito" role="status">
                            <q-icon name="check_circle" size="28px" aria-hidden="true" />
                            <div>
                                <div class="mkt-generador__exito-titulo">Video en la fila de producción</div>
                                <div class="mkt-generador__suave">Folio {{ renderResult.generated_content_id }}</div>
                            </div>
                        </div>
                        <template #pie>
                            <a class="mkt-generador__enlace" :href="'/marketing/video-queue?id=' + renderResult.generated_content_id">Ver avance en la fila de producción</a>
                        </template>
                    </mkt-tarjeta-panel>
                </aside>
            </div>
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../helpers/toastMsg";
import { currentSearch } from "../../../hook/comunValues";
import { mensajeDeError, puedePermiso } from "./kit";

/** Mensaje del backend: `mensaje` del contrato o el `error` en texto que ya devolvía el render. */
const mensajeRender = (e, porDefecto) => {
    const d = e?.response?.data;
    if (!d?.mensaje && typeof d?.error === "string" && d.error.includes(" ")) return d.error;
    return mensajeDeError(e, porDefecto);
};

/** Generar (/marketing/video-generator) — Fase 1, rediseño con el kit, mismo funcionamiento; §2.13 bloques como la ficha de Cliente. */
export default {
    name: "MarketingVideoGeneratorView",
    data() {
        return {
            soloGuionEscenas: false,
            contexto: null,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            generating: false,
            templates: [],
            selectedTemplateId: null,
            selectedTemplate: null,
            templateVariables: [],
            cargandoVariables: false,
            errorVariables: "",
            variables: {},
            faltantes: [],
            options: { tts_driver: "openai" },
            ttsOptions: [
                { label: "OpenAI (nova)", value: "openai" },
                { label: "Piper (local)", value: "piper" },
            ],
            renderResult: null,
            useLogoOverride: false,
            useColorOverride: false,
            overrides: { logoFile: null, primary_color: "#0066CC", secondary_color: "#FF6600" },
            camposColor: [
                { key: "primary_color", label: "Primario" },
                { key: "secondary_color", label: "Secundario" },
            ],
            logoPreviewUrl: null,
        };
    },
    computed: {
        templateOptions() {
            return this.templates.map((t) => ({ label: t.name, value: t.id }));
        },
        puedeGenerar() {
            return puedePermiso(this.$store, "generate-video-content");
        },
    },
    watch: {
        "overrides.logoFile"(file) {
            if (this.logoPreviewUrl) URL.revokeObjectURL(this.logoPreviewUrl);
            this.logoPreviewUrl = file ? URL.createObjectURL(file) : null;
        },
    },
    mounted() {
        this.iniciar();
    },
    beforeUnmount() {
        if (this.logoPreviewUrl) URL.revokeObjectURL(this.logoPreviewUrl);
    },
    methods: {
        async iniciar() {
            // La navegación SPA monta Vue antes del pushState: el ?template_id se lee con currentSearch() al montar.
            const id = parseInt(new URLSearchParams(currentSearch()).get("template_id"), 10);
            await this.loadTemplates();
            if (this.estado !== "listo") return;
            if (id > 0 && this.templates.some((t) => t.id === id)) {
                this.selectedTemplateId = id;
                this.onTemplateChange(id);
            }
        },
        async loadTemplates() {
            this.estado = "cargando";
            try {
                const { data } = await axios.get("/api/marketing/video-templates");
                // Fase 2: las plantillas «Guion por escenas» solo se usan desde Campañas 360 (reciben escenas, no variables).
                const todas = data?.data ?? [];
                this.templates = todas.filter((t) => t?.category !== "guion_escenas");
                // Solo para el aviso: hay plantillas, pero todas son de «Guion por escenas» (se usan desde Campañas 360).
                this.soloGuionEscenas = todas.length > 0 && this.templates.length === 0;
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
        async onTemplateChange(id) {
            const tpl = this.templates.find((t) => t.id === id);
            this.selectedTemplate = tpl ?? null;
            this.variables = {};
            this.templateVariables = [];
            this.faltantes = [];
            this.errorVariables = "";
            this.renderResult = null;
            if (!tpl) return;
            this.cargandoVariables = true;
            try {
                const { data } = await axios.get(`/api/marketing/video-templates/${id}/variables`);
                this.templateVariables = data?.variables ?? [];
                this.variables = { ...(data?.default_variables ?? {}) };
            } catch (e) {
                this.errorVariables = mensajeDeError(e, "No se pudieron cargar los datos que pide la plantilla.");
            } finally {
                this.cargandoVariables = false;
            }
        },
        async generate() {
            if (!this.selectedTemplateId || this.generating) return;
            this.faltantes = this.templateVariables.filter((v) => v.required && !this.variables[v.key]).map((v) => v.key);
            if (this.faltantes.length) {
                message("Faltan datos obligatorios del contenido.", "warning");
                return;
            }
            this.generating = true;
            this.renderResult = null;
            try {
                let res;
                if (this.useLogoOverride && this.overrides.logoFile) {
                    const fd = new FormData();
                    fd.append("template_id", this.selectedTemplateId);
                    fd.append("variables", JSON.stringify(this.variables));
                    fd.append("options", JSON.stringify(this.options));
                    fd.append("overrides[logo_file]", this.overrides.logoFile);
                    if (this.useColorOverride) {
                        fd.append("overrides[primary_color]", this.overrides.primary_color);
                        fd.append("overrides[secondary_color]", this.overrides.secondary_color);
                    }
                    res = await axios.post("/api/marketing/generated-content/render", fd, {
                        headers: { "Content-Type": "multipart/form-data" },
                    });
                } else {
                    const payload = { template_id: this.selectedTemplateId, variables: this.variables, options: this.options };
                    if (this.useColorOverride) {
                        payload.overrides = {
                            primary_color: this.overrides.primary_color,
                            secondary_color: this.overrides.secondary_color,
                        };
                    }
                    res = await axios.post("/api/marketing/generated-content/render", payload);
                }
                this.renderResult = res.data;
                message("Video en la fila de producción. Puedes ver su avance ahí.");
            } catch (e) {
                message(mensajeRender(e, "No se pudo mandar el video a la fila de producción."), "error");
            } finally {
                this.generating = false;
            }
        },
    },
};
</script>

<style scoped>
.mkt-generador { min-width: 0; }
.mkt-generador__rejilla {
    display: grid;
    gap: 14px;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    align-items: start;
}
.mkt-generador__principal,
.mkt-generador__lateral { display: flex; flex-direction: column; gap: 14px; min-width: 0; }
.mkt-generador__datos {
    display: flex;
    flex-wrap: wrap;
    gap: var(--mkt-esp-1) var(--mkt-esp-2);
    margin-top: var(--mkt-esp-1);
    font-size: 13px;
    color: var(--mkt-texto-suave);
}
.mkt-generador__datos > span { display: inline-flex; align-items: center; gap: 4px; }
.mkt-generador__campos {
    display: grid;
    gap: var(--mkt-esp-1) var(--mkt-esp-2);
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.mkt-generador__campo-ancho { grid-column: 1 / -1; }
.mkt-generador__suave { margin: 0; font-size: 13px; line-height: 1.5; color: var(--mkt-texto-suave); }
.mkt-generador__sin-permiso { margin-top: var(--mkt-esp-2); display: flex; align-items: center; gap: 6px; }
.mkt-generador__aviso {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--mkt-esp-1);
    font-size: 14px;
    color: var(--mkt-peligro);
}
.mkt-generador__extra { margin-top: var(--mkt-esp-2); border: 1px solid var(--mkt-borde); border-radius: var(--mkt-radio); }
.mkt-generador__extra-cuerpo { display: flex; flex-direction: column; gap: var(--mkt-esp-1); padding: var(--mkt-esp-1) var(--mkt-esp-2) var(--mkt-esp-2); }
.mkt-generador__sangria { padding-left: var(--mkt-esp-4); display: flex; flex-direction: column; gap: var(--mkt-esp-1); }
.mkt-generador__logo { max-height: 60px; max-width: 120px; object-fit: contain; }
.mkt-generador__colores { flex-direction: row; flex-wrap: wrap; gap: var(--mkt-esp-2); }
.mkt-generador__etiqueta { display: block; font-size: 12px; color: var(--mkt-texto-suave); margin-bottom: 4px; }
.mkt-generador__color-campo { display: flex; align-items: center; gap: var(--mkt-esp-1); max-width: 180px; }
.mkt-generador__picker {
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    padding: 2px;
    border: 1px solid var(--mkt-borde);
    border-radius: 4px;
    background: var(--mkt-fondo-tarjeta);
    cursor: pointer;
}
.mkt-generador__exito { display: flex; align-items: center; gap: var(--mkt-esp-1); color: var(--mkt-exito); }
.mkt-generador__exito-titulo { font-weight: 600; color: var(--mkt-texto); }
.mkt-generador__enlace { font-weight: 500; text-decoration: none; }
.mkt-generador__enlace:hover { text-decoration: underline; }
@media (max-width: 1023px) {
    .mkt-generador__rejilla { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 599px) {
    .mkt-generador__rejilla,
    .mkt-generador__principal,
    .mkt-generador__lateral { gap: var(--mkt-esp-2); }
    .mkt-generador__campos { grid-template-columns: minmax(0, 1fr); }
    .mkt-generador__sangria { padding-left: var(--mkt-esp-2); }
}
</style>
