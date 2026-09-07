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
                    <q-item-section avatar>
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
</template>

<script setup>
// MR-12 Fase B1 (item roadmap #9990520) — modal de uniones de hilos, enganchado desde
// JunctionBoxConfiguration.vue/ServiceBoxConfiguration.vue (Mufa/NAP). Reusa EmpalmesPanel.vue
// (Fase B, item #9990408) en vez de duplicar la lógica de tabla/formulario/endpoints.
import { ref, watch } from "vue";
import EmpalmesPanel from "../others/EmpalmesPanel.vue";

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
