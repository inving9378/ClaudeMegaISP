<template>
    <q-dialog
        v-model="dialog"
        persistent
        @before-show="onShowDialog"
        @hide="emits('hide', currentObject)"
    >
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section
                        ><h6>
                            {{ object ? "Editar" : "Configurar" }}
                            fuente
                        </h6></q-item-section
                    >
                    <q-item-section avatar>
                        <q-btn
                            icon="close"
                            flat
                            round
                            dense
                            @click="dialog = false"
                        />
                    </q-item-section>
                </q-item>
            </q-card-section>

            <q-separator />
            <q-card-section>
                <q-form ref="form" greedy>
                    <div class="row">
                        <div
                            class="col-lg-6 col-xl-6 col-md-6 col-sm-12 col-xs-12"
                        >
                            <label for="object-name">Nombre</label>
                            <q-input
                                v-model="currentObject.data.name"
                                for="object-name"
                                :rules="[(val) => !!val || 'Requerido']"
                                outlined
                                dense
                            />

                            <label for="object-description" class="q-mt-md"
                                >Descripción</label
                            ><q-input
                                v-model="currentObject.data.description"
                                type="textarea"
                                for="object-description"
                                :rows="3"
                                outlined
                                dense
                            />
                        </div>
                        <div
                            class="col-lg-6 col-xl-6 col-md-6 col-sm-12 col-xs-12"
                        >
                            <awesome-marker-icon
                                :color="currentObject.color"
                                :icon-color="currentObject.icon_color"
                                @change-color="
                                    (val) => (currentObject.color = val)
                                "
                                @change-icon-color="
                                    (val) => (currentObject.icon_color = val)
                                "
                            />
                        </div>
                    </div>
                </q-form>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right" style="margin: 0px !important">
                <q-btn
                    no-caps
                    color="primary"
                    label="Guardar"
                    :loading="loading"
                    @click="save"
                />
                <q-btn
                    no-caps
                    flat
                    color="primary"
                    label="Cancelar"
                    @click="dialog = false"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, watch } from "vue";
import { saveObject } from "../helper/request";
import { errorValidation, message } from "../../../../helpers/toastMsg";
import AwesomeMarkerIcon from "./AwesomeMarkerIcon.vue";
import { currentNode } from "../../../../composables/useNodeMap";

defineOptions({
    name: "SourceComponent",
});

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    object: Object,
    project: Object,
    layer: Object,
});

const emits = defineEmits(["created", "updated", "cancel", "hide"]);

const dialog = ref(false);
const loading = ref(false);
const currentObject = ref(null);
const form = ref(null);

watch(
    () => props.show,
    (n) => {
        if (n) {
            dialog.value = true;
        }
    }
);

const setDefaultData = () => {
    currentObject.value = props.object
        ? { ...props.object }
        : {
              ...props.layer,
              data: {
                  name: null,
                  description: null,
              },
          };
};

const onShowDialog = () => {
    setDefaultData();
    loading.value = false;
};

const save = () => {
    form.value.validate().then((success) => {
        if (success) {
            storeOrSave();
        } else {
            errorValidation();
        }
    });
};

const storeOrSave = async () => {
    loading.value = true;
    const object = await saveObject(currentObject.value);
    loading.value = false;
    if (object !== null) {
        currentNode.value = object;
        if (currentObject.value.id) {
            emits("updated", object);
            message("Fuente modificada correctamente");
        } else {
            emits("created", object);
            message("Fuente agregada correctamente");
        }
        currentObject.value = object;
        dialog.value = false;
    } else {
        message(
            "Ha ocurrido un error interno. Consulte al administrador",
            "error"
        );
    }
};
</script>

<style scope>
.q-field.row,
.q-field__control.row {
    margin-left: 0px !important;
    margin-right: 0px !important;
    --bs-gutter-x: 0px !important;
}
.q-item__section.column {
    width: auto !important;
}
.q-item__section.column {
    min-width: 10px !important;
}
</style>
