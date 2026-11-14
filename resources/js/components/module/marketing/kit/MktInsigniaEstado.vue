<template>
    <span class="estado-chip mkt-insignia" :class="`estado-tono--${tonoCliente}`">{{ texto }}</span>
</template>

<script>
const TONOS = ["exito", "aviso", "peligro", "info", "neutro", "primario"];

/**
 * Insignia de estado = el chip de estado de la ficha de Cliente (`.estado-chip` + `.estado-tono--*`, globales del
 * sistema en resources/sass/app.scss, item #488): fondo pastel, texto oscuro del matiz y punto de color; en oscuro
 * toma los tonos oscuros del sistema. Sin colores propios.
 *   exito → Activo (verde) · peligro → Bloqueado (rojo) · aviso → Inactivo (ámbar)
 *   info / primario → Nuevo (azul) · neutro → Cancelado (gris)
 */
const MAPA = { exito: "activo", peligro: "bloqueado", aviso: "inactivo", info: "nuevo", primario: "nuevo", neutro: "cancelado" };

export default {
    name: "MktInsigniaEstado",
    props: {
        texto: { type: String, required: true },
        tono: { type: String, default: "neutro" },
    },
    computed: {
        tonoCliente() {
            return MAPA[TONOS.includes(this.tono) ? this.tono : "neutro"];
        },
    },
};
</script>

<style scoped>
/* En tablas y tarjetas el chip va a 12 px (en la ficha de Cliente hereda 0.75em del título). */
.mkt-insignia { font-size: 12px; white-space: nowrap; }
</style>
