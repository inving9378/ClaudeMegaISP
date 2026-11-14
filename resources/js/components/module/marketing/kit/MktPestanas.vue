<template>
    <nav v-if="items.length || $slots.extra" class="mkt-pestanas" :aria-label="etiqueta">
        <div class="mkt-pestanas__fila" role="tablist">
            <template v-for="item in items" :key="item.clave">
                <a
                    v-if="item.url"
                    role="tab"
                    class="mkt-pestanas__item"
                    :class="{ 'mkt-pestanas__item--activo': item.clave === modelValue }"
                    :href="item.url"
                    :aria-selected="item.clave === modelValue ? 'true' : 'false'"
                    :aria-current="item.clave === modelValue ? 'page' : null"
                >{{ item.etiqueta }}</a>
                <button
                    v-else
                    type="button"
                    role="tab"
                    class="mkt-pestanas__item"
                    :class="{ 'mkt-pestanas__item--activo': item.clave === modelValue }"
                    :aria-selected="item.clave === modelValue ? 'true' : 'false'"
                    @click="$emit('update:modelValue', item.clave)"
                >{{ item.etiqueta }}</button>
            </template>
            <div v-if="$slots.extra" class="mkt-pestanas__extra">
                <slot name="extra" />
            </div>
        </div>
    </nav>
</template>

<script>
/**
 * Pestañas tipo carpeta, copia del marcado visual de la ficha de Cliente (`ClientCrud.vue`, `.cc-folder-tabs`):
 * una sola fila, sin íconos, la activa en índigo y unida a la línea de abajo.
 * - Con `url` cada pestaña es un enlace (lo intercepta la navegación SPA); sin `url`, un botón con v-model.
 * - Nunca muestra flechas ni se corta: si no caben, pasan a la línea siguiente (375 px).
 * - Slot `extra`: a la derecha de la fila (p. ej. un enlace a otro módulo que NO es pestaña).
 */
export default {
    name: "MktPestanas",
    props: {
        /** [{ clave, etiqueta, url? }] */
        items: { type: Array, default: () => [] },
        modelValue: { type: [String, Number], default: null },
        etiqueta: { type: String, default: "Pestañas" },
    },
    emits: ["update:modelValue"],
};
</script>

<style scoped>
.mkt-pestanas { min-width: 0; max-width: 100%; margin-bottom: 16px; }
.mkt-pestanas__fila {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 4px;
    border-bottom: 3px solid var(--mkt-carpeta, #4f46e5);
}
.mkt-pestanas__item {
    display: inline-flex;
    align-items: center;
    min-height: 36px;
    padding: 0 16px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.715em;
    letter-spacing: 0.01786em;
    white-space: nowrap;
    text-decoration: none;
    cursor: pointer;
    background: var(--mkt-carpeta-fondo, #f3f4f6);
    color: var(--mkt-carpeta-texto, #4b5563);
    border: 1px solid var(--mkt-carpeta-borde, #e5e7eb);
    border-bottom: 0;
    border-radius: 8px 8px 0 0;
}
.mkt-pestanas__item:hover { background: var(--mkt-carpeta-hover, #e0e7ff); color: var(--mkt-carpeta-hover-texto, #4338ca); }
.mkt-pestanas__item:focus-visible { outline: 2px solid var(--mkt-carpeta, #4f46e5); outline-offset: 2px; }
.mkt-pestanas__item--activo,
.mkt-pestanas__item--activo:hover {
    background: var(--mkt-carpeta, #4f46e5);
    border-color: var(--mkt-carpeta, #4f46e5);
    color: #fff;
}
.mkt-pestanas__extra {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
    padding-bottom: 4px;
}
@media (max-width: 599px) {
    .mkt-pestanas__extra { margin-left: 0; width: 100%; }
    .mkt-pestanas__item { padding: 0 12px; font-size: 13px; }
}
</style>
