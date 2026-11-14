<template>
    <mkt-raiz :color-marca="contexto?.empresa_activa?.color_primario">
        <div class="mkt-brand">
            <mkt-encabezado-pantalla
                titulo="Brand Kit"
                subtitulo="Identidad visual que usan los videos y piezas de Marketing"
                con-selector-empresa
                @contexto="(c) => (contexto = c)"
            >
                <template #acciones>
                    <mkt-boton
                        v-if="editable && estado === 'listo'"
                        tipo="guardar"
                        etiqueta="Guardar cambios"
                        :cargando="saving"
                        @click="save"
                    />
                </template>

                <!-- Sin permiso -->
                <mkt-estado-vacio
                    v-if="estado === 'sin_permiso'"
                    icono="lock"
                    titulo="No puedes ver el Brand Kit"
                    texto="No tienes acceso a esta sección. Pídelo a Irving."
                />

                <!-- Error -->
                <mkt-estado-vacio v-else-if="estado === 'error'" icono="cloud_off" titulo="No se pudo cargar el Brand Kit" :texto="mensajeError" />
            </mkt-encabezado-pantalla>

            <template v-if="estado !== 'sin_permiso' && estado !== 'error'">

                <div class="mkt-brand__rejilla">
                    <!-- Logo -->
                    <mkt-tarjeta-panel titulo="Logo" subtitulo="PNG, JPG, SVG o WebP, máximo 5 MB" tono="lila" icono-fa="fa-image" :cargando="estado === 'cargando'">
                        <div class="mkt-brand__logo">
                            <div
                                class="mkt-brand__logo-caja"
                                :class="{ 'mkt-brand__logo-caja--activa': editable }"
                                @dragover.prevent
                                @drop.prevent="handleLogoDrop"
                            >
                                <img v-if="logoUrl" :src="logoUrl" alt="Logo de la empresa" class="mkt-brand__logo-img" />
                                <div v-else class="mkt-brand__logo-vacio">
                                    <q-icon name="image" size="36px" aria-hidden="true" />
                                    <span>Sin logo</span>
                                    <span v-if="editable">Arrastra un archivo aquí</span>
                                </div>
                            </div>
                            <div v-if="editable" class="mkt-brand__logo-acciones">
                                <q-file
                                    ref="logoInput"
                                    v-model="logoFile"
                                    accept=".png,.jpg,.jpeg,.svg,.webp"
                                    :max-file-size="5 * 1024 * 1024"
                                    class="mkt-brand__oculto"
                                    @update:model-value="onLogoFileSelected"
                                />
                                <mkt-boton
                                    tipo="secundario"
                                    etiqueta="Subir logo"
                                    :cargando="uploadingLogo"
                                    @click="$refs.logoInput.pickFiles()"
                                />
                                <mkt-boton
                                    v-if="logoUrl"
                                    tipo="peligro-secundario"
                                    etiqueta="Eliminar logo"
                                    :deshabilitado="deletingLogo"
                                    @click="confirmDeleteLogo = true"
                                />
                            </div>
                        </div>
                    </mkt-tarjeta-panel>

                    <!-- Colores -->
                    <mkt-tarjeta-panel titulo="Colores de marca" tono="rosa" icono-fa="fa-palette" :cargando="estado === 'cargando'">
                        <template v-if="editable" #acciones>
                            <q-btn-dropdown outline dense no-caps color="grey-8" label="Paletas sugeridas" class="mkt-brand__paletas">
                                <q-list>
                                    <q-item v-for="p in palettes" :key="p.name" v-close-popup clickable class="mkt-brand__opcion" @click="applyPalette(p)">
                                        <q-item-section avatar class="mkt-brand__opcion-avatar">
                                            <span class="mkt-brand__muestras">
                                                <span :style="{ background: p.primary }"></span>
                                                <span :style="{ background: p.secondary }"></span>
                                            </span>
                                        </q-item-section>
                                        <q-item-section>{{ p.name }}</q-item-section>
                                    </q-item>
                                </q-list>
                            </q-btn-dropdown>
                        </template>
                        <div class="mkt-brand__colores">
                            <div v-for="c in camposColor" :key="c.key" class="mkt-brand__color">
                                <label class="mkt-brand__etiqueta" :for="`mkt-color-${c.key}`">{{ c.label }}</label>
                                <div class="mkt-brand__color-campo">
                                    <input
                                        :id="`mkt-color-${c.key}`"
                                        v-model="form.colors[c.key]"
                                        type="color"
                                        class="mkt-brand__picker"
                                        :disabled="!editable"
                                        :aria-label="`${c.label} (selector)`"
                                    />
                                    <q-input
                                        v-model="form.colors[c.key]"
                                        dense
                                        outlined
                                        :readonly="!editable"
                                        :rules="[hexRule]"
                                        :error="!!errores[`colors.${c.key}`]"
                                        :error-message="errores[`colors.${c.key}`]"
                                        class="mkt-brand__hex"
                                        :aria-label="c.label"
                                    />
                                </div>
                            </div>
                        </div>
                        <div class="mkt-brand__vista" aria-label="Vista previa de colores">
                            <span class="mkt-brand__pastilla" :style="{ background: form.colors.primary, color: form.colors.text }">Internet de calidad</span>
                            <span class="mkt-brand__pastilla" :style="{ background: form.colors.secondary, color: form.colors.text }">Oferta especial</span>
                            <span class="mkt-brand__pastilla" :style="{ background: '#1A1A2E', color: form.colors.text }">Soporte 24/7</span>
                        </div>
                    </mkt-tarjeta-panel>

                <!-- Empresa -->
                <mkt-tarjeta-panel titulo="Datos de la empresa" subtitulo="Aparecen en los cierres de los videos" tono="azul" icono-fa="fa-building" :cargando="estado === 'cargando'">
                    <div class="mkt-brand__campos">
                        <q-input
                            v-model="form.company.name"
                            label="Nombre de la empresa"
                            outlined
                            dense
                            maxlength="80"
                            :readonly="!editable"
                            :error="!!errores['company.name']"
                            :error-message="errores['company.name']"
                        />
                        <q-input
                            v-model="form.company.website"
                            label="Sitio web"
                            outlined
                            dense
                            maxlength="200"
                            placeholder="www.meganet.com.mx"
                            :readonly="!editable"
                            :error="!!errores['company.website']"
                            :error-message="errores['company.website']"
                        />
                        <q-input
                            v-model="form.company.phone"
                            label="Teléfono principal"
                            outlined
                            dense
                            maxlength="30"
                            :readonly="!editable"
                            :error="!!errores['company.phone']"
                            :error-message="errores['company.phone']"
                        />
                        <q-input
                            v-model="form.company.whatsapp"
                            label="WhatsApp"
                            outlined
                            dense
                            maxlength="30"
                            :readonly="!editable"
                            :error="!!errores['company.whatsapp']"
                            :error-message="errores['company.whatsapp']"
                        />
                    </div>
                </mkt-tarjeta-panel>

                <!-- Integraciones [F1-6]: sin captura de llaves; se administran en el Hub -->
                <mkt-tarjeta-panel
                    titulo="Servicios externos"
                    subtitulo="Las claves de acceso se administran en Integraciones, no aquí"
                    tono="verde"
                    icono-fa="fa-plug"
                    :cargando="estado === 'cargando'"
                >
                    <template #acciones>
                        <mkt-boton tipo="secundario" chico etiqueta="Configurar en Integraciones" href="/integraciones" />
                    </template>
                    <ul class="mkt-brand__servicios">
                        <li v-for="s in servicios" :key="s.clave" class="mkt-brand__servicio">
                            <div class="mkt-brand__servicio-texto">
                                <span class="mkt-brand__servicio-nombre">{{ s.nombre }}</span>
                                <span class="mkt-brand__servicio-uso">{{ s.uso }}</span>
                            </div>
                            <mkt-insignia-estado
                                v-if="s.configurada !== null"
                                :texto="s.configurada ? 'Configurada' : 'Falta'"
                                :tono="s.configurada ? 'exito' : 'aviso'"
                            />
                            <span v-else class="mkt-brand__servicio-uso">Se administra en Integraciones</span>
                        </li>
                    </ul>
                </mkt-tarjeta-panel>
                </div>
            </template>

            <!-- Confirmar eliminar logo -->
            <mkt-dialogo v-model="confirmDeleteLogo" titulo="Eliminar logo" :guardando="deletingLogo" :ancho="420">
                <p class="mkt-brand__texto">¿Seguro que deseas eliminar el logo actual? Los videos nuevos saldrán sin logo hasta que subas otro.</p>
                <template #acciones>
                    <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="deletingLogo" @click="confirmDeleteLogo = false" />
                    <mkt-boton tipo="eliminar" etiqueta="Eliminar" :cargando="deletingLogo" @click="doDeleteLogo" />
                </template>
            </mkt-dialogo>
        </div>
    </mkt-raiz>
</template>

<script>
import axios from "axios";
import { message } from "../../../helpers/toastMsg";
import { mensajeDeError, erroresPorCampo } from "./kit";

/**
 * Brand Kit (/marketing/brand-kit) — Fase 1 (rediseño con el kit).
 * [F1-6] Ya no captura llaves de OpenAI/Pexels: solo muestra si están configuradas y manda a /integraciones.
 * Es configuración compartida de Meganet: editable solo con capacidades.editar_config_compartida.
 */
export default {
    name: "MarketingBrandKitView",
    data() {
        return {
            contexto: null,
            estado: "cargando", // cargando | listo | error | sin_permiso
            mensajeError: "",
            errores: {},
            saving: false,
            uploadingLogo: false,
            deletingLogo: false,
            confirmDeleteLogo: false,
            logoUrl: null,
            logoFile: null,
            kit: { integrations: {} },
            form: {
                colors: { primary: "#1976D2", secondary: "#FFC107", text: "#FFFFFF" },
                company: { name: "", phone: "", whatsapp: "", website: "" },
            },
            camposColor: [
                { key: "primary", label: "Color primario" },
                { key: "secondary", label: "Color secundario" },
                { key: "text", label: "Color de texto" },
            ],
            palettes: [
                { name: "Meganet (azul + naranja)", primary: "#0066CC", secondary: "#FF6600", text: "#FFFFFF" },
                { name: "Corporativo (gris + naranja)", primary: "#455A64", secondary: "#FF7043", text: "#FFFFFF" },
                { name: "Energético (rojo + amarillo)", primary: "#C62828", secondary: "#FFD600", text: "#FFFFFF" },
                { name: "Limpio (verde + blanco)", primary: "#2E7D32", secondary: "#FFFFFF", text: "#FFFFFF" },
            ],
        };
    },
    computed: {
        editable() {
            return this.contexto?.capacidades?.editar_config_compartida !== false;
        },
        servicios() {
            const i = this.kit?.integrations || {};
            const valor = (v) => (typeof v === "boolean" ? v : null);
            return [
                { clave: "openai", nombre: "OpenAI (voz en off)", uso: "Narración de los videos", configurada: valor(i.openai_configured) },
                { clave: "pexels", nombre: "Pexels (clips de fondo)", uso: "Clips de video de fondo para las escenas", configurada: valor(i.pexels_configured) },
            ];
        },
    },
    mounted() {
        this.load();
    },
    methods: {
        async load() {
            this.estado = "cargando";
            try {
                const { data } = await axios.get("/api/marketing/brand-kit");
                this.kit = data || { integrations: {} };
                this.form.colors = { ...this.form.colors, ...(data?.colors || {}) };
                this.form.company = { ...this.form.company, ...(data?.company || {}) };
                this.logoUrl = data?.logo?.url ?? null;
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
        async save() {
            if (this.saving) return;
            this.saving = true;
            this.errores = {};
            try {
                await axios.put("/api/marketing/brand-kit", { colors: this.form.colors, company: this.form.company });
                message("Brand Kit guardado.");
                await this.load();
            } catch (e) {
                this.errores = erroresPorCampo(e);
                message(mensajeDeError(e, "No se pudo guardar el Brand Kit."), "error");
            } finally {
                this.saving = false;
            }
        },
        async onLogoFileSelected(file) {
            if (!file || this.uploadingLogo) return;
            this.uploadingLogo = true;
            try {
                const fd = new FormData();
                fd.append("logo", file);
                const { data } = await axios.post("/api/marketing/brand-kit/logo", fd, {
                    headers: { "Content-Type": "multipart/form-data" },
                });
                this.logoUrl = data?.url ? `${data.url}${data.url.includes("?") ? "&" : "?"}r=${Date.now()}` : null;
                message("Logo actualizado.");
            } catch (e) {
                message(mensajeDeError(e, "No se pudo subir el logo."), "error");
            } finally {
                this.uploadingLogo = false;
                this.logoFile = null;
            }
        },
        handleLogoDrop(e) {
            if (!this.editable) return;
            const file = e.dataTransfer?.files?.[0];
            if (file) this.onLogoFileSelected(file);
        },
        async doDeleteLogo() {
            if (this.deletingLogo) return;
            this.deletingLogo = true;
            try {
                await axios.delete("/api/marketing/brand-kit/logo");
                this.logoUrl = null;
                this.confirmDeleteLogo = false;
                message("Logo eliminado.");
            } catch (e) {
                message(mensajeDeError(e, "No se pudo eliminar el logo."), "error");
            } finally {
                this.deletingLogo = false;
            }
        },
        applyPalette(p) {
            this.form.colors = { primary: p.primary, secondary: p.secondary, text: p.text };
            message(`Paleta «${p.name}» aplicada. Recuerda guardar.`, "info");
        },
        hexRule(v) {
            return !v || /^#[0-9a-fA-F]{6}$/.test(v) || "Formato inválido (#RRGGBB)";
        },
    },
};
</script>

<style scoped>
.mkt-brand {
    display: flex;
    flex-direction: column;
    gap: var(--mkt-esp-2);
    min-width: 0;
}
.mkt-brand > :first-child { margin-bottom: 0; }
/* Bloques de la ficha de Cliente: dos columnas en escritorio, una en celular. */
.mkt-brand__rejilla {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
}
.mkt-brand__logo { display: flex; flex-wrap: wrap; align-items: center; gap: var(--mkt-esp-2); }
.mkt-brand__logo-caja {
    width: 168px;
    height: 168px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px dashed var(--mkt-borde);
    border-radius: var(--mkt-radio);
    background: var(--mkt-fondo-suave);
    flex: 0 0 auto;
}
.mkt-brand__logo-caja--activa { cursor: copy; }
.mkt-brand__logo-img { max-width: 148px; max-height: 148px; object-fit: contain; }
.mkt-brand__logo-vacio {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: var(--mkt-texto-suave);
    text-align: center;
    padding: var(--mkt-esp-1);
}
.mkt-brand__logo-acciones { display: flex; flex-direction: column; gap: var(--mkt-esp-1); }
.mkt-brand__oculto { display: none; }
.mkt-brand__colores {
    display: grid;
    gap: var(--mkt-esp-2);
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
}
.mkt-brand__etiqueta { display: block; font-size: 13px; font-weight: 500; color: var(--mkt-texto-suave); margin-bottom: 4px; }
.mkt-brand__color-campo { display: flex; align-items: flex-start; gap: var(--mkt-esp-1); }
.mkt-brand__picker {
    width: 40px;
    height: 40px;
    flex: 0 0 40px;
    padding: 2px;
    border: 1px solid var(--mkt-borde);
    border-radius: 6px;
    background: var(--mkt-fondo-tarjeta);
    cursor: pointer;
}
.mkt-brand__picker:disabled { cursor: default; }
.mkt-brand__hex { flex: 1 1 auto; min-width: 0; }
.mkt-brand__vista { display: flex; flex-wrap: wrap; gap: var(--mkt-esp-1); margin-top: var(--mkt-esp-1); }
.mkt-brand__pastilla { padding: 4px 12px; border-radius: 999px; font-size: 13px; font-weight: 500; }
.mkt-brand__campos {
    display: grid;
    gap: var(--mkt-esp-1) var(--mkt-esp-2);
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.mkt-brand__servicios { list-style: none; margin: 0; padding: 0; }
.mkt-brand__servicio {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--mkt-esp-2);
    padding: 12px 0;
}
.mkt-brand__servicio + .mkt-brand__servicio { border-top: 1px solid var(--mkt-borde); }
.mkt-brand__servicio-texto { display: flex; flex-direction: column; min-width: 0; }
.mkt-brand__servicio-nombre { font-weight: 600; font-size: 14px; color: var(--mkt-texto); }
.mkt-brand__servicio-uso { font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-brand__texto { margin: 0; font-size: 14px; line-height: 1.5; color: var(--mkt-texto); }
/* Menú de paletas: se pinta fuera de la raíz → arreglo de Bootstrap `.row > *` aquí. */
.mkt-brand__opcion { --bs-gutter-x: 0; --bs-gutter-y: 0; }
.mkt-brand__opcion > * { width: auto; max-width: none; margin-top: 0; }
.mkt-brand__opcion > .mkt-brand__opcion-avatar { min-width: 36px; padding-right: 8px; }
.mkt-brand__muestras { display: inline-flex; gap: 4px; }
.mkt-brand__muestras > span { width: 14px; height: 14px; border-radius: 3px; border: 1px solid rgba(0, 0, 0, 0.1); }

@media (max-width: 1023px) {
    .mkt-brand__rejilla { grid-template-columns: minmax(0, 1fr); }
}
@media (max-width: 599px) {
    .mkt-brand__rejilla { gap: 12px; }
    .mkt-brand__colores,
    .mkt-brand__campos { grid-template-columns: minmax(0, 1fr); }
    .mkt-brand__servicio { flex-wrap: wrap; }
}
</style>
