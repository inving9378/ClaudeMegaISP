<template>
    <section
        class="mkt-panel"
        :class="[tonoValido ? ['mkt-panel--bloque', `mkt-panel--${tonoValido}`] : null, { 'mkt-panel--oscuro': tema.oscuro }]"
        :aria-busy="cargando ? 'true' : 'false'"
    >
        <header v-if="titulo || $slots.acciones" class="mkt-panel__cabeza">
            <div class="mkt-panel__titulos">
                <h2 v-if="titulo" class="mkt-panel__titulo">
                    <i v-if="iconoFa" class="fa fa-fw mkt-panel__icono" :class="iconoFa" aria-hidden="true"></i>
                    <span>{{ titulo }}</span>
                </h2>
                <p v-if="subtitulo" class="mkt-panel__subtitulo">{{ subtitulo }}</p>
            </div>
            <div v-if="$slots.acciones" class="mkt-panel__acciones">
                <slot name="acciones" />
            </div>
        </header>
        <div class="mkt-panel__cuerpo" :class="{ 'mkt-panel__cuerpo--sin-relleno': sinRelleno }">
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
/** Tonos de los bloques de la ficha de Cliente (InformationClientCrud.vue, `.cic-card--*`). */
const TONOS = ["lila", "azul", "verde", "crema", "oliva", "salmon", "rosa", "gris"];

/**
 * Tarjeta de Marketing (diseño unificado con Clientes, §2.13).
 * - Sin `tono`: igual que la tarjeta del Listado de Clientes (q-card: esquinas de 4 px, sombra de Quasar).
 * - Con `tono`: bloque de la ficha de Cliente (esquinas de 12 px, cabecera de color con ícono FA5 opcional).
 */
export default {
    name: "MktTarjetaPanel",
    inject: { tema: { from: "mktTema", default: () => ({ oscuro: false }) } },
    props: {
        titulo: { type: String, default: "" },
        subtitulo: { type: String, default: "" },
        cargando: { type: Boolean, default: false },
        /** lila · azul · verde · crema · oliva · salmon · rosa · gris */
        tono: { type: String, default: "" },
        /** Clase de Font Awesome 5 para la cabecera, p. ej. "fa-bullseye" (solo con `tono`). */
        iconoFa: { type: String, default: "" },
        /** Cuerpo sin relleno (p. ej. una tabla que llega al borde). */
        sinRelleno: { type: Boolean, default: false },
    },
    computed: {
        tonoValido() {
            return TONOS.includes(this.tono) ? this.tono : "";
        },
    },
};
</script>

<style scoped>
.mkt-panel {
    background: var(--mkt-fondo-tarjeta, #fff);
    border-radius: var(--mkt-radio, 4px);
    box-shadow: var(--mkt-sombra, none);
    min-width: 0;
}
.mkt-panel__cabeza {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px 16px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--mkt-borde, #e9e9ef);
}
.mkt-panel__titulos { min-width: 0; flex: 1 1 220px; }
.mkt-panel__titulo {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 0.92rem;
    line-height: 1.5;
    font-weight: 700;
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
    gap: 8px;
}
.mkt-panel__cuerpo { padding: 16px; min-width: 0; }
.mkt-panel__cuerpo--sin-relleno { padding: 0; }
.mkt-panel__cargando { display: flex; flex-direction: column; gap: 8px; }
.mkt-panel__pie {
    padding: 8px 16px;
    border-top: 1px solid var(--mkt-borde, #e9e9ef);
    font-size: 13px;
    color: var(--mkt-texto-suave, #74788d);
}

/* ── Bloque de ficha (copia de .cic-card / .cic-card-head de la ficha de Cliente) ── */
.mkt-panel--bloque {
    background: var(--mkt-bloque-fondo, #fff);
    border: 1px solid var(--mkt-bloque-borde, #e5e7eb);
    border-radius: 12px;
    box-shadow: none;
    overflow: hidden;
}
.mkt-panel--bloque .mkt-panel__cabeza { border-bottom: 1px solid #f1f3f5; }
.mkt-panel--lila   .mkt-panel__cabeza { background: #EEEDFE; }
.mkt-panel--azul   .mkt-panel__cabeza { background: #E6F1FB; }
.mkt-panel--verde  .mkt-panel__cabeza { background: #E1F5EE; }
.mkt-panel--crema  .mkt-panel__cabeza { background: #FAEEDA; }
.mkt-panel--oliva  .mkt-panel__cabeza { background: #EAF3DE; }
.mkt-panel--salmon .mkt-panel__cabeza { background: #FAECE7; }
.mkt-panel--rosa   .mkt-panel__cabeza { background: #FBEAF0; }
.mkt-panel--gris   .mkt-panel__cabeza { background: #F1EFE8; }
.mkt-panel--lila   .mkt-panel__titulo { color: #3C3489; }
.mkt-panel--azul   .mkt-panel__titulo { color: #0C447C; }
.mkt-panel--verde  .mkt-panel__titulo { color: #085041; }
.mkt-panel--crema  .mkt-panel__titulo { color: #633806; }
.mkt-panel--oliva  .mkt-panel__titulo { color: #27500A; }
.mkt-panel--salmon .mkt-panel__titulo { color: #712B13; }
.mkt-panel--rosa   .mkt-panel__titulo { color: #72243E; }
.mkt-panel--gris   .mkt-panel__titulo { color: #2C2C2A; }
.mkt-panel__icono { font-size: 0.85rem; }

/* Oscuro (igual que .cic-dark; `tema` viene de MktRaiz): cabecera gris oscura y línea de color abajo del bloque. */
.mkt-panel--oscuro.mkt-panel--bloque .mkt-panel__cabeza { background: #26262c; border-color: #2c2c34; }
.mkt-panel--oscuro.mkt-panel--bloque .mkt-panel__titulo { color: #e5e7eb; }
.mkt-panel--oscuro.mkt-panel--lila   { border-bottom: 3px solid #7F77DD; }
.mkt-panel--oscuro.mkt-panel--azul   { border-bottom: 3px solid #378ADD; }
.mkt-panel--oscuro.mkt-panel--verde  { border-bottom: 3px solid #1D9E75; }
.mkt-panel--oscuro.mkt-panel--crema  { border-bottom: 3px solid #EF9F27; }
.mkt-panel--oscuro.mkt-panel--oliva  { border-bottom: 3px solid #639922; }
.mkt-panel--oscuro.mkt-panel--salmon { border-bottom: 3px solid #D85A30; }
.mkt-panel--oscuro.mkt-panel--rosa   { border-bottom: 3px solid #D4537E; }
.mkt-panel--oscuro.mkt-panel--gris   { border-bottom: 3px solid #888780; }
.mkt-panel--oscuro.mkt-panel--lila   .mkt-panel__icono { color: #AFA9EC; }
.mkt-panel--oscuro.mkt-panel--azul   .mkt-panel__icono { color: #85B7EB; }
.mkt-panel--oscuro.mkt-panel--verde  .mkt-panel__icono { color: #5DCAA5; }
.mkt-panel--oscuro.mkt-panel--crema  .mkt-panel__icono { color: #FAC775; }
.mkt-panel--oscuro.mkt-panel--oliva  .mkt-panel__icono { color: #97C459; }
.mkt-panel--oscuro.mkt-panel--salmon .mkt-panel__icono { color: #F0997B; }
.mkt-panel--oscuro.mkt-panel--rosa   .mkt-panel__icono { color: #ED93B1; }
.mkt-panel--oscuro.mkt-panel--gris   .mkt-panel__icono { color: #B4B2A9; }
@media (max-width: 599px) {
    .mkt-panel__cabeza,
    .mkt-panel__cuerpo,
    .mkt-panel__pie { padding-left: 12px; padding-right: 12px; }
    .mkt-panel__cuerpo--sin-relleno { padding: 0; }
}
</style>
