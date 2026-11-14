<template>
    <mkt-tarjeta-panel :titulo="`Variante ${pieza.variante || ''}`" :subtitulo="subtitulo" tono="crema" icono-fa="fa-ad">
        <template #acciones>
            <mkt-insignia-estado :texto="estado.label" :tono="estado.tono" />
        </template>

        <div v-if="pieza.estado === 'generando' || pieza.estado === 'pendiente'" class="mkt-anuncio__esqueleto" aria-busy="true">
            <q-skeleton type="text" width="60%" height="24px" />
            <q-skeleton type="text" width="95%" />
            <q-skeleton type="text" width="80%" />
            <q-skeleton type="rect" height="36px" width="40%" />
        </div>

        <mkt-estado-vacio v-else-if="pieza.estado === 'error'" icono="error_outline" titulo="No se pudo generar" :texto="pieza.error || 'Usa «Regenerar».'" />

        <template v-else>
            <div class="mkt-anuncio__vista">
                <p class="mkt-anuncio__principal">{{ c.texto_principal }}</p>
                <div class="mkt-anuncio__imagen" aria-hidden="true">
                    <q-icon :name="iconoFormato" size="28px" />
                    <span>{{ etiquetaFormato }}</span>
                </div>
                <div class="mkt-anuncio__pie">
                    <div class="mkt-anuncio__textos">
                        <span class="mkt-anuncio__titular">{{ c.titular }}</span>
                        <span class="mkt-anuncio__desc">{{ c.descripcion }}</span>
                    </div>
                    <span class="mkt-anuncio__boton">{{ etiquetaBoton(c.boton) }}</span>
                </div>
            </div>
            <dl class="mkt-anuncio__medidas">
                <div v-for="m in medidas" :key="m.campo" :class="{ 'mkt-anuncio__pasado': m.largo > m.tope }">
                    <dt>{{ m.etiqueta }}</dt>
                    <dd>{{ m.largo }} / {{ m.tope }}</dd>
                </div>
            </dl>
            <p v-if="c.idea_visual" class="mkt-anuncio__idea"><span>Idea visual:</span> {{ c.idea_visual }}</p>
        </template>

        <template v-if="pieza.estado === 'lista' || pieza.estado === 'error'" #pie>
            <div class="mkt-anuncio__acciones">
                <mkt-boton v-if="pieza.estado === 'lista'" tipo="secundario" chico icono-fa="fa-copy" etiqueta="Copiar" @click="copiar" />
                <mkt-boton v-if="pieza.estado === 'lista'" tipo="secundario" chico icono-fa="fa-edit" etiqueta="Editar" @click="abrirEditor" />
                <mkt-boton v-if="puedeGenerar" tipo="secundario" chico icono-fa="fa-redo" etiqueta="Regenerar" @click="regenerar = true" />
            </div>
        </template>

        <mkt-dialogo v-model="editor" :titulo="`Editar anuncio ${pieza.variante || ''}`" :guardando="guardando" :ancho="600">
            <div v-if="b" class="mkt-anuncio__form">
                <q-input v-model="b.titular" outlined label="Titular *" counter :maxlength="lim.titular" :error="!!err('titular')" :error-message="err('titular')" />
                <q-input v-model="b.texto_principal" outlined autogrow type="textarea" label="Texto principal *" counter :maxlength="lim.texto_principal" :error="!!err('texto_principal')" :error-message="err('texto_principal')" />
                <q-input v-model="b.descripcion" outlined label="Descripción" counter :maxlength="lim.descripcion" :error="!!err('descripcion')" :error-message="err('descripcion')" />
                <div class="mkt-anuncio__par">
                    <q-select v-model="b.boton" outlined emit-value map-options :options="botones" label="Botón" :error="!!err('boton')" :error-message="err('boton')">
                        <template #option="scope">
                            <q-item v-bind="scope.itemProps" class="mkt-anuncio__opcion"><q-item-section>{{ scope.opt.label }}</q-item-section></q-item>
                        </template>
                    </q-select>
                    <q-select v-model="b.formato" outlined emit-value map-options :options="formatos" label="Formato" :error="!!err('formato')" :error-message="err('formato')">
                        <template #option="scope">
                            <q-item v-bind="scope.itemProps" class="mkt-anuncio__opcion"><q-item-section>{{ scope.opt.label }}</q-item-section></q-item>
                        </template>
                    </q-select>
                </div>
                <q-input v-model="b.idea_visual" outlined autogrow type="textarea" label="Idea visual (para quien diseña)" counter :maxlength="lim.idea_visual" :error="!!err('idea_visual')" :error-message="err('idea_visual')" />
                <q-banner v-if="error" dense rounded class="mkt-anuncio__error" role="alert">{{ error }}</q-banner>
            </div>
            <template #acciones>
                <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="guardando" @click="editor = false" />
                <mkt-boton tipo="guardar" etiqueta="Guardar" :cargando="guardando" @click="guardar" />
            </template>
        </mkt-dialogo>

        <dialogo-regenerar-pieza v-model="regenerar" :campana-id="campanaId" :pieza="pieza" @regenerada="alRegenerar" />
    </mkt-tarjeta-panel>
</template>

<script>
import axios from "axios";
import { message } from "../../../../helpers/toastMsg";
import { MktTarjetaPanel, MktEstadoVacio, MktInsigniaEstado, MktDialogo, mensajeDeError, erroresPorCampo } from "../kit";
import { ESTADOS_PIEZA, LIMITES_ANUNCIO, BOTONES_ANUNCIO, FORMATOS_ANUNCIO, etiquetaBoton, clonar, copiarTexto } from "./catalogos360";
import DialogoRegenerarPieza from "./DialogoRegenerarPieza.vue";

const ICONOS_FORMATO = { imagen: "image", video: "smart_display", carrusel: "view_carousel" };

/** Variante de anuncio de Meta (§2.5.5) con contadores contra los topes (titular 40, texto 125, descripción 30). */
export default {
    name: "TarjetaAnuncio",
    components: { MktTarjetaPanel, MktEstadoVacio, MktInsigniaEstado, MktDialogo, DialogoRegenerarPieza },
    props: {
        campanaId: { type: Number, required: true },
        pieza: { type: Object, required: true },
        puedeGenerar: { type: Boolean, default: true },
    },
    emits: ["actualizada", "en-proceso"],
    data() {
        return {
            editor: false, regenerar: false, b: null, guardando: false, error: "", errores: {},
            lim: LIMITES_ANUNCIO, botones: BOTONES_ANUNCIO, formatos: FORMATOS_ANUNCIO,
        };
    },
    computed: {
        c() {
            return this.pieza?.contenido && typeof this.pieza.contenido === "object" ? this.pieza.contenido : {};
        },
        estado() {
            return ESTADOS_PIEZA[this.pieza.estado] || ESTADOS_PIEZA.pendiente;
        },
        subtitulo() {
            return this.pieza.estado === "lista" ? `Anuncio de Meta · ${this.etiquetaFormato}` : "Anuncio de Meta";
        },
        etiquetaFormato() {
            return FORMATOS_ANUNCIO.find((f) => f.value === this.c.formato)?.label || "Imagen";
        },
        iconoFormato() {
            return ICONOS_FORMATO[this.c.formato] || "image";
        },
        medidas() {
            return [
                { campo: "titular", etiqueta: "Titular", largo: String(this.c.titular || "").length, tope: this.lim.titular },
                { campo: "texto_principal", etiqueta: "Texto", largo: String(this.c.texto_principal || "").length, tope: this.lim.texto_principal },
                { campo: "descripcion", etiqueta: "Descripción", largo: String(this.c.descripcion || "").length, tope: this.lim.descripcion },
            ];
        },
    },
    methods: {
        etiquetaBoton,
        err(campo) {
            const k = Object.keys(this.errores).find((x) => x === `contenido.${campo}`);
            return k ? this.errores[k] : "";
        },
        async copiar() {
            const c = this.c;
            const texto = [
                `Titular: ${c.titular || ""}`,
                `Texto principal: ${c.texto_principal || ""}`,
                `Descripción: ${c.descripcion || ""}`,
                `Botón: ${etiquetaBoton(c.boton)}`,
            ].join("\n");
            const ok = await copiarTexto(texto);
            message(ok ? "Anuncio copiado." : "No se pudo copiar. Selecciónalo y cópialo a mano.", ok ? "success" : "error");
        },
        abrirEditor() {
            const c = clonar(this.c) || {};
            this.b = {
                ...c,
                titular: c.titular || "",
                texto_principal: c.texto_principal || "",
                descripcion: c.descripcion || "",
                boton: c.boton || "MAS_INFORMACION",
                formato: c.formato || "imagen",
                idea_visual: c.idea_visual || "",
            };
            this.error = "";
            this.errores = {};
            this.editor = true;
        },
        async guardar() {
            if (this.guardando || !this.b) return;
            const locales = {};
            if (!this.b.titular.trim()) locales["contenido.titular"] = "Escribe el titular.";
            if (!this.b.texto_principal.trim()) locales["contenido.texto_principal"] = "Escribe el texto principal.";
            if (Object.keys(locales).length) {
                this.errores = locales;
                return;
            }
            this.guardando = true;
            this.error = "";
            this.errores = {};
            try {
                const contenido = Object.fromEntries(Object.entries(this.b).map(([k, v]) => [k, typeof v === "string" ? v.trim() : v]));
                const { data } = await axios.put(`/api/marketing/campanas-360/${this.campanaId}/piezas/${this.pieza.id}`, { contenido });
                this.$emit("actualizada", data?.data || { ...this.pieza, contenido });
                this.editor = false;
                message("Anuncio guardado.");
            } catch (e) {
                this.errores = erroresPorCampo(e);
                this.error = mensajeDeError(e, "No se pudo guardar.");
            } finally {
                this.guardando = false;
            }
        },
        alRegenerar(pieza) {
            this.$emit("actualizada", { ...pieza, estado: pieza?.estado || "generando" });
            this.$emit("en-proceso");
            message("Regenerando la variante.");
        },
    },
};
</script>

<style scoped>
.mkt-anuncio__esqueleto { display: flex; flex-direction: column; gap: 6px; }
.mkt-anuncio__vista { border: 1px solid var(--mkt-borde); border-radius: var(--mkt-radio); overflow: hidden; background: var(--mkt-fondo-tarjeta); }
.mkt-anuncio__principal { margin: 0; padding: 12px; font-size: 14px; line-height: 20px; overflow-wrap: anywhere; white-space: pre-line; }
.mkt-anuncio__imagen {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
    height: 120px; color: var(--mkt-texto-suave); font-size: 12px; background: var(--mkt-fondo-suave);
    border-top: 1px solid var(--mkt-borde); border-bottom: 1px solid var(--mkt-borde);
}
.mkt-anuncio__pie { display: flex; align-items: center; justify-content: space-between; gap: var(--mkt-esp-1); padding: 10px 12px; }
.mkt-anuncio__textos { display: flex; flex-direction: column; min-width: 0; }
.mkt-anuncio__titular { font-weight: 600; font-size: 14px; overflow-wrap: anywhere; }
.mkt-anuncio__desc { font-size: 13px; color: var(--mkt-texto-suave); overflow-wrap: anywhere; }
.mkt-anuncio__boton {
    flex: 0 0 auto; font-size: 13px; font-weight: 600; padding: 6px 12px; border-radius: 6px;
    background: var(--mkt-fondo-suave); border: 1px solid var(--mkt-borde); white-space: nowrap;
}
.mkt-anuncio__medidas { display: flex; flex-wrap: wrap; gap: 4px var(--mkt-esp-2); margin: var(--mkt-esp-1) 0 0; font-size: 12px; color: var(--mkt-texto-suave); }
.mkt-anuncio__medidas div { display: flex; gap: 4px; }
.mkt-anuncio__medidas dd { margin: 0; font-variant-numeric: tabular-nums; }
.mkt-anuncio__pasado { color: var(--mkt-peligro); font-weight: 600; }
.mkt-anuncio__idea { margin: var(--mkt-esp-1) 0 0; font-size: 13px; overflow-wrap: anywhere; }
.mkt-anuncio__idea span { font-weight: 600; color: var(--mkt-texto-suave); }
.mkt-anuncio__acciones { display: flex; flex-wrap: wrap; gap: 4px; }
.mkt-anuncio__form { display: flex; flex-direction: column; gap: var(--mkt-esp-2); }
.mkt-anuncio__par { display: grid; gap: var(--mkt-esp-2); grid-template-columns: repeat(2, minmax(0, 1fr)); }
.mkt-anuncio__error { background: color-mix(in srgb, var(--mkt-peligro) 10%, var(--mkt-fondo-tarjeta)); color: var(--mkt-texto); }
.mkt-anuncio__opcion { --bs-gutter-x: 0; }
.mkt-anuncio__opcion > * { width: auto; max-width: none; margin-top: 0; }
@media (max-width: 599px) {
    .mkt-anuncio__par { grid-template-columns: minmax(0, 1fr); }
    .mkt-anuncio__acciones > .btn { flex: 1 1 auto; }
}
</style>
