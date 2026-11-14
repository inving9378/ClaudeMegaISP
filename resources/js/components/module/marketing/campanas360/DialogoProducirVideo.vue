<template>
    <mkt-dialogo :model-value="modelValue" titulo="Producir en Video MVM" :guardando="enviando" :ancho="560" @update:model-value="(v) => $emit('update:modelValue', v)">
        <div class="mkt-prod">
            <dl class="mkt-prod__resumen">
                <dt>Guion</dt>
                <dd>{{ pieza?.contenido?.titulo || etiqueta }}</dd>
                <dt>Formato</dt>
                <dd>{{ formatoTexto }}</dd>
                <dt>Escenas</dt>
                <dd>{{ escenas.length }} · {{ formatoDuracion(total) }}</dd>
            </dl>

            <q-select
                v-model="voz"
                outlined
                emit-value
                map-options
                :options="voces"
                label="Voz en off"
                :disable="enviando"
            >
                <template #prepend><q-icon name="record_voice_over" size="20px" /></template>
                <template #option="scope">
                    <q-item v-bind="scope.itemProps" class="mkt-prod__opcion"><q-item-section>{{ scope.opt.label }}</q-item-section></q-item>
                </template>
            </q-select>

            <q-select
                v-model="musica"
                outlined
                emit-value
                map-options
                :options="musicas"
                label="Música de fondo"
                :disable="enviando"
            >
                <template #prepend><q-icon name="music_note" size="20px" /></template>
                <template #option="scope">
                    <q-item v-bind="scope.itemProps" class="mkt-prod__opcion"><q-item-section>{{ scope.opt.label }}</q-item-section></q-item>
                </template>
            </q-select>

            <p class="mkt-prod__nota">
                El video se arma con el motor de Video MVM: escenas, textos en pantalla, voz y música ya cargados. Tarda unos minutos;
                el estado se ve en esta ficha y en Video → Cola.
            </p>

            <q-banner v-if="error" rounded class="mkt-prod__error" role="alert">
                {{ error }}
                <ul v-if="detalle.length" class="mkt-prod__lista">
                    <li v-for="(d, i) in detalle" :key="i">{{ d }}</li>
                </ul>
            </q-banner>
        </div>
        <template #acciones>
            <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="enviando" @click="$emit('update:modelValue', false)" />
            <mkt-boton tipo="guardar" icono-fa="fa-film" etiqueta="Producir" :cargando="enviando" @click="producir" />
        </template>
    </mkt-dialogo>
</template>

<script>
import axios from "axios";
import { MktDialogo, mensajeDeError, formatoDuracion } from "../kit";
import { VOCES, MUSICAS, LIMITES_GUION, ETIQUETAS_PIEZA, duracionTotal } from "./catalogos360";

/** Producir un guion en Video MVM (§2.4.12): voz y música precargadas del guion. 202 → Video «en_cola». */
export default {
    name: "DialogoProducirVideo",
    components: { MktDialogo },
    props: {
        modelValue: { type: Boolean, default: false },
        campanaId: { type: Number, required: true },
        pieza: { type: Object, default: null },
    },
    emits: ["update:modelValue", "producido"],
    data() {
        return { voz: "nova", musica: "sin_musica", enviando: false, error: "", detalle: [], voces: VOCES, musicas: MUSICAS };
    },
    computed: {
        escenas() {
            return Array.isArray(this.pieza?.contenido?.escenas) ? this.pieza.contenido.escenas : [];
        },
        total() {
            return this.pieza?.contenido?.duracion_total_s ?? duracionTotal(this.escenas);
        },
        etiqueta() {
            return ETIQUETAS_PIEZA[this.pieza?.pieza] || "Guion";
        },
        formatoTexto() {
            const f = this.pieza?.contenido?.formato || LIMITES_GUION[this.pieza?.pieza]?.formato;
            return f === "16:9" ? "Video horizontal 16:9 (1920 × 1080)" : "Reel vertical 9:16 (1080 × 1920)";
        },
    },
    watch: {
        modelValue(v) {
            if (!v) return;
            const c = this.pieza?.contenido || {};
            this.voz = VOCES.some((x) => x.value === c.voz?.id) ? c.voz.id : "nova";
            this.musica = MUSICAS.some((x) => x.value === c.musica?.ambiente) ? c.musica.ambiente : "sin_musica";
            this.error = "";
            this.detalle = [];
        },
    },
    methods: {
        formatoDuracion,
        async producir() {
            if (this.enviando || !this.pieza) return;
            this.enviando = true;
            this.error = "";
            this.detalle = [];
            try {
                const { data } = await axios.post(`/api/marketing/campanas-360/${this.campanaId}/piezas/${this.pieza.id}/producir`, {
                    voz: this.voz,
                    musica: this.musica,
                });
                this.$emit("producido", data?.data || null);
                this.$emit("update:modelValue", false);
            } catch (e) {
                const d = e?.response?.data || {};
                this.error = mensajeDeError(e, "No se pudo mandar a producir. Intenta de nuevo.");
                const errs = d.errores || d.errors;
                if (Array.isArray(errs)) this.detalle = errs.map(String);
                else if (errs && typeof errs === "object") {
                    this.detalle = Object.entries(errs).map(([k, v]) => `${textoClave(k)}: ${Array.isArray(v) ? v.join(" ") : v}`);
                }
            } finally {
                this.enviando = false;
            }
        },
    },
};

/** "escenas.2.texto_pantalla" → "Escena 3 · texto_pantalla". */
function textoClave(k) {
    const m = /^escenas\.(\d+)(?:\.(.+))?$/.exec(String(k));
    if (m) return `Escena ${Number(m[1]) + 1}${m[2] ? ` · ${m[2].replace(/_/g, " ")}` : ""}`;
    return String(k).replace(/_/g, " ");
}
</script>

<style scoped>
.mkt-prod { display: flex; flex-direction: column; gap: var(--mkt-esp-2); }
.mkt-prod__resumen { display: grid; grid-template-columns: max-content minmax(0, 1fr); gap: 4px var(--mkt-esp-2); margin: 0; font-size: 14px; }
.mkt-prod__resumen dt { color: var(--mkt-texto-suave); }
.mkt-prod__resumen dd { margin: 0; font-weight: 500; overflow-wrap: anywhere; }
.mkt-prod__nota { margin: 0; font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-prod__error { background: color-mix(in srgb, var(--mkt-peligro) 10%, var(--mkt-fondo-tarjeta)); color: var(--mkt-texto); font-size: 14px; }
.mkt-prod__lista { margin: 6px 0 0; padding-left: 18px; font-size: 13px; }
.mkt-prod__opcion { --bs-gutter-x: 0; }
.mkt-prod__opcion > * { width: auto; max-width: none; margin-top: 0; }
</style>
