<template>
    <section class="mkt-panel" :aria-busy="cargando ? 'true' : 'false'">
        <header v-if="titulo || $slots.acciones" class="mkt-panel__cabeza">
            <div class="mkt-panel__titulos">
                <h2 v-if="titulo" class="mkt-panel__titulo">{{ titulo }}</h2>
                <p v-if="subtitulo" class="mkt-panel__subtitulo">{{ subtitulo }}</p>
            </div>
            <div v-if="$slots.acciones" class="mkt-panel__acciones">
                <slot name="acciones" />
            </div>
        </header>
        <div class="mkt-panel__cuerpo">
            <div v-if="cargando" class="mkt-panel__cargando">
                <q-skeleton type="text" width="90%" />
                <q-skeleton type="text" width="75%" />
                <q-skeleton type="text" width="60%" />
            </div>
            <slot v-else />
        </div>
        <footer v-if="$slots.pie" class="mkt-panel__pie">
            <slot name="pie" />
        </footer>
    </section>
</template>

<script>
export default {
    name: "MktTarjetaPanel",
    props: {
        titulo: { type: String, default: "" },
        subtitulo: { type: String, default: "" },
        cargando: { type: Boolean, default: false },
    },
};
</script>

<style scoped>
.mkt-panel {
    background: var(--mkt-fondo-tarjeta, #fff);
    border: 1px solid var(--mkt-borde, #e9e9ef);
    border-radius: var(--mkt-radio, 8px);
    box-shadow: var(--mkt-sombra, none);
    min-width: 0;
}
.mkt-panel__cabeza {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: var(--mkt-esp-2, 16px);
    padding: var(--mkt-esp-2, 16px) var(--mkt-esp-3, 24px);
    border-bottom: 1px solid var(--mkt-borde, #e9e9ef);
}
.mkt-panel__titulos { min-width: 0; flex: 1 1 220px; }
.mkt-panel__titulo {
    margin: 0;
    font-size: 16px;
    line-height: 24px;
    font-weight: 600;
    color: var(--mkt-texto, #343a40);
}
.mkt-panel__subtitulo {
    margin: 2px 0 0;
    font-size: 13px;
    color: var(--mkt-texto-suave, #74788d);
}
.mkt-panel__acciones {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--mkt-esp-1, 8px);
}
.mkt-panel__cuerpo { padding: var(--mkt-esp-2, 16px) var(--mkt-esp-3, 24px); min-width: 0; }
.mkt-panel__cargando { display: flex; flex-direction: column; gap: var(--mkt-esp-1, 8px); }
.mkt-panel__pie {
    padding: var(--mkt-esp-1, 8px) var(--mkt-esp-3, 24px);
    border-top: 1px solid var(--mkt-borde, #e9e9ef);
    font-size: 13px;
    color: var(--mkt-texto-suave, #74788d);
}
@media (max-width: 599px) {
    .mkt-panel__cabeza,
    .mkt-panel__cuerpo { padding-left: var(--mkt-esp-2, 16px); padding-right: var(--mkt-esp-2, 16px); }
    .mkt-panel__pie { padding-left: var(--mkt-esp-2, 16px); padding-right: var(--mkt-esp-2, 16px); }
}
</style>
