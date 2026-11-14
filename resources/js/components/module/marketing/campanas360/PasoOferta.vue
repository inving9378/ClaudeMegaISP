<template>
    <div class="mkt-paso">
        <q-input
            :model-value="oferta.descripcion"
            outlined
            autogrow
            type="textarea"
            label="¿Qué ofrecemos? *"
            hint="La IA no inventa precios ni promociones: solo usa lo que escribas aquí."
            counter
            :maxlength="limites.ofertaDescripcion[1]"
            :error="!!errores['oferta.descripcion']"
            :error-message="errores['oferta.descripcion']"
            @update:model-value="(v) => campo('descripcion', v)"
        />

        <div class="mkt-paso__par">
            <q-input
                :model-value="oferta.plan"
                outlined
                label="Plan"
                hint="Ej.: Fibra 500"
                :maxlength="limites.plan"
                :error="!!errores['oferta.plan']"
                :error-message="errores['oferta.plan']"
                @update:model-value="(v) => campo('plan', v)"
            />
            <q-input
                :model-value="oferta.precio_mxn"
                outlined
                inputmode="decimal"
                label="Precio al mes"
                prefix="$"
                suffix="MXN"
                :hint="oferta.precio_mxn ? formatoMXN(limpiarMonto(oferta.precio_mxn)) : 'Opcional. Ej.: 599.00'"
                :error="!!errores['oferta.precio_mxn']"
                :error-message="errores['oferta.precio_mxn']"
                @update:model-value="(v) => campo('precio_mxn', v)"
            />
        </div>

        <q-input
            :model-value="oferta.condiciones"
            outlined
            autogrow
            type="textarea"
            label="Condiciones"
            hint="Ej.: válido para los primeros 100 clientes; instalación gratis."
            counter
            :maxlength="limites.condiciones"
            :error="!!errores['oferta.condiciones']"
            :error-message="errores['oferta.condiciones']"
            @update:model-value="(v) => campo('condiciones', v)"
        />

        <q-input
            :model-value="oferta.vigencia_hasta"
            outlined
            type="date"
            stack-label
            label="La oferta vale hasta"
            class="mkt-paso__fecha"
            :error="!!errores['oferta.vigencia_hasta']"
            :error-message="errores['oferta.vigencia_hasta']"
            @update:model-value="(v) => campo('vigencia_hasta', v)"
        />
    </div>
</template>

<script>
import { formatoMXN } from "../kit";
import { LIMITES_CAMPANA } from "./catalogos360";

/** Asistente 360 · paso 3: oferta (§2.4.2). El precio viaja como cadena; aquí solo se muestra con formatoMXN. */
export default {
    name: "PasoOferta",
    props: {
        modelValue: { type: Object, required: true },
        errores: { type: Object, default: () => ({}) },
    },
    emits: ["update:modelValue"],
    data() {
        return { limites: LIMITES_CAMPANA };
    },
    computed: {
        oferta() {
            return this.modelValue.oferta || {};
        },
    },
    methods: {
        formatoMXN,
        limpiarMonto: (v) => String(v ?? "").replace(/[,\s$]/g, ""),
        campo(nombre, valor) {
            this.$emit("update:modelValue", { ...this.modelValue, oferta: { ...this.oferta, [nombre]: valor } });
        },
    },
};
</script>

<style scoped>
.mkt-paso { display: flex; flex-direction: column; gap: var(--mkt-esp-2); }
.mkt-paso__par { display: grid; gap: var(--mkt-esp-2); grid-template-columns: repeat(2, minmax(0, 1fr)); }
.mkt-paso__fecha { max-width: 240px; }
@media (max-width: 599px) {
    .mkt-paso__par { grid-template-columns: minmax(0, 1fr); }
    .mkt-paso__fecha { max-width: none; }
}
</style>
