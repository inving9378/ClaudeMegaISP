<template>
    <q-btn no-caps color="primary" label="Troncales" @click="showDialog = true">
        <q-tooltip>Agregar troncal</q-tooltip>
    </q-btn>
    <q-dialog
        v-model="showDialog"
        persistent
        transition-show="scale"
        transition-hide="scale"
    >
        <q-card style="width: 350px; max-width: 80vw">
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section
                        ><h6>Configurar troncales</h6></q-item-section
                    >
                    <q-item-section avatar>
                        <q-btn
                            icon="close"
                            flat
                            round
                            dense
                            @click="showDialog = false"
                        />
                    </q-item-section>
                </q-item>
            </q-card-section>

            <q-separator />
            <q-card-section>
                <q-form greedy ref="form">
                    <avaiables-routes-component
                        :layer="layer"
                        :multiple="false"
                        :clear="clear"
                        @update="(r) => (currentRoute = r)"
                        @clear="clear = false"
                /></q-form>
            </q-card-section>
            <q-separator />
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    no-caps
                    color="primary"
                    label="Agregar"
                    class="q-mr-sm"
                    @click="() => addRoute(true)"
                />
                <q-btn
                    no-caps
                    color="primary"
                    label="Agregar y continuar"
                    class="q-mr-sm"
                    @click="() => addRoute(false)"
                />
                <q-btn
                    no-caps
                    flat
                    color="primary"
                    label="Cancelar"
                    @click="showDialog = false"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, watch } from "vue";
import AvaiablesRoutesComponent from "./AvaiablesRoutesComponent.vue";
import { errorValidation } from "../../../../../helpers/toastMsg";

defineOptions({
    name: "DialogConfirm",
});

const props = defineProps({
    layer: Object,
    routes: {
        type: Array,
        default: [],
    },
});

const emits = defineEmits(["troncal-add"]);

const showDialog = ref(false);
const currentRoute = ref(null);
const form = ref(null);
const clear = ref(false);

const addRoute = (close = false) => {
    form.value.validate().then(async (success) => {
        if (success) {
            emits("troncal-add", currentRoute.value);
            if (close) {
                showDialog.value = false;
            } else {
                clear.value = true;
                form.value.resetValidation();
            }
        } else {
            errorValidation();
        }
    });
};
</script>
