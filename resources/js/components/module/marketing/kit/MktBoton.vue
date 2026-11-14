<template>
    <a
        v-if="href"
        :href="href"
        class="btn waves-effect waves-light"
        :class="clases"
        :aria-disabled="deshabilitado ? 'true' : null"
    >
        <i v-if="iconoFa" class="fa fa-fw me-1" :class="iconoFa" aria-hidden="true"></i>{{ etiqueta }}<slot />
    </a>
    <button
        v-else
        :type="submit ? 'submit' : 'button'"
        class="btn waves-effect waves-light"
        :class="clases"
        :disabled="deshabilitado || cargando"
        :aria-busy="cargando ? 'true' : 'false'"
    >
        <span v-if="cargando" class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
        <i v-else-if="iconoFa" class="fa fa-fw me-1" :class="iconoFa" aria-hidden="true"></i>{{ etiqueta }}<slot />
    </button>
</template>

<script>
/**
 * Botón del sistema (Bootstrap `.btn`, 38 px, sin mayúsculas), como los de Clientes (§2.13):
 *   crear → verde `btn-success` · guardar / acción principal → azul `btn-info` · eliminar → rojo `btn-danger`
 *   volver / cancelar → `btn-secondary` · secundario → `btn-outline-secondary` (como «Tareas» / «Soporte»)
 *   peligro-secundario → `btn-outline-danger` · enlace → `btn-link`
 * `cargando` deshabilita el botón y muestra el giro (doble clic = una sola petición). Los clics llegan por
 * el `@click` nativo (atributo heredado).
 */
const TIPOS = {
    crear: "btn-success",
    guardar: "btn-info",
    principal: "btn-info",
    eliminar: "btn-danger",
    volver: "btn-secondary",
    secundario: "btn-outline-secondary",
    "peligro-secundario": "btn-outline-danger",
    enlace: "btn-link",
};

export default {
    name: "MktBoton",
    props: {
        tipo: { type: String, default: "secundario" },
        etiqueta: { type: String, default: "" },
        iconoFa: { type: String, default: "" },
        cargando: { type: Boolean, default: false },
        deshabilitado: { type: Boolean, default: false },
        href: { type: String, default: "" },
        submit: { type: Boolean, default: false },
        chico: { type: Boolean, default: false },
    },
    computed: {
        clases() {
            return [TIPOS[this.tipo] || TIPOS.secundario, { "btn-sm": this.chico, disabled: this.href && this.deshabilitado }];
        },
    },
};
</script>
