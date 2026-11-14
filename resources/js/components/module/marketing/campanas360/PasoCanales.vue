<template>
    <div class="mkt-paso">
        <p class="mkt-paso__intro">Elige dónde va a vivir la campaña. Por cada canal la IA prepara sus piezas; todas se pueden editar y regenerar después.</p>
        <div class="mkt-paso__canales" role="group" aria-label="Canales de la campaña">
            <label
                v-for="c in canales"
                :key="c.value"
                class="mkt-paso__canal"
                :class="{ 'mkt-paso__canal--activo': elegido(c.value) }"
            >
                <input
                    type="checkbox"
                    class="mkt-paso__check"
                    :checked="elegido(c.value)"
                    @change="alternar(c.value)"
                />
                <span class="mkt-paso__canal-icono" aria-hidden="true"><q-icon :name="c.icono" size="22px" /></span>
                <span class="mkt-paso__canal-textos">
                    <span class="mkt-paso__canal-titulo">{{ c.label }}</span>
                    <span class="mkt-paso__canal-ayuda">{{ c.genera }}</span>
                </span>
                <q-icon :name="elegido(c.value) ? 'check_box' : 'check_box_outline_blank'" size="22px" class="mkt-paso__canal-check" aria-hidden="true" />
            </label>
        </div>
        <div v-if="errorCanales" class="mkt-paso__error" role="alert">{{ errorCanales }}</div>
        <q-banner v-if="elegido('whatsapp')" dense rounded class="mkt-paso__aviso">
            <template #avatar><q-icon name="info" size="20px" /></template>
            {{ avisoWhatsapp }}
        </q-banner>
    </div>
</template>

<script>
import { CANALES, AVISO_WHATSAPP } from "./catalogos360";

/** Asistente 360 · paso 4: canales (§2.5.2 y §2.5.3). */
export default {
    name: "PasoCanales",
    props: {
        modelValue: { type: Object, required: true },
        errores: { type: Object, default: () => ({}) },
    },
    emits: ["update:modelValue"],
    data() {
        return { canales: CANALES, avisoWhatsapp: AVISO_WHATSAPP };
    },
    computed: {
        lista() {
            return Array.isArray(this.modelValue.canales) ? this.modelValue.canales : [];
        },
        errorCanales() {
            if (this.errores.canales) return this.errores.canales;
            const k = Object.keys(this.errores).find((x) => x.startsWith("canales."));
            return k ? this.errores[k] : "";
        },
    },
    methods: {
        elegido(v) {
            return this.lista.includes(v);
        },
        alternar(v) {
            const nueva = this.elegido(v) ? this.lista.filter((x) => x !== v) : [...this.lista, v];
            // orden estable = orden del catálogo
            const ordenada = CANALES.map((c) => c.value).filter((x) => nueva.includes(x));
            this.$emit("update:modelValue", { ...this.modelValue, canales: ordenada });
        },
    },
};
</script>

<style scoped>
.mkt-paso { display: flex; flex-direction: column; gap: var(--mkt-esp-2); }
.mkt-paso__intro { margin: 0; font-size: 14px; color: var(--mkt-texto-suave); }
.mkt-paso__canales { display: grid; gap: var(--mkt-esp-1); grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
.mkt-paso__canal {
    position: relative;
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
    padding: 12px;
    border: 1px solid var(--mkt-borde);
    border-radius: var(--mkt-radio);
    background: var(--mkt-fondo-tarjeta);
    cursor: pointer;
    transition: border-color 0.15s ease, background-color 0.15s ease;
}
.mkt-paso__canal:hover { border-color: var(--mkt-primario); }
.mkt-paso__canal:focus-within { outline: 2px solid var(--mkt-primario); outline-offset: 2px; }
.mkt-paso__canal--activo { border-color: var(--mkt-primario); background: color-mix(in srgb, var(--mkt-primario) 6%, var(--mkt-fondo-tarjeta)); }
.mkt-paso__check { position: absolute; opacity: 0; width: 1px; height: 1px; }
.mkt-paso__canal-icono {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; flex: 0 0 40px; border-radius: 8px;
    color: var(--mkt-primario); background: color-mix(in srgb, var(--mkt-primario) 10%, transparent);
}
.mkt-paso__canal-textos { display: flex; flex-direction: column; min-width: 0; flex: 1 1 auto; }
.mkt-paso__canal-titulo { font-weight: 600; font-size: 14px; color: var(--mkt-texto); }
.mkt-paso__canal-ayuda { font-size: 13px; color: var(--mkt-texto-suave); }
.mkt-paso__canal-check { color: var(--mkt-primario); }
.mkt-paso__error { font-size: 13px; color: var(--mkt-peligro); }
.mkt-paso__aviso {
    background: color-mix(in srgb, var(--mkt-info) 10%, var(--mkt-fondo-tarjeta));
    color: var(--mkt-texto);
    border: 1px solid color-mix(in srgb, var(--mkt-info) 35%, transparent);
    font-size: 13px;
}
@media (max-width: 599px) {
    .mkt-paso__canales { grid-template-columns: minmax(0, 1fr); }
}
@media (prefers-reduced-motion: reduce) {
    .mkt-paso__canal { transition: none; }
}
</style>
