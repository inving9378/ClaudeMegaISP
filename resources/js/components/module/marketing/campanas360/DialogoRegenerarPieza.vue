<template>
    <mkt-dialogo :model-value="modelValue" :titulo="titulo" :guardando="enviando" :ancho="520" @update:model-value="(v) => $emit('update:modelValue', v)">
        <p class="mkt-regen__texto">
            La IA vuelve a escribir <strong>solo esta pieza</strong> ({{ etiqueta }}). Las demás no cambian.
            <span v-if="pieza?.editada"> Lo que editaste a mano se guarda como versión anterior.</span>
        </p>
        <q-input
            v-model="indicaciones"
            outlined
            autogrow
            type="textarea"
            label="Indicaciones para la IA (opcional)"
            hint="Ej.: más corto y con humor; menciona la instalación gratis."
            counter
            :maxlength="limite"
            :disable="enviando"
            :error="!!errorCampo"
            :error-message="errorCampo"
        />
        <q-banner v-if="error" dense rounded class="mkt-regen__error" role="alert">{{ error }}</q-banner>
        <template #acciones>
            <mkt-boton tipo="volver" etiqueta="Cancelar" :deshabilitado="enviando" @click="$emit('update:modelValue', false)" />
            <mkt-boton tipo="guardar" icono-fa="fa-magic" etiqueta="Regenerar" :cargando="enviando" @click="enviar" />
        </template>
    </mkt-dialogo>
</template>

<script>
import axios from "axios";
import { MktDialogo, mensajeDeError, erroresPorCampo } from "../kit";
import { ETIQUETAS_PIEZA, LIMITE_INDICACIONES } from "./catalogos360";

/** Regenerar UNA pieza (§2.4.11): POST …/piezas/{piezaId}/regenerar → 202 con la pieza en «generando». */
export default {
    name: "DialogoRegenerarPieza",
    components: { MktDialogo },
    props: {
        modelValue: { type: Boolean, default: false },
        campanaId: { type: Number, required: true },
        pieza: { type: Object, default: null },
    },
    emits: ["update:modelValue", "regenerada"],
    data() {
        return { indicaciones: "", enviando: false, error: "", errorCampo: "", limite: LIMITE_INDICACIONES };
    },
    computed: {
        etiqueta() {
            const e = this.pieza ? ETIQUETAS_PIEZA[this.pieza.pieza] || "pieza" : "pieza";
            return this.pieza?.variante ? `${e} ${this.pieza.variante}` : e;
        },
        titulo() {
            return `Regenerar ${this.etiqueta}`;
        },
    },
    watch: {
        modelValue(v) {
            if (v) {
                this.indicaciones = "";
                this.error = "";
                this.errorCampo = "";
            }
        },
    },
    methods: {
        async enviar() {
            if (this.enviando || !this.pieza) return;
            this.enviando = true;
            this.error = "";
            this.errorCampo = "";
            try {
                const cuerpo = {};
                const t = this.indicaciones.trim();
                if (t) cuerpo.indicaciones = t;
                const { data } = await axios.post(`/api/marketing/campanas-360/${this.campanaId}/piezas/${this.pieza.id}/regenerar`, cuerpo);
                this.$emit("regenerada", data?.data || { ...this.pieza, estado: "generando" });
                this.$emit("update:modelValue", false);
            } catch (e) {
                this.errorCampo = erroresPorCampo(e).indicaciones || "";
                this.error = this.errorCampo ? "" : mensajeDeError(e, "No se pudo regenerar. Intenta de nuevo.");
            } finally {
                this.enviando = false;
            }
        },
    },
};
</script>

<style scoped>
.mkt-regen__texto { margin: 0 0 var(--mkt-esp-2); font-size: 14px; line-height: 22px; }
.mkt-regen__error { margin-top: var(--mkt-esp-1); background: color-mix(in srgb, var(--mkt-peligro) 10%, var(--mkt-fondo-tarjeta)); color: var(--mkt-texto); }
</style>
