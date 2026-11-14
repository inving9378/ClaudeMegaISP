<template>
    <div class="mkt-paso">
        <q-input
            :model-value="publico.descripcion"
            outlined
            autogrow
            type="textarea"
            label="Tipo de público *"
            hint="A quién le habla la campaña. Ej.: Nuevos clientes, familias con fibra nueva"
            counter
            :maxlength="limites.publicoDescripcion[1]"
            :error="!!errores['publico.descripcion']"
            :error-message="errores['publico.descripcion']"
            @update:model-value="(v) => campo('descripcion', v)"
        />

    </div>
</template>

<script>
import { LIMITES_CAMPANA } from "./catalogos360";

/**
 * Asistente 360 · paso 2: público. Ajuste de Henry (13-nov): SOLO el tipo de público (p. ej. «Nuevos clientes»).
 * Edad, nichos, zonas e intereses ya no se piden ni se mandan a la API.
 */
export default {
    name: "PasoPublico",
    props: {
        modelValue: { type: Object, required: true },
        errores: { type: Object, default: () => ({}) },
    },
    emits: ["update:modelValue"],
    data() {
        return { limites: LIMITES_CAMPANA };
    },
    computed: {
        publico() {
            return this.modelValue.publico || {};
        },
    },
    methods: {
        campo(nombre, valor) {
            this.$emit("update:modelValue", { ...this.modelValue, publico: { ...this.publico, [nombre]: valor } });
        },
    },
};
</script>

<style scoped>
.mkt-paso { display: flex; flex-direction: column; gap: var(--mkt-esp-2); }
</style>
