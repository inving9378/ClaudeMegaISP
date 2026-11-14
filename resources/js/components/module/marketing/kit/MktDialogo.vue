<template>
    <q-dialog
        :model-value="modelValue"
        :persistent="guardando || persistente"
        :maximized="false"
        @update:model-value="(v) => $emit('update:modelValue', v)"
        @hide="$emit('hide')"
    >
        <q-card class="mkt-dialogo" :style="{ '--mkt-dialogo-ancho': anchoCss }">
            <mkt-raiz :color-marca="colorMarca">
                <header class="mkt-dialogo__cabeza">
                    <h2 class="mkt-dialogo__titulo">{{ titulo }}</h2>
                    <q-btn
                        flat
                        dense
                        round
                        icon="close"
                        :disable="guardando"
                        aria-label="Cerrar"
                        @click="$emit('update:modelValue', false)"
                    />
                </header>
                <div class="mkt-dialogo__cuerpo">
                    <slot />
                </div>
                <footer v-if="$slots.acciones" class="mkt-dialogo__acciones">
                    <slot name="acciones" />
                </footer>
            </mkt-raiz>
        </q-card>
    </q-dialog>
</template>

<script>
import MktRaiz from "./MktRaiz.vue";

/**
 * Diálogo del kit (Fase 1, contrato §2.9): q-dialog con su propia <mkt-raiz> adentro
 * (el contenido se teletransporta fuera de la pantalla y así hereda variables, modo oscuro y
 * el arreglo de Bootstrap). Mientras `guardando` no se puede cerrar.
 */
export default {
    name: "MktDialogo",
    components: { MktRaiz },
    props: {
        modelValue: { type: Boolean, default: false },
        titulo: { type: String, required: true },
        guardando: { type: Boolean, default: false },
        persistente: { type: Boolean, default: false },
        ancho: { type: [Number, String], default: 560 },
        colorMarca: { type: String, default: null },
    },
    emits: ["update:modelValue", "hide"],
    computed: {
        anchoCss() {
            return typeof this.ancho === "number" ? `${this.ancho}px` : this.ancho;
        },
    },
};
</script>

<style scoped>
.mkt-dialogo {
    width: var(--mkt-dialogo-ancho, 560px);
    max-width: calc(100vw - 32px) !important;
    border-radius: 8px;
    overflow: hidden;
}
.mkt-dialogo :deep(.mkt-raiz) {
    background: var(--mkt-fondo-tarjeta);
    border-top: 3px solid var(--mkt-marca);
}
.mkt-dialogo__cabeza {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--mkt-esp-2, 16px);
    padding: var(--mkt-esp-2, 16px) var(--mkt-esp-3, 24px);
    border-bottom: 1px solid var(--mkt-borde, #e9e9ef);
}
.mkt-dialogo__titulo {
    margin: 0;
    font-size: 16px;
    line-height: 24px;
    font-weight: 600;
    color: var(--mkt-texto, #343a40);
}
.mkt-dialogo__cuerpo {
    display: flex;
    flex-direction: column;
    gap: var(--mkt-esp-1, 8px); /* los q-input ya reservan su renglón de error */
    padding: var(--mkt-esp-3, 24px);
    max-height: calc(100vh - 200px);
    overflow-y: auto;
}
.mkt-dialogo__acciones {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: var(--mkt-esp-1, 8px);
    padding: var(--mkt-esp-2, 16px) var(--mkt-esp-3, 24px);
    border-top: 1px solid var(--mkt-borde, #e9e9ef);
}
@media (max-width: 599px) {
    .mkt-dialogo__cabeza,
    .mkt-dialogo__cuerpo,
    .mkt-dialogo__acciones { padding-left: var(--mkt-esp-2, 16px); padding-right: var(--mkt-esp-2, 16px); }
    .mkt-dialogo__acciones :deep(.q-btn) { flex: 1 1 100%; }
}
</style>
