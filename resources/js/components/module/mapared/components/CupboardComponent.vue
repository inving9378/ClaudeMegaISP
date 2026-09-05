<template>
    <q-dialog
        v-model="dialog"
        persistent
        @before-show="onShowDialog"
        @hide="emits('hide', currentObject)"
        v-if="inDialog"
    >
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section
                        ><h6>
                            {{ object ? "Editar" : "Configurar" }}
                            armario
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
                    <selected-troncal-from-object
                        :routes="currentObject['selected_routes'] ?? []"
                        @destroy="
                            (val) =>
                                (currentObject['selected_routes'] =
                                    currentObject['selected_routes'].filter(
                                        (r) => r.input !== val
                                    ))
                        "
                        v-if="
                            currentObject['selected_routes']?.length > 0 &&
                            !object
                        "
                    />
                </q-form>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right" style="margin: 0px !important"
                ><troncal-dialog
                    :layer="currentObject"
                    @troncal-add="
                        (t) => currentObject['selected_routes'].push(t)
                    "
                    v-if="!object"
                />
                <q-btn no-caps color="primary" label="Guardar" @click="save" />
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
    <q-card v-else>
        <q-card-section>
            <q-form ref="form" greedy>
                <div class="row">
                    <div class="col-lg-6 col-xl-6 col-md-6 col-sm-12 col-xs-12">
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
                            :rows="14"
                            outlined
                            dense
                        />
                    </div>
                    <div class="col-lg-6 col-xl-6 col-md-6 col-sm-12 col-xs-12">
                        <awesome-marker-icon
                            :color="currentObject.color"
                            :icon-color="currentObject.icon_color"
                            @change-color="(val) => (currentObject.color = val)"
                            @change-icon-color="
                                (val) => (currentObject.icon_color = val)
                            "
                        />
                    </div>
                </div>
            </q-form>
        </q-card-section>

        <q-separator />

        <q-card-actions
            align="right"
            style="margin: 0px !important"
            v-if="hasEdit"
        >
            <q-btn no-caps color="primary" label="Guardar" @click="save" />
        </q-card-actions>
    </q-card>
</template>

<script setup>
import { onBeforeMount, ref, watch } from "vue";
import { saveObject } from "../helper/request";
import { errorValidation, message } from "../../../../helpers/toastMsg";
import AwesomeMarkerIcon from "./AwesomeMarkerIcon.vue";
import TroncalDialog from "./others/TroncalDialog.vue";
import SelectedTroncalFromObject from "./others/SelectedTroncalFromObject.vue";
import { hideLoading, showLoading } from "../../../../helpers/loading";

defineOptions({
    name: "CupboardComponent",
});

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    object: Object,
    project: Object,
    layer: Object,
    hasEdit: Boolean,
    inDialog: {
        type: Boolean,
        default: true,
    },
});

const emits = defineEmits(["created", "updated", "cancel", "hide"]);

const dialog = ref(false);
const currentObject = ref(null);
const form = ref(null);

onBeforeMount(() => {
    setDefaultData();
});

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
              selected_routes: [],
          };
};

const onShowDialog = () => {
    setDefaultData();
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
    showLoading();
    const object = await saveObject(currentObject.value);
    hideLoading();
    if (object) {
        if (currentObject.value.id) {
            emits("updated", object);
            message("Armario modificado correctamente");
        } else {
            emits("created", object, currentObject.value.selected_routes ?? []);
            message("Armario agregado correctamente");
        }

        if (props.inDialog) {
            currentObject.value = object;
            dialog.value = false;
        }
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
