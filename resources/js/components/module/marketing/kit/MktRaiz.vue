<template>
    <div class="mkt-raiz" :class="{ 'mkt-raiz--oscuro': oscuro, 'mkt-raiz--pantalla': !enDialogo }" :style="estiloMarca">
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
 * - Diseño unificado con Clientes (§2.13): da a sus hijos `mktTema.oscuro` (provide) para que q-card/q-table
 *   usen `:dark` igual que Clientes, y como pantalla deja el margen de `q-pa-md` del Listado de Clientes
 *   (en un diálogo, no).
 */
export default {
    name: "MktRaiz",
    props: {
        colorMarca: { type: String, default: null },
    },
    provide() {
        return { mktTema: this.tema };
    },
    data() {
        return { oscuro: false, observador: null, enDialogo: false, tema: { oscuro: false } };
    },
    computed: {
        estiloMarca() {
            const marca = esColorHex(this.colorMarca) ? this.colorMarca : MARCA_MEGANET.primario;
            return { "--mkt-marca": marca };
        },
    },
    mounted() {
        this.enDialogo = !!this.$el?.closest?.(".q-dialog, .q-dialog__inner, .mkt-dlg");
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
            this.tema.oscuro = this.oscuro;
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
    --mkt-radio: 4px;
    /* Sombra de q-card de Quasar (la tarjeta del Listado de Clientes) */
    --mkt-sombra: 0 1px 5px rgba(0, 0, 0, 0.2), 0 2px 2px rgba(0, 0, 0, 0.14), 0 3px 1px -2px rgba(0, 0, 0, 0.12);
    /* Pestañas tipo carpeta y bloques de la ficha de Cliente (ClientCrud / InformationClientCrud) */
    --mkt-carpeta: #4f46e5;
    --mkt-carpeta-fondo: #f3f4f6;
    --mkt-carpeta-texto: #4b5563;
    --mkt-carpeta-borde: #e5e7eb;
    --mkt-carpeta-hover: #e0e7ff;
    --mkt-carpeta-hover-texto: #4338ca;
    --mkt-bloque-fondo: #ffffff;
    --mkt-bloque-borde: #e5e7eb;
    --mkt-kpi-fondo: #f8fafc;
    --mkt-kpi-borde: #eef0f3;
    --mkt-kpi-valor: #334155;
    /* Avatar de iniciales (`.cic-avatar` de la ficha de Cliente) */
    --mkt-avatar-fondo: #eef2ff;
    --mkt-avatar-texto: #4f46e5;
    --mkt-fuente: "IBM Plex Sans", sans-serif;
    /* Rojo y verde de Quasar → los del sistema (Clientes: btn-danger / btn-success), solo dentro de Marketing. */
    --q-negative: #fd625e;
    --q-positive: #2ab57d;

    font-family: var(--mkt-fuente);
    color: var(--mkt-texto);
    min-width: 0;
}

.mkt-raiz--oscuro {
    /* app.min.css / bootstrap.min.css: body[data-layout-mode=dark] */
    --mkt-texto: #ced4da;
    --mkt-texto-suave: #858d98;
    /* Mismo oscuro que Clientes: q-card dark del Listado y bloques de la ficha */
    --mkt-borde: rgba(255, 255, 255, 0.28);
    --mkt-fondo-tarjeta: #1a1a1a;
    --mkt-fondo-suave: #26262c;
    --mkt-sombra: 0 1px 5px rgba(255, 255, 255, 0.2), 0 2px 2px rgba(255, 255, 255, 0.14), 0 3px 1px -2px rgba(255, 255, 255, 0.12);
    --mkt-carpeta-fondo: #26262c;
    --mkt-carpeta-texto: #9ca3af;
    --mkt-carpeta-borde: #2c2c34;
    --mkt-carpeta-hover: #312e81;
    --mkt-carpeta-hover-texto: #c7d2fe;
    --mkt-bloque-fondo: #1d1d21;
    --mkt-bloque-borde: #2c2c34;
    --mkt-kpi-fondo: #26262c;
    --mkt-kpi-borde: #2c2c34;
    --mkt-kpi-valor: #e5e7eb;
    --mkt-avatar-fondo: #312e81;
    --mkt-avatar-texto: #c7d2fe;
}
/* Margen del Listado de Clientes (`div.q-pa-md` alrededor de la tarjeta). */
.mkt-raiz--pantalla { padding: 0 16px 16px; }
@media (max-width: 599px) {
    .mkt-raiz--pantalla { padding: 0 0 16px; }
}

/* Bootstrap 5 global: `.row > * { width:100%; padding: gutter }` choca con las filas INTERNAS de Quasar
   (botones con ícono, toggles, selects, pestañas). El kit no usa `.row` para su propio acomodo (usa grid/flex
   con clases mkt-*), así que dentro de la raíz toda `.row` es de Quasar y se neutraliza. Solo aquí. */
.mkt-raiz :deep(.row) { --bs-gutter-x: 0; --bs-gutter-y: 0; }
.mkt-raiz :deep(.row > *) { width: auto; max-width: none; margin-top: 0; }
/* Bootstrap pone `flex-shrink: 0` a los hijos de .row: el campo no dejaba sitio a su sufijo («MXN») y lo empujaba fuera. */
.mkt-raiz :deep(.row > .q-field__native),
.mkt-raiz :deep(.row > .q-field__input) { width: 100%; min-width: 0; flex-shrink: 1; }
.mkt-raiz :deep(.row > .q-field__prefix),
.mkt-raiz :deep(.row > .q-field__suffix) { flex-shrink: 0; }
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
.mkt-raiz--oscuro :deep(.q-field__bottom),
.mkt-raiz--oscuro :deep(.q-field__counter) { color: var(--mkt-texto-suave); }
.mkt-raiz--oscuro :deep(.q-field--outlined .q-field__control:before) { border-color: var(--mkt-borde); }
.mkt-raiz--oscuro :deep(.q-table__card),
.mkt-raiz--oscuro :deep(.q-table) { background: var(--mkt-fondo-tarjeta); color: var(--mkt-texto); }
</style>
