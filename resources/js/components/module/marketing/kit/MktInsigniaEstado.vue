<template>
    <span class="mkt-insignia" :class="`mkt-insignia--${tonoValido}`">
        <span class="mkt-insignia__punto" aria-hidden="true"></span>{{ texto }}
    </span>
</template>

<script>
const TONOS = ["exito", "aviso", "peligro", "info", "neutro", "primario"];

export default {
    name: "MktInsigniaEstado",
    props: {
        texto: { type: String, required: true },
        tono: { type: String, default: "neutro" },
    },
    computed: {
        tonoValido() {
            return TONOS.includes(this.tono) ? this.tono : "neutro";
        },
    },
};
</script>

<style scoped>
.mkt-insignia {
    --tono: var(--mkt-neutro, #74788d);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 500;
    line-height: 20px;
    white-space: nowrap;
    color: var(--tono);
    background: color-mix(in srgb, var(--tono) 14%, transparent);
}
.mkt-insignia__punto {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
}
.mkt-insignia--exito { --tono: var(--mkt-exito, #2ab57d); }
.mkt-insignia--aviso { --tono: #c98a14; } /* #ffbf53 sobre blanco no da contraste de texto: se oscurece */
.mkt-insignia--peligro { --tono: var(--mkt-peligro, #fd625e); }
.mkt-insignia--info { --tono: #2f8fdc; }
.mkt-insignia--neutro { --tono: var(--mkt-neutro, #74788d); }
.mkt-insignia--primario { --tono: var(--mkt-primario, #5156be); }
</style>
