<template>
    <div class="mkt-vacio" role="status">
        <div class="mkt-vacio__icono" aria-hidden="true">
            <q-icon :name="icono" size="28px" />
        </div>
        <div class="mkt-vacio__titulo">{{ titulo }}</div>
        <p v-if="texto" class="mkt-vacio__texto">{{ texto }}</p>
        <div v-if="$slots.accion" class="mkt-vacio__accion">
            <slot name="accion" />
        </div>
    </div>
</template>

<script>
export default {
    name: "MktEstadoVacio",
    props: {
        icono: { type: String, default: "inbox" },
        titulo: { type: String, required: true },
        texto: { type: String, default: "" },
    },
};
</script>

<style scoped>
.mkt-vacio {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: var(--mkt-esp-4, 32px) var(--mkt-esp-2, 16px);
    gap: var(--mkt-esp-1, 8px);
}
.mkt-vacio__icono {
    width: 56px;
    height: 56px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--mkt-primario, #5156be);
    background: color-mix(in srgb, var(--mkt-primario, #5156be) 10%, transparent);
    margin-bottom: var(--mkt-esp-1, 8px);
}
.mkt-vacio__titulo {
    font-size: 16px;
    font-weight: 600;
    color: var(--mkt-texto, #343a40);
}
.mkt-vacio__texto {
    margin: 0;
    max-width: 44ch;
    font-size: 14px;
    line-height: 1.5;
    color: var(--mkt-texto-suave, #74788d);
}
.mkt-vacio__accion {
    margin-top: var(--mkt-esp-2, 16px);
}
</style>
