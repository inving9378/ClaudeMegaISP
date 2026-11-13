<template>
    <header class="mkt-encabezado">
        <div class="mkt-encabezado__identidad">
            <span v-if="icono" class="mkt-encabezado__icono" aria-hidden="true">
                <q-icon :name="icono" size="24px" />
            </span>
            <div class="mkt-encabezado__textos">
                <h1 class="mkt-encabezado__titulo">{{ titulo }}</h1>
                <p v-if="subtitulo" class="mkt-encabezado__subtitulo">{{ subtitulo }}</p>
            </div>
        </div>
        <div v-if="conSelectorEmpresa || $slots.acciones" class="mkt-encabezado__lado">
            <mkt-selector-empresa
                v-if="conSelectorEmpresa"
                ref="selector"
                @cargado="alCargarContexto"
                @error="(e) => $emit('contexto-error', e)"
            />
            <div v-if="$slots.acciones" class="mkt-encabezado__acciones">
                <slot name="acciones" :contexto="contexto" />
            </div>
        </div>
    </header>
</template>

<script>
import MktSelectorEmpresa from "./MktSelectorEmpresa.vue";

/**
 * Encabezado de pantalla de Marketing.
 * Además del contrato: reemite el contexto del selector como `contexto` / `contexto-error`
 * y lo pasa al slot `acciones` (para mostrar botones según `capacidades`).
 */
export default {
    name: "MktEncabezadoPantalla",
    components: { MktSelectorEmpresa },
    props: {
        titulo: { type: String, required: true },
        subtitulo: { type: String, default: "" },
        icono: { type: String, default: "" },
        conSelectorEmpresa: { type: Boolean, default: false },
    },
    emits: ["contexto", "contexto-error"],
    data() {
        return { contexto: null };
    },
    methods: {
        /** Vuelve a leer el contexto (p. ej. tras crear una empresa nueva). */
        recargarContexto() {
            if (this.$refs.selector) this.$refs.selector.cargar();
        },
        alCargarContexto(ctx) {
            this.contexto = ctx;
            this.$emit("contexto", ctx);
        },
    },
};
</script>

<style scoped>
.mkt-encabezado {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: var(--mkt-esp-2, 16px);
    margin-bottom: var(--mkt-esp-3, 24px);
}
.mkt-encabezado__identidad {
    display: flex;
    align-items: center;
    gap: var(--mkt-esp-2, 16px);
    min-width: 0;
    flex: 1 1 280px;
}
.mkt-encabezado__icono {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    flex: 0 0 48px;
    border-radius: 12px;
    color: #fff;
    background: var(--mkt-marca, #0066cc);
}
.mkt-encabezado__textos { min-width: 0; }
.mkt-encabezado__titulo {
    margin: 0;
    font-size: 24px;
    line-height: 32px;
    font-weight: 600;
    color: var(--mkt-texto, #343a40);
}
.mkt-encabezado__subtitulo {
    margin: 2px 0 0;
    font-size: 14px;
    line-height: 20px;
    color: var(--mkt-texto-suave, #74788d);
}
.mkt-encabezado__lado {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--mkt-esp-2, 16px);
    min-width: 0;
}
.mkt-encabezado__acciones {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--mkt-esp-1, 8px);
}
@media (max-width: 599px) {
    .mkt-encabezado__titulo { font-size: 20px; line-height: 28px; }
    .mkt-encabezado__icono { width: 40px; height: 40px; flex-basis: 40px; }
    .mkt-encabezado__lado { width: 100%; }
    .mkt-encabezado__acciones { width: 100%; }
    .mkt-encabezado__acciones :deep(.q-btn) { flex: 1 1 auto; }
}
</style>
