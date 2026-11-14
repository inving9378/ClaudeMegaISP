<template>
    <mkt-tarjeta-panel :titulo="tituloPanel" subtitulo="Variantes A y B para probar cuál funciona mejor" tono="azul" icono-fa="fa-bullhorn">
        <div class="mkt-copy">
            <article v-for="p in piezas" :key="p.id" class="mkt-copy__var">
                <header class="mkt-copy__cabeza">
                    <span class="mkt-copy__letra">Variante {{ p.variante }}</span>
                    <mkt-insignia-estado :texto="estadoDe(p).label" :tono="estadoDe(p).tono" />
                    <span v-if="p.editada" class="mkt-copy__suave">Editada</span>
                </header>

                <div v-if="p.estado === 'generando' || p.estado === 'pendiente'" class="mkt-copy__esqueleto" aria-busy="true">
                    <q-skeleton v-for="n in 5" :key="n" type="text" :width="`${95 - n * 8}%`" />
                </div>

                <mkt-estado-vacio v-else-if="p.estado === 'error'" icono="error_outline" titulo="No se pudo generar" :texto="p.error || 'Usa «Regenerar».'" />

                <template v-else>
                    <p class="mkt-copy__texto">{{ c(p).texto }}</p>
                    <div v-if="hashtags(p).length" class="mkt-copy__tags">
                        <span v-for="h in hashtags(p)" :key="h" class="mkt-copy__tag">{{ h }}</span>
                    </div>
                    <p v-if="c(p).cta" class="mkt-copy__cta"><q-icon name="ads_click" size="16px" aria-hidden="true" /> {{ c(p).cta }}</p>
                </template>

                <footer class="mkt-copy__acciones">
                    <mkt-boton v-if="p.estado === 'lista'" tipo="secundario" chico icono-fa="fa-copy" etiqueta="Copiar" @click="copiar(p)" />
                    <mkt-boton v-if="p.estado === 'lista'" tipo="secundario" chico icono-fa="fa-edit" etiqueta="Editar" @click="abrirEditor(p)" />
                    <mkt-boton v-if="puedeGenerar && (p.estado === 'lista' || p.estado === 'error')" tipo="secundario" chico icono-fa="fa-redo" etiqueta="Regenerar" @click="abrirRegenerar(p)" />
                </footer>
            </article>
        </div>

        <mkt-dialogo v-model="editor" :titulo="`Editar ${tituloPanel} ${actual?.variante || ''}`" :guardando="guardando" :ancho="640">
            <div v-if="borrador" class="mkt-copy__form">
                <q-input
                    v-model="borrador.texto"
                    outlined
                    autogrow
                    type="textarea"
                    label="Texto de la publicación *"
                    counter
                    :maxlength="lim.texto"
                    :error="!!err('texto')"
                    :error-message="err('texto')"
                />
                <q-select
                    v-model="borrador.hashtags"
                    outlined
                    multiple
                    use-input
                    use-chips
                    hide-dropdown-icon
                    new-value-mode="add-unique"
                    input-debounce="0"
                    label="Hashtags"
                    :hint="`Escribe uno y presiona Enter (máximo ${lim.hashtags}).`"
                    :max-values="lim.hashtags"
                    :error="!!err('hashtags')"
                    :error-message="err('hashtags')"
                    @new-value="nuevoHashtag"
                />
                <q-input v-model="borrador.cta" outlined label="Llamada a la acción" counter :maxlength="lim.cta" :error="!!err('cta')" :error-message="err('cta')" />
                <q-banner v-if="error" dense rounded class="mkt-copy__error" role="alert">{{ error }}</q-banner>
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
import { ESTADOS_PIEZA, ETIQUETAS_PIEZA, LIMITES_COPY, clonar, conGato, copiarTexto } from "./catalogos360";
import DialogoRegenerarPieza from "./DialogoRegenerarPieza.vue";

/** Copies de Facebook o Instagram (§2.5.5), variantes A y B lado a lado. Editar = PUT pieza; Regenerar = una sola variante. */
export default {
    name: "TarjetaCopy",
    components: { MktTarjetaPanel, MktEstadoVacio, MktInsigniaEstado, MktDialogo, DialogoRegenerarPieza },
    props: {
        campanaId: { type: Number, required: true },
        piezas: { type: Array, default: () => [] },
        puedeGenerar: { type: Boolean, default: true },
    },
    emits: ["actualizada", "en-proceso"],
    data() {
        return { editor: false, regenerar: false, actual: null, borrador: null, guardando: false, error: "", errores: {} };
    },
    computed: {
        tipo() {
            return this.piezas[0]?.pieza || "copy_facebook";
        },
        tituloPanel() {
            return this.piezas[0]?.pieza_etiqueta || ETIQUETAS_PIEZA[this.tipo] || "Copy";
        },
        lim() {
            return LIMITES_COPY[this.tipo] || LIMITES_COPY.copy_facebook;
        },
    },
    methods: {
        c: (p) => (p?.contenido && typeof p.contenido === "object" ? p.contenido : {}),
        estadoDe: (p) => ESTADOS_PIEZA[p?.estado] || ESTADOS_PIEZA.pendiente,
        hashtags(p) {
            const h = this.c(p).hashtags;
            return Array.isArray(h) ? h.map(conGato).filter(Boolean) : [];
        },
        err(campo) {
            const k = Object.keys(this.errores).find((x) => x === `contenido.${campo}` || x.startsWith(`contenido.${campo}.`));
            return k ? this.errores[k] : "";
        },
        async copiar(p) {
            const partes = [this.c(p).texto, this.hashtags(p).join(" ")].filter(Boolean);
            const ok = await copiarTexto(partes.join("\n\n"));
            message(ok ? "Texto copiado." : "No se pudo copiar. Selecciónalo y cópialo a mano.", ok ? "success" : "error");
        },
        abrirEditor(p) {
            this.actual = p;
            const c = clonar(this.c(p)) || {};
            this.borrador = { ...c, texto: c.texto || "", hashtags: Array.isArray(c.hashtags) ? c.hashtags : [], cta: c.cta || "" };
            this.error = "";
            this.errores = {};
            this.editor = true;
        },
        abrirRegenerar(p) {
            this.actual = p;
            this.regenerar = true;
        },
        nuevoHashtag(valor, hecho) {
            const v = conGato(valor).slice(0, this.lim.hashtag + 1);
            if (v.length > 1) hecho(v, "add-unique");
        },
        async guardar() {
            if (this.guardando || !this.actual) return;
            if (!this.borrador.texto.trim()) {
                this.errores = { "contenido.texto": "Escribe el texto de la publicación." };
                return;
            }
            this.guardando = true;
            this.error = "";
            this.errores = {};
            try {
                const contenido = { ...this.borrador, texto: this.borrador.texto.trim(), cta: this.borrador.cta.trim() };
                const { data } = await axios.put(`/api/marketing/campanas-360/${this.campanaId}/piezas/${this.actual.id}`, { contenido });
                this.$emit("actualizada", data?.data || { ...this.actual, contenido });
                this.editor = false;
                message("Copy guardado.");
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
.mkt-copy { display: grid; gap: var(--mkt-esp-2); grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
.mkt-copy__var {
    display: flex; flex-direction: column; gap: var(--mkt-esp-1);
    padding: 12px var(--mkt-esp-2); border: 1px solid var(--mkt-borde); border-radius: var(--mkt-radio); min-width: 0;
}
.mkt-copy__cabeza { display: flex; align-items: center; gap: var(--mkt-esp-1); flex-wrap: wrap; }
.mkt-copy__letra { font-size: 13px; font-weight: 600; color: var(--mkt-primario); }
.mkt-copy__suave { font-size: 12px; color: var(--mkt-texto-suave); }
.mkt-copy__esqueleto { display: flex; flex-direction: column; gap: 4px; }
.mkt-copy__texto { margin: 0; font-size: 14px; line-height: 22px; white-space: pre-line; overflow-wrap: anywhere; flex: 1 1 auto; }
.mkt-copy__tags { display: flex; flex-wrap: wrap; gap: 4px; }
.mkt-copy__tag { font-size: 13px; color: var(--mkt-primario); background: color-mix(in srgb, var(--mkt-primario) 8%, transparent); padding: 1px 8px; border-radius: 999px; }
.mkt-copy__cta { display: flex; align-items: center; gap: 4px; margin: 0; font-size: 14px; font-weight: 600; }
.mkt-copy__acciones { display: flex; flex-wrap: wrap; gap: 4px; margin-top: auto; padding-top: var(--mkt-esp-1); border-top: 1px solid var(--mkt-borde); }
.mkt-copy__form { display: flex; flex-direction: column; gap: var(--mkt-esp-2); }
.mkt-copy__error { background: color-mix(in srgb, var(--mkt-peligro) 10%, var(--mkt-fondo-tarjeta)); color: var(--mkt-texto); }
@media (max-width: 599px) {
    .mkt-copy { grid-template-columns: minmax(0, 1fr); }
    .mkt-copy__acciones > .btn { flex: 1 1 auto; }
}
</style>
