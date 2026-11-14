<template>
    <mkt-tarjeta-panel titulo="Mensajes de WhatsApp" subtitulo="Difusión, seguimiento y respuesta a quien pregunta" tono="verde" icono-fa="fa-comments">
        <q-banner rounded class="mkt-wa__aviso" role="note">
            <template #avatar><q-icon name="info" size="22px" /></template>
            {{ aviso }}
        </q-banner>

        <div class="mkt-wa">
            <article v-for="p in piezas" :key="p.id" class="mkt-wa__var">
                <header class="mkt-wa__cabeza">
                    <span class="mkt-wa__uso">{{ etiquetaUsoWhatsApp(c(p).uso) || `Variante ${p.variante}` }}</span>
                    <mkt-insignia-estado :texto="estadoDe(p).label" :tono="estadoDe(p).tono" />
                </header>

                <div v-if="p.estado === 'generando' || p.estado === 'pendiente'" class="mkt-wa__esqueleto" aria-busy="true">
                    <q-skeleton v-for="n in 4" :key="n" type="text" :width="`${92 - n * 10}%`" />
                </div>

                <mkt-estado-vacio v-else-if="p.estado === 'error'" icono="error_outline" titulo="No se pudo generar" :texto="p.error || 'Usa «Regenerar».'" />

                <p v-else class="mkt-wa__burbuja">{{ c(p).texto }}</p>

                <footer class="mkt-wa__acciones">
                    <mkt-boton v-if="p.estado === 'lista'" tipo="secundario" chico icono-fa="fa-copy" etiqueta="Copiar" @click="copiar(p)" />
                    <mkt-boton v-if="p.estado === 'lista'" tipo="secundario" chico icono-fa="fa-edit" etiqueta="Editar" @click="abrirEditor(p)" />
                    <mkt-boton v-if="puedeGenerar && (p.estado === 'lista' || p.estado === 'error')" tipo="secundario" chico icono-fa="fa-redo" etiqueta="Regenerar" @click="abrirRegenerar(p)" />
                </footer>
            </article>
        </div>

        <mkt-dialogo v-model="editor" titulo="Editar mensaje de WhatsApp" :guardando="guardando" :ancho="560">
            <div v-if="b" class="mkt-wa__form">
                <q-input
                    v-model="b.texto"
                    outlined
                    autogrow
                    type="textarea"
                    :label="`Mensaje (${etiquetaUsoWhatsApp(b.uso)}) *`"
                    counter
                    :maxlength="limite"
                    :error="!!err('texto')"
                    :error-message="err('texto')"
                />
                <p class="mkt-wa__nota">{{ aviso }}</p>
                <q-banner v-if="error" dense rounded class="mkt-wa__error" role="alert">{{ error }}</q-banner>
            </div>
            <template #acciones>
                <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="guardando" @click="editor = false" />
                <mkt-boton tipo="guardar" etiqueta="Guardar" :cargando="guardando" @click="guardar" />
            </template>
        </mkt-dialogo>

        <dialogo-regenerar-pieza v-model="regenerar" :campana-id="campanaId" :pieza="actual" @regenerada="alRegenerar" />
    </mkt-tarjeta-panel>
</template>

<script>
import axios from "axios";
import { message } from "../../../../helpers/toastMsg";
import { MktTarjetaPanel, MktEstadoVacio, MktInsigniaEstado, MktDialogo, mensajeDeError, erroresPorCampo } from "../kit";
import { ESTADOS_PIEZA, AVISO_WHATSAPP, LIMITE_WHATSAPP, etiquetaUsoWhatsApp, clonar, copiarTexto } from "./catalogos360";
import DialogoRegenerarPieza from "./DialogoRegenerarPieza.vue";

/**
 * Mensajes de WhatsApp (§2.5.5) — SOLO TEXTO. No hay botón de enviar ni conexión con ningún gateway: solo «Copiar»,
 * «Editar» (PUT pieza) y «Regenerar» (una variante).
 */
export default {
    name: "TarjetaWhatsApp",
    components: { MktTarjetaPanel, MktEstadoVacio, MktInsigniaEstado, MktDialogo, DialogoRegenerarPieza },
    props: {
        campanaId: { type: Number, required: true },
        piezas: { type: Array, default: () => [] },
        puedeGenerar: { type: Boolean, default: true },
    },
    emits: ["actualizada", "en-proceso"],
    data() {
        return {
            aviso: AVISO_WHATSAPP, limite: LIMITE_WHATSAPP,
            editor: false, regenerar: false, actual: null, b: null, guardando: false, error: "", errores: {},
        };
    },
    methods: {
        etiquetaUsoWhatsApp,
        c: (p) => (p?.contenido && typeof p.contenido === "object" ? p.contenido : {}),
        estadoDe: (p) => ESTADOS_PIEZA[p?.estado] || ESTADOS_PIEZA.pendiente,
        err(campo) {
            return this.errores[`contenido.${campo}`] || "";
        },
        async copiar(p) {
            const ok = await copiarTexto(this.c(p).texto || "");
            message(ok ? "Mensaje copiado." : "No se pudo copiar. Selecciónalo y cópialo a mano.", ok ? "success" : "error");
        },
        abrirEditor(p) {
            this.actual = p;
            const c = clonar(this.c(p)) || {};
            this.b = { ...c, texto: c.texto || "", uso: c.uso || "difusion" };
            this.error = "";
            this.errores = {};
            this.editor = true;
        },
        abrirRegenerar(p) {
            this.actual = p;
            this.regenerar = true;
        },
        async guardar() {
            if (this.guardando || !this.b) return;
            if (!this.b.texto.trim()) {
                this.errores = { "contenido.texto": "Escribe el mensaje." };
                return;
            }
            this.guardando = true;
            this.error = "";
            this.errores = {};
            try {
                const contenido = { ...this.b, texto: this.b.texto.trim() };
                const { data } = await axios.put(`/api/marketing/campanas-360/${this.campanaId}/piezas/${this.actual.id}`, { contenido });
                this.$emit("actualizada", data?.data || { ...this.actual, contenido });
                this.editor = false;
                message("Mensaje guardado.");
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
            message("Regenerando el mensaje.");
        },
    },
};
</script>

<style scoped>
.mkt-wa__aviso {
    margin-bottom: var(--mkt-esp-2);
    background: color-mix(in srgb, var(--mkt-info) 10%, var(--mkt-fondo-tarjeta));
    border: 1px solid color-mix(in srgb, var(--mkt-info) 35%, transparent);
    color: var(--mkt-texto);
    font-size: 14px;
}
.mkt-wa { display: grid; gap: var(--mkt-esp-2); grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
.mkt-wa__var { display: flex; flex-direction: column; gap: var(--mkt-esp-1); padding: 12px; border: 1px solid var(--mkt-borde); border-radius: var(--mkt-radio); min-width: 0; }
.mkt-wa__cabeza { display: flex; align-items: center; justify-content: space-between; gap: var(--mkt-esp-1); }
.mkt-wa__uso { font-size: 13px; font-weight: 600; color: var(--mkt-primario); }
.mkt-wa__esqueleto { display: flex; flex-direction: column; gap: 4px; }
.mkt-wa__burbuja {
    margin: 0; padding: 10px 12px; font-size: 14px; line-height: 21px; white-space: pre-line; overflow-wrap: anywhere;
    background: var(--mkt-fondo-suave); border: 1px solid var(--mkt-borde); border-radius: 4px 12px 12px 12px; flex: 1 1 auto;
}
.mkt-wa__acciones { display: flex; flex-wrap: wrap; gap: 4px; margin-top: auto; }
.mkt-wa__form { display: flex; flex-direction: column; gap: var(--mkt-esp-1); }
.mkt-wa__nota { margin: 0; font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-wa__error { background: color-mix(in srgb, var(--mkt-peligro) 10%, var(--mkt-fondo-tarjeta)); color: var(--mkt-texto); }
@media (max-width: 599px) {
    .mkt-wa { grid-template-columns: minmax(0, 1fr); }
    .mkt-wa__acciones > .btn { flex: 1 1 auto; }
}
</style>
