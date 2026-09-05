<template>
    <q-btn
        round
        dense
        :icon="object ? 'edit' : 'router'"
        :size="object ? '13px' : null"
        color="primary"
        @click="dialog = true"
        v-if="btn || object"
        ><q-tooltip>
            {{ object ? "Editar" : "Agregar splitter" }}
        </q-tooltip></q-btn
    >
    <q-item clickable style="padding: 5px" @click="dialog = true" v-else>
        <q-item-section avatar>
            <q-icon name="router" />
        </q-item-section>
        <q-item-section style="margin-left: 5px"> Splitter </q-item-section>
    </q-item>
    <q-dialog v-model="dialog" persistent @before-show="onShowDialog">
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section
                        ><h6>
                            {{ object ? "Editar" : "Adicionar" }}
                            splitter
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
                        <div class="col2">
                            <label for="object-name">Nombre</label>
                            <q-input
                                v-model="currentObject.name"
                                for="object-name"
                                :rules="[(val) => rules.required(val)]"
                                outlined
                                dense
                            />

                            <label for="object-ports"
                                >Cantidad de puertos</label
                            >
                            <q-select
                                v-model.number="currentObject.data.ports"
                                for="object-ports"
                                :rules="[(val) => rules.required(val)]"
                                outlined
                                dense
                                options-dense
                                clearable
                                emit-value
                                :options="portOptions"
                            />

                            <label for="object-orientation">Orientación</label>
                            <q-select
                                v-model="currentObject.orientation"
                                for="object-orientation"
                                :rules="[(val) => rules.required(val)]"
                                outlined
                                dense
                                options-dense
                                clearable
                                emit-value
                                map-options
                                :options="[
                                    {
                                        label: 'Izquierda',
                                        value: 'left',
                                    },
                                    {
                                        label: 'Derecha',
                                        value: 'right',
                                    },
                                ]"
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
import { saveDevice } from "../../helper/devices-request";
import { hideLoading, showLoading } from "../../../../../helpers/loading";

defineOptions({
    name: "FormSplitterComponent",
});

const props = defineProps({
    object: Object,
    parent_id: {
        type: Number,
        default: null,
    },
    box: Object,
    btn: {
        type: Boolean,
        default: false,
    },
});

const emits = defineEmits(["created", "updated", "cancel", "hide"]);

const dialog = ref(false);
const currentObject = ref(null);
const form = ref(null);

const defaultOptions = [
    {
        label: 2,
        value: 2,
    },
    {
        label: 4,
        value: 4,
    },
    {
        label: 6,
        value: 6,
    },
    {
        label: 8,
        value: 8,
    },
    {
        label: 12,
        value: 12,
    },
    {
        label: 16,
        value: 16,
    },
    {
        label: 24,
        value: 24,
    },
    {
        label: 32,
        value: 32,
    },
    {
        label: 64,
        value: 64,
    },
];

const portOptions = ref([]);

const setDefaultData = () => {
    if (props.object) {
        currentObject.value = { ...props.object };
        portOptions.value = defaultOptions.filter(
            (o) => o.value >= props.object.ports.length
        );
    } else {
        currentObject.value = {
            name: null,
            layer_id: props.box.id,
            orientation: "right",
            position_x: 20,
            position_y: 20,
            type: "splitter",
            data: { ports: null },
            parent_id: props.parent_id,
        };
        portOptions.value = defaultOptions;
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
    const object = await saveDevice(currentObject.value);
    hideLoading();
    if (object !== null) {
        if (currentObject.value.id) {
            emits("updated", object);
            message("Dispositivo modificado correctamente");
        } else {
            emits("created", object);
            message("Dispositivo agregado correctamente");
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
