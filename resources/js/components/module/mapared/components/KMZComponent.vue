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
                    <q-item-section><h6>Configurar KMZ</h6></q-item-section>
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
                            <label for="object-type">Tipo de objeto</label>
                            <q-select
                                v-model="currentObject.object_type"
                                for="object-type"
                                :rules="[(val) => rules.required(val)]"
                                outlined
                                dense
                                options-dense
                                option-value="dialog"
                                option-label="text"
                                :options="options"
                                @update:model-value="onChangeType"
                            />

                            <label for="object-name" class="q-mt-sm"
                                >Nombre</label
                            >
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
import { ref, watch, onMounted } from "vue";
import { saveObject } from "../helper/request";
import { errorValidation, message } from "../../../../helpers/toastMsg";
import AwesomeMarkerIcon from "./AwesomeMarkerIcon.vue";
import { menuOptions } from "../helper/mapUtils";
import { rules } from "../../../../helpers/validations";
import { currentNode } from "../../../../composables/useNodeMap";

defineOptions({
    name: "KMZComponent",
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
const excludes = [
    "folder",
    "region",
    "route",
    "note",
    "elements_in_serie",
    "client",
];

const options = ref([]);

onMounted(() => {
    options.value = menuOptions.filter((m) => !excludes.includes(m.dialog));
});
watch(
    () => props.show,
    (n) => {
        if (n) {
            dialog.value = true;
        }
    }
);

const onChangeType = (val) => {
    Object.assign(currentObject.value, val);
};

const setDefaultData = () => {
    currentObject.value = { ...props.object };
    currentObject["object_type"] = null;
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
        emits("updated", object);
        message("KMZ modificado correctamente");
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
