<template>
    <div class="mkt-raiz" :class="{ 'mkt-raiz--oscuro': oscuro }" :style="estiloMarca">
        <slot />
    </div>
</template>

<script>
import { esColorHex, MARCA_MEGANET } from "./tokens";

/**
 * Raíz de toda pantalla (y diálogo) de Marketing IA.
 * - Define las variables --mkt-* (nunca en :root).
 * - Sigue el modo oscuro del sistema observando body[data-layout-mode] (MutationObserver, sin :global).
 * - Neutraliza la regla global de Bootstrap `.row > *` SOLO dentro de sí misma.
 */
export default {
    name: "MktRaiz",
    props: {
        colorMarca: { type: String, default: null },
    },
    data() {
        return { oscuro: false, observador: null };
    },
    computed: {
        estiloMarca() {
            const marca = esColorHex(this.colorMarca) ? this.colorMarca : MARCA_MEGANET.primario;
            return { "--mkt-marca": marca };
        },
    },
    mounted() {
        this.leerModo();
        if (typeof MutationObserver !== "undefined") {
            this.observador = new MutationObserver(() => this.leerModo());
            this.observador.observe(document.body, { attributes: true, attributeFilter: ["data-layout-mode"] });
        }
    },
    beforeUnmount() {
        if (this.observador) this.observador.disconnect();
    },
    methods: {
        leerModo() {
            this.oscuro = document.body.getAttribute("data-layout-mode") === "dark";
        },
    },
};
</script>

<style scoped>
.mkt-raiz {
    /* Paleta del sistema LM (bootstrap.min.css servido) */
    --mkt-primario: #5156be;
    --mkt-exito: #2ab57d;
    --mkt-info: #4ba6ef;
    --mkt-aviso: #ffbf53;
    --mkt-peligro: #fd625e;
    --mkt-neutro: #74788d;
    --mkt-borde: #e9e9ef;
    --mkt-texto: #343a40;
    --mkt-texto-suave: #74788d;
    --mkt-fondo-tarjeta: #ffffff;
    --mkt-fondo-suave: #f8f8fb;
    /* Escala de espacio (múltiplos de 8 px) y forma */
    --mkt-esp-1: 8px;
    --mkt-esp-2: 16px;
    --mkt-esp-3: 24px;
    --mkt-esp-4: 32px;
    --mkt-radio: 8px;
    --mkt-sombra: 0 1px 2px rgba(52, 58, 64, 0.06), 0 2px 8px rgba(52, 58, 64, 0.04);
    --mkt-fuente: "IBM Plex Sans", sans-serif;

    font-family: var(--mkt-fuente);
    color: var(--mkt-texto);
    min-width: 0;
}

.mkt-raiz--oscuro {
    /* app.min.css / bootstrap.min.css: body[data-layout-mode=dark] */
    --mkt-borde: #3b403d;
    --mkt-texto: #ced4da;
    --mkt-texto-suave: #858d98;
    --mkt-fondo-tarjeta: #313533;
    --mkt-fondo-suave: #2a2e2c;
    --mkt-sombra: none;
}

/* Bootstrap 5 global: `.row > * { width:100%; padding: gutter }` choca con las filas INTERNAS de Quasar
   (botones con ícono, toggles, selects, pestañas). El kit no usa `.row` para su propio acomodo (usa grid/flex
   con clases mkt-*), así que dentro de la raíz toda `.row` es de Quasar y se neutraliza. Solo aquí. */
.mkt-raiz :deep(.row) { --bs-gutter-x: 0; --bs-gutter-y: 0; }
.mkt-raiz :deep(.row > *) { width: auto; max-width: none; margin-top: 0; }
.mkt-raiz :deep(.row > .q-field__native),
.mkt-raiz :deep(.row > .q-field__input) { width: 100%; }
/* Bootstrap también fija el padding de los hijos de .row: se devuelven los de Quasar (quasar.prod.css). */
.mkt-raiz :deep(.q-tab) { padding: 0 16px; }
.mkt-raiz :deep(.row > .q-btn) { padding: 4px 16px; }
.mkt-raiz :deep(.row > .q-btn--dense) { padding: 0.285em; }
.mkt-raiz :deep(.row > .q-btn--round) { padding: 0; }
.mkt-raiz :deep(.q-toggle__inner) { padding: 0.325em 0.3em; }
.mkt-raiz :deep(.q-toggle--dense .q-toggle__inner) { padding: 0.07625em 0; }
.mkt-raiz :deep(.q-field__prepend) { padding-right: 12px; }
.mkt-raiz :deep(.q-field__append) { padding-left: 12px; }
.mkt-raiz :deep(.q-item__section--side) { padding-left: 16px; padding-right: 0; }
.mkt-raiz :deep(.q-item__section--avatar) { min-width: 40px; padding-right: 16px; padding-left: 0; }

/* Campos Quasar legibles en modo oscuro (solo dentro de Marketing). */
.mkt-raiz--oscuro :deep(.q-field__native),
.mkt-raiz--oscuro :deep(.q-field__input),
.mkt-raiz--oscuro :deep(.q-field__label),
.mkt-raiz--oscuro :deep(.q-field__marginal) { color: var(--mkt-texto); }
.mkt-raiz--oscuro :deep(.q-field--outlined .q-field__control:before) { border-color: var(--mkt-borde); }
.mkt-raiz--oscuro :deep(.q-table__card),
.mkt-raiz--oscuro :deep(.q-table) { background: var(--mkt-fondo-tarjeta); color: var(--mkt-texto); }
</style>
