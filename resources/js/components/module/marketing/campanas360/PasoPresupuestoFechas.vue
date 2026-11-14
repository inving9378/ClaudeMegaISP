<template>
    <div class="mkt-paso">
        <q-input
            :model-value="modelValue.presupuesto_mxn"
            outlined
            inputmode="decimal"
            label="Presupuesto total *"
            prefix="$"
            suffix="MXN"
            class="mkt-paso__monto"
            :hint="hintPresupuesto"
            :error="!!errores.presupuesto_mxn"
            :error-message="errores.presupuesto_mxn"
            @update:model-value="(v) => actualizar({ presupuesto_mxn: v })"
        />

        <div class="mkt-paso__par">
            <q-input
                :model-value="modelValue.fecha_inicio"
                outlined
                type="date"
                stack-label
                label="Empieza *"
                :min="minInicio"
                :error="!!errores.fecha_inicio"
                :error-message="errores.fecha_inicio"
                @update:model-value="(v) => actualizar({ fecha_inicio: v })"
            />
            <q-input
                :model-value="modelValue.fecha_fin"
                outlined
                type="date"
                stack-label
                label="Termina *"
                :min="modelValue.fecha_inicio || minInicio"
                :error="!!errorFin"
                :error-message="errorFin"
                @update:model-value="(v) => actualizar({ fecha_fin: v })"
            />
        </div>
        <p v-if="duracion !== null && duracion >= 0" class="mkt-paso__nota">
            La campaña dura {{ duracion + 1 }} {{ duracion === 0 ? "día" : "días" }}<span v-if="semanas">, unas {{ semanas }} {{ semanas === 1 ? "semana" : "semanas" }}</span>.
        </p>
    </div>
</template>

<script>
import { formatoMXN } from "../kit";
import { hoyMexico, diasEntre } from "./catalogos360";

/** Asistente 360 · paso 5: presupuesto en MXN y fechas (§2.4.2). */
export default {
    name: "PasoPresupuestoFechas",
    props: {
        modelValue: { type: Object, required: true },
        errores: { type: Object, default: () => ({}) },
        /** Al editar, la fecha de inicio ya guardada puede ser pasada: el mínimo solo aplica si cambia. */
        fechaInicioOriginal: { type: String, default: null },
    },
    emits: ["update:modelValue"],
    computed: {
        minInicio() {
            const hoy = hoyMexico();
            const orig = this.fechaInicioOriginal;
            return orig && orig < hoy ? orig : hoy;
        },
        /** Error de «Termina»: el del backend (422 `fecha_fin`) o, al momento, si queda antes de «Empieza». */
        errorFin() {
            if (this.errores.fecha_fin) return this.errores.fecha_fin;
            const { fecha_inicio: ini, fecha_fin: fin } = this.modelValue;
            return ini && fin && fin < ini ? "La fecha de fin no puede ser anterior a la de inicio." : "";
        },
        duracion() {
            return diasEntre(this.modelValue.fecha_inicio, this.modelValue.fecha_fin);
        },
        semanas() {
            return this.duracion !== null && this.duracion >= 6 ? Math.round((this.duracion + 1) / 7) : 0;
        },
        hintPresupuesto() {
            const limpio = String(this.modelValue.presupuesto_mxn ?? "").replace(/[,\s$]/g, "");
            if (limpio === "") return "Lo que se piensa invertir en anuncios. Pon 0 si la campaña es solo orgánica.";
            if (Number(limpio) === 0) return "Campaña orgánica (sin pauta pagada).";
            return formatoMXN(limpio);
        },
    },
    methods: {
        actualizar(parche) {
            this.$emit("update:modelValue", { ...this.modelValue, ...parche });
        },
    },
};
</script>

<style scoped>
.mkt-paso { display: flex; flex-direction: column; gap: var(--mkt-esp-2); }
.mkt-paso__monto { max-width: 360px; }
.mkt-paso__par { display: grid; gap: var(--mkt-esp-2); grid-template-columns: repeat(2, minmax(0, 220px)); }
.mkt-paso__nota { margin: 0; font-size: 13px; color: var(--mkt-texto-suave); }
/* El aviso de cada fecha va completo debajo de su campo (en varias líneas si hace falta), sin cortarse ni encimarse. */
.mkt-paso__par :deep(.q-field--with-bottom) { padding-bottom: 0; }
.mkt-paso__par :deep(.q-field__bottom) { position: static; transform: none; padding-top: 4px; min-height: 20px; }
.mkt-paso__par :deep(.q-field__messages),
.mkt-paso__par :deep(.q-field__messages > div) { white-space: normal; overflow: visible; text-overflow: clip; line-height: 1.3; }
@media (max-width: 599px) {
    .mkt-paso__monto { max-width: none; }
    .mkt-paso__par { grid-template-columns: minmax(0, 1fr); }
}
</style>
