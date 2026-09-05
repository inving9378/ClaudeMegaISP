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
                            {{ object ? "Editar" : "Adicionar" }}
                            carpeta
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
                        <div class="col">
                            <label for="object-name">Nombre</label>
                            <q-input
                                v-model="currentObject.name"
                                for="object-name"
                                :rules="[(val) => !!val || 'Requerido']"
                                outlined
                                dense
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
import { saveProject } from "../helper/request";
import { errorValidation, message } from "../../../../helpers/toastMsg";
import { currentNode } from "../../../../composables/useNodeMap";

defineOptions({
    name: "FolderComponent",
});

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    object: Object,
    project: Object,
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
              name: null,
              parent_id: props.project.id,
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
    const object = await saveProject(
        props.object?.id ?? null,
        currentObject.value
    );
    loading.value = false;
    if (object !== null) {
        currentNode.value = object;
        if (currentObject.value.id) {
            emits("updated", object);
            message("Carpeta modificada correctamente");
        } else {
            emits("created", object);
            message("Carpeta agregada correctamente");
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
