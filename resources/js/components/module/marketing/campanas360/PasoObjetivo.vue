<template>
    <div class="mkt-paso">
        <q-input
            :model-value="modelValue.nombre"
            outlined
            label="Nombre de la campaña *"
            hint="Para reconocerla en la lista. Ej.: Fibra 500 diciembre"
            counter
            :maxlength="limites.nombre[1]"
            :error="!!errores.nombre"
            :error-message="errores.nombre"
            @update:model-value="(v) => actualizar({ nombre: v })"
        />

        <fieldset class="mkt-paso__grupo">
            <legend class="mkt-paso__leyenda">¿Qué quieres lograr? *</legend>
            <div class="mkt-paso__opciones" role="radiogroup" aria-label="Objetivo de la campaña">
                <button
                    v-for="o in objetivos"
                    :key="o.value"
                    type="button"
                    role="radio"
                    :aria-checked="modelValue.objetivo === o.value ? 'true' : 'false'"
                    class="mkt-paso__opcion"
                    :class="{ 'mkt-paso__opcion--activa': modelValue.objetivo === o.value }"
                    @click="actualizar({ objetivo: o.value })"
                >
                    <span class="mkt-paso__opcion-icono" aria-hidden="true"><q-icon :name="o.icono" size="22px" /></span>
                    <span class="mkt-paso__opcion-textos">
                        <span class="mkt-paso__opcion-titulo">{{ o.label }}</span>
                        <span class="mkt-paso__opcion-ayuda">{{ o.ayuda }}</span>
                    </span>
                    <q-icon v-if="modelValue.objetivo === o.value" name="check_circle" size="20px" class="mkt-paso__opcion-check" aria-hidden="true" />
                </button>
            </div>
            <div v-if="errores.objetivo" class="mkt-paso__error" role="alert">{{ errores.objetivo }}</div>
        </fieldset>
    </div>
</template>

<script>
import { OBJETIVOS, LIMITES_CAMPANA } from "./catalogos360";

/** Asistente 360 · paso 1: nombre y objetivo (§2.5.1). */
export default {
    name: "PasoObjetivo",
    props: {
        modelValue: { type: Object, required: true },
        errores: { type: Object, default: () => ({}) },
    },
    emits: ["update:modelValue"],
    data() {
        return { objetivos: OBJETIVOS, limites: LIMITES_CAMPANA };
    },
    methods: {
        actualizar(parche) {
            this.$emit("update:modelValue", { ...this.modelValue, ...parche });
        },
    },
};
</script>

<style scoped>
.mkt-paso { display: flex; flex-direction: column; gap: var(--mkt-esp-3); }
.mkt-paso__grupo { border: 0; padding: 0; margin: 0; min-width: 0; }
.mkt-paso__leyenda { font-size: 14px; font-weight: 600; margin-bottom: var(--mkt-esp-1); color: var(--mkt-texto); }
.mkt-paso__opciones { display: grid; gap: var(--mkt-esp-1); grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
.mkt-paso__opcion {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    text-align: left;
    font: inherit;
    color: var(--mkt-texto);
    background: var(--mkt-fondo-tarjeta);
    border: 1px solid var(--mkt-borde);
    border-radius: var(--mkt-radio);
    cursor: pointer;
    transition: border-color 0.15s ease, background-color 0.15s ease;
}
.mkt-paso__opcion:hover { border-color: var(--mkt-primario); }
.mkt-paso__opcion:focus-visible { outline: 2px solid var(--mkt-primario); outline-offset: 2px; }
.mkt-paso__opcion--activa { border-color: var(--mkt-primario); background: color-mix(in srgb, var(--mkt-primario) 6%, var(--mkt-fondo-tarjeta)); }
.mkt-paso__opcion-icono {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; flex: 0 0 40px; border-radius: 8px;
    color: var(--mkt-primario); background: color-mix(in srgb, var(--mkt-primario) 10%, transparent);
}
.mkt-paso__opcion-textos { display: flex; flex-direction: column; min-width: 0; flex: 1 1 auto; }
.mkt-paso__opcion-titulo { font-weight: 600; font-size: 14px; }
.mkt-paso__opcion-ayuda { font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-paso__opcion-check { color: var(--mkt-primario); }
.mkt-paso__error { margin-top: var(--mkt-esp-1); font-size: 13px; color: var(--mkt-peligro); }
@media (max-width: 599px) {
    .mkt-paso__opciones { grid-template-columns: minmax(0, 1fr); }
}
@media (prefers-reduced-motion: reduce) {
    .mkt-paso__opcion { transition: none; }
}
</style>
