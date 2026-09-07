<template>
    <q-dialog v-model="dialog" @hide="onHide">
        <q-card style="width: 640px; max-width: 95vw">
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section>
                        <div class="text-h6">
                            Empalmes {{ object?.name ? `— ${object.name}` : "" }}
                        </div>
                    </q-item-section>
                    <q-item-section avatar class="row items-center no-wrap q-gutter-xs">
                        <q-btn
                            v-if="object?.id"
                            no-caps
                            flat
                            dense
                            icon="description"
                            label="Carta de empalme"
                            @click="cartaAbierta = true"
                        />
                        <q-btn icon="close" flat round dense @click="dialog = false" />
                    </q-item-section>
                </q-item>
            </q-card-section>

            <q-separator />

            <q-card-section>
                <empalmes-panel
                    v-if="object?.id"
                    :key="object.id"
                    :elemento-contenedor-type="MAPA_RED_LAYER_MODEL"
                    :elemento-contenedor-id="object.id"
                />
            </q-card-section>
        </q-card>
    </q-dialog>

    <carta-empalme-dialog
        v-if="object?.id"
        v-model="cartaAbierta"
        :elemento-contenedor-type="MAPA_RED_LAYER_MODEL"
        :elemento-contenedor-id="object.id"
        :nombre="object?.name"
    />
</template>

<script setup>
// MR-12 Fase B1 (item roadmap #9990520) — modal de uniones de hilos, enganchado desde
// JunctionBoxConfiguration.vue/ServiceBoxConfiguration.vue/RackConfiguration.vue (Mufa/NAP/Rack).
// Reusa EmpalmesPanel.vue (Fase B, item #9990408) en vez de duplicar la lógica de tabla/formulario/
// endpoints. MR-19 Fase 2 (item #9990566) suma el botón "Carta de empalme" en el mismo punto —
// cubre las 3 pantallas de un solo cambio, sin tocarlas una por una.
import { ref, watch } from "vue";
import EmpalmesPanel from "../others/EmpalmesPanel.vue";
import CartaEmpalmeDialog from "../others/CartaEmpalmeDialog.vue";

defineOptions({
    name: "EmpalmeConfigDialog",
});

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    object: Object,
    hasEdit: Boolean,
});

const emits = defineEmits(["hide"]);

const MAPA_RED_LAYER_MODEL = "App\\Modules\\Addons\\MapaRed\\Models\\MapaRedLayer";

const dialog = ref(false);
const cartaAbierta = ref(false);

watch(
    () => props.show,
    (n) => {
        if (n) {
            dialog.value = true;
        }
    }
);

const onHide = () => {
    emits("hide");
};
</script>
