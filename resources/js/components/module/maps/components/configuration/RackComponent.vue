<template>
    <q-btn
        round
        dense
        :icon="object ? 'edit' : 'door_back'"
        :size="object ? '13px' : null"
        color="primary"
        @click="dialog = true"
        ><q-tooltip>
            {{ object ? "Editar" : "Agregar rack" }}
        </q-tooltip></q-btn
    >
    <q-dialog v-model="dialog" persistent @show="onShowDialog">
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section
                        ><h6>
                            {{ object ? "Editar" : "Adicionar" }}
                            rack
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
                                :rules="[(val) => rules.required(val)]"
                                outlined
                                dense
                            />
                            <label for="object-description" class="q-mt-md"
                                >Descripción</label
                            ><q-input
                                v-model="currentObject.description"
                                type="textarea"
                                for="object-description"
                                :rows="3"
                                outlined
                                dense
                            />
                        </div>
                    </div>
                </q-form>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right" style="margin: 0px !important">
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
</template>

<script setup>
import { ref } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import { rules } from "../../../../../helpers/validations";
import { saveRack } from "../../helper/site-request";
import { hideLoading, showLoading } from "../../../../../helpers/loading";

defineOptions({
    name: "RackComponent",
});

const props = defineProps({
    object: Object,
    site: Object,
});

const emits = defineEmits(["created", "updated", "cancel", "hide"]);

const dialog = ref(false);
const currentObject = ref({
    name: null,
    description: null,
});
const form = ref(null);

const setDefaultData = () => {
    if (props.object) {
        currentObject.value = { ...props.object };
    } else {
        currentObject.value = {
            name: null,
            description: null,
        };
    }
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
    const object = await saveRack(currentObject.value);
    hideLoading();
    if (object !== null) {
        if (currentObject.value.id) {
            emits("updated", object);
            message("Rack modificado correctamente");
        } else {
            emits("created", object);
            message("Rack agregado correctamente");
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
