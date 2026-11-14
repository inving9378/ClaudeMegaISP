<template>
    <div class="mkt-encabezado">
        <!-- Un solo grupo de pestañas (las de la sección activa), arriba de la tarjeta como en la ficha de Cliente. -->
        <mkt-navegacion-modulo v-if="conPestanas" />
        <q-card class="mkt-encabezado__tarjeta" :dark="tema.oscuro">
            <div class="mkt-encabezado__fila">
                <div class="mkt-encabezado__izquierda">
                    <h1 class="text-h6 mkt-encabezado__titulo">{{ titulo }}</h1>
                    <div v-if="$slots.filtros" class="mkt-encabezado__filtros">
                        <slot name="filtros" :contexto="contexto" />
                    </div>
                </div>
                <div v-if="(conSelectorEmpresa && mostrarSelector) || $slots.acciones" class="mkt-encabezado__lado">
                    <!-- Cambio de alcance (Henry, 2026-11-13): solo Meganet. El selector sigue cargando el contexto
                         (capacidades, color) pero no se muestra salvo `mostrar-selector`. -->
                    <div v-if="$slots.acciones" class="mkt-encabezado__acciones">
                        <slot name="acciones" :contexto="contexto" />
                    </div>
                </div>
                <mkt-selector-empresa
                    v-if="conSelectorEmpresa"
                    v-show="mostrarSelector"
                    ref="selector"
                    @cargado="alCargarContexto"
                    @error="(e) => $emit('contexto-error', e)"
                />
            </div>
            <div v-if="$slots.default" class="mkt-encabezado__cuerpo">
                <slot :contexto="contexto" />
            </div>
        </q-card>
    </div>
</template>

<script>
import MktSelectorEmpresa from "./MktSelectorEmpresa.vue";
import MktNavegacionModulo from "./MktNavegacionModulo.vue";

/**
 * Encabezado de pantalla de Marketing — diseño unificado con el Listado de Clientes (§2.13):
 * pestañas de la sección (si tiene) → UNA tarjeta (q-card) con el título `text-h6` (20 px / 500, sin ícono),
 * los filtros en la MISMA fila (slot `filtros`) y las acciones a la derecha (slot `acciones`).
 * El slot por defecto es el cuerpo de esa misma tarjeta (la tabla de un listado va ahí, como en Clientes).
 *
 * Se conservan `subtitulo` e `icono` por compatibilidad, pero no se pintan (Clientes no lleva ni uno ni otro).
 * Además del contrato: reemite el contexto del selector como `contexto` / `contexto-error`
 * y lo pasa a los slots (para mostrar botones según `capacidades`).
 */
export default {
    name: "MktEncabezadoPantalla",
    components: { MktSelectorEmpresa, MktNavegacionModulo },
    inject: { tema: { from: "mktTema", default: () => ({ oscuro: false }) } },
    props: {
        titulo: { type: String, required: true },
        subtitulo: { type: String, default: "" },
        icono: { type: String, default: "" },
        conSelectorEmpresa: { type: Boolean, default: false },
        /** Fase 1 (solo Meganet): el selector carga el contexto pero queda oculto salvo que se pida. */
        mostrarSelector: { type: Boolean, default: false },
        /** Pinta arriba las pestañas de la sección activa (sub-secciones del menú). */
        conPestanas: { type: Boolean, default: true },
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
.mkt-encabezado { min-width: 0; margin-bottom: 16px; }
.mkt-encabezado__tarjeta { border-radius: 4px; min-width: 0; }
/* Fila de la tarjeta = `q-table__top` del Listado de Clientes (12px 16px, dentro de q-pa-sm). */
.mkt-encabezado__fila {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px 16px;
    padding: 20px 24px 12px;
}
.mkt-encabezado__izquierda {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    /* base 0: los filtros se acomodan en otra línea DENTRO de este bloque y las acciones se quedan arriba a la derecha */
    flex: 1 1 0;
    min-width: 0;
}
.mkt-encabezado__titulo {
    margin: 0 8px 0 0;
    white-space: nowrap;
    color: inherit;
}
.mkt-encabezado__filtros {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    /* Se queda en la fila del título; si no caben, los filtros pasan a otra línea DEBAJO de los filtros. */
    flex: 1 1 0;
    min-width: 0;
}
/* Campos de filtro con ancho fijo como los de Clientes (select-filter ~200 px). */
.mkt-encabezado__filtros :deep(.q-field) { width: 200px; max-width: 100%; }
.mkt-encabezado__filtros :deep(.q-field.mkt-filtro--busqueda) { width: 240px; }
.mkt-encabezado__filtros :deep(.q-field.mkt-filtro--corto) { width: 150px; }
.mkt-encabezado__lado {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-left: auto;
    min-width: 0;
}
.mkt-encabezado__acciones {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.mkt-encabezado__cuerpo { padding: 0 8px 8px; min-width: 0; }
@media (max-width: 599px) {
    .mkt-encabezado__fila { padding: 16px 16px 12px; }
    .mkt-encabezado__titulo { white-space: normal; }
    .mkt-encabezado__filtros,
    .mkt-encabezado__filtros :deep(.q-field),
    .mkt-encabezado__filtros :deep(.q-field.mkt-filtro--busqueda),
    .mkt-encabezado__filtros :deep(.q-field.mkt-filtro--corto) { width: 100%; }
    .mkt-encabezado__izquierda { flex-basis: 100%; }
    .mkt-encabezado__filtros { flex-basis: 100%; }
    .mkt-encabezado__lado,
    .mkt-encabezado__acciones { width: 100%; }
    .mkt-encabezado__acciones > :deep(*) { flex: 1 1 auto; }
}
</style>
