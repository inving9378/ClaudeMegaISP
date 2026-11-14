<template>
    <q-btn
        flat
        round
        dense
        icon="more_vert"
        class="mkt-menu-tabla"
        :dark="tema.oscuro"
        aria-label="Acciones de la tabla"
    >
        <q-menu anchor="bottom left" self="top left" :dark="tema.oscuro">
            <q-list style="min-width: 220px">
                <q-item v-if="conActualizar" clickable v-close-popup @click="$emit('actualizar')">
                    <q-item-section avatar><q-icon name="refresh" /></q-item-section>
                    <q-item-section>Actualizar</q-item-section>
                </q-item>
                <q-item clickable v-close-popup @click="$emit('expandir')">
                    <q-item-section avatar><q-icon name="fullscreen" /></q-item-section>
                    <q-item-section>{{ enPantallaCompleta ? "Salir de pantalla completa" : "Expandir pantalla" }}</q-item-section>
                </q-item>
                <template v-if="columnas.length > 1">
                    <q-separator />
                    <q-item-label header class="q-py-xs">Ver columnas</q-item-label>
                    <q-item v-for="col in columnas" :key="col.name" clickable dense @click="alternar(col.name)">
                        <q-item-section avatar>
                            <q-icon :name="ocultas.includes(col.name) ? 'check_box_outline_blank' : 'check_box'" />
                        </q-item-section>
                        <q-item-section>{{ col.label }}</q-item-section>
                    </q-item>
                </template>
            </q-list>
        </q-menu>
    </q-btn>
</template>

<script>
/**
 * Menú de tres puntos de una tabla, como el del Listado de Clientes (Actualizar · Expandir · Ver columnas).
 * Lo usa `mkt-tabla-ejecutiva` arriba a la derecha; una pantalla lo puede poner en la fila del título
 * (slot `#acciones` del encabezado) atándolo a su tabla con `v-model:ocultas` y `@expandir="$refs.tabla.expandir()"`.
 */
export default {
    name: "MktMenuTabla",
    inject: { tema: { from: "mktTema", default: () => ({ oscuro: false }) } },
    props: {
        /** Columnas con etiqueta (las que se pueden ocultar). */
        columnas: { type: Array, default: () => [] },
        ocultas: { type: Array, default: () => [] },
        conActualizar: { type: Boolean, default: false },
        enPantallaCompleta: { type: Boolean, default: false },
    },
    emits: ["update:ocultas", "actualizar", "expandir"],
    methods: {
        alternar(nombre) {
            this.$emit("update:ocultas", this.ocultas.includes(nombre)
                ? this.ocultas.filter((n) => n !== nombre)
                : [...this.ocultas, nombre]);
        },
    },
};
</script>
