<template>
    <q-btn
        size="sm"
        padding="5px"
        color="danger"
        icon="delete"
        @click="dialog = true"
        ><q-tooltip class="bg-danger">Eliminar</q-tooltip></q-btn
    >
    <q-dialog v-model="dialog" persistent @before-show="onShowDialog">
        <q-card>
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section
                        ><h6>
                            Configurar motivo de eliminacion
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
            <q-card-section style="max-height: 50vh" class="scroll">
                <q-form ref="form" greedy>
                    <div class="row">
                        <div class="col">
                            <label for="object-type">Motivo</label>
                            <q-select
                                v-model="currentObject.reason"
                                for="object-type"
                                :rules="[(val) => rules.required(val)]"
                                outlined
                                dense
                                options-dense
                                emit-value
                                map-options
                                :options="options"
                                @update:model-value="
                                    (val) => {
                                        setDefaultData(false);
                                        currentObject.reason = val;
                                    }
                                "
                            />

                            <div
                                class="q-mt-sm"
                                v-if="currentObject?.reason === 'Cancelación'"
                            >
                                <input
                                    type="checkbox"
                                    v-model="currentObject.fiber_quit"
                                    id="fiber_quit"
                                    style="
                                        width: 20px;
                                        height: 20px;
                                        margin-right: 10px;
                                    "
                                />
                                <label
                                    for="fiber_quit"
                                    style="position: absolute"
                                    class="cursor-pointer"
                                    >Se retira la fibra</label
                                >
                            </div>

                            <template
                                v-if="
                                    currentObject?.reason ===
                                    'Cambio de domicilio'
                                "
                            >
                                <label for="object-address"
                                    >Dirección actual</label
                                ><q-input
                                    v-model="currentObject.current_address"
                                    type="textarea"
                                    for="object-address"
                                    :rows="3"
                                    outlined
                                    dense
                                    readonly
                                />

                                <label for="object-zip" class="q-mt-md"
                                    >Código Zip</label
                                ><q-input
                                    :rules="[(val) => rules.required(val)]"
                                    v-model="currentObject.zip"
                                    for="object-zip"
                                    outlined
                                    dense
                                />

                                <label for="object-street">Calle</label
                                ><q-input
                                    :rules="[(val) => rules.required(val)]"
                                    v-model="currentObject.street"
                                    for="object-street"
                                    outlined
                                    dense
                                />

                                <label for="object-external_number"
                                    >Número externo</label
                                ><q-input
                                    :rules="[(val) => rules.required(val)]"
                                    v-model="currentObject.external_number"
                                    for="object-external_number"
                                    outlined
                                    dense
                                />

                                <label for="object-internal_number"
                                    >Número interno</label
                                ><q-input
                                    :rules="[(val) => rules.required(val)]"
                                    v-model="currentObject.internal_number"
                                    for="object-internal_number"
                                    outlined
                                    dense
                                />

                                <label for="object-type">Estado</label>
                                <q-select
                                    v-model="currentObject.state_id"
                                    for="object-type"
                                    :rules="[(val) => rules.required(val)]"
                                    outlined
                                    dense
                                    options-dense
                                    emit-value
                                    map-options
                                    :options="relations.state.options"
                                    :loading="relations.state.loading"
                                    @update:model-value="
                                        (val) =>
                                            loadRelations('municipality', val)
                                    "
                                />

                                <label for="object-type">Municipio</label>
                                <q-select
                                    v-model="currentObject.municipality_id"
                                    for="object-type"
                                    :rules="[(val) => rules.required(val)]"
                                    outlined
                                    dense
                                    options-dense
                                    emit-value
                                    map-options
                                    :options="relations.municipality.options"
                                    :loading="relations.municipality.loading"
                                    @update:model-value="
                                        (val) => loadRelations('colony', val)
                                    "
                                />

                                <label for="object-type">Colonia</label>
                                <q-select
                                    v-model="currentObject.colony_id"
                                    for="object-type"
                                    :rules="[(val) => rules.required(val)]"
                                    outlined
                                    dense
                                    options-dense
                                    emit-value
                                    map-options
                                    :options="relations.colony.options"
                                    :loading="relations.colony.loading"
                                />
                            </template>
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
import { ref, watch } from "vue";
import { removeClientFromServiceBox } from "../../helper/request";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import { rules } from "../../../../../helpers/validations";
import { hideLoading, showLoading } from "../../../../../helpers/loading";
import axios from "axios";

defineOptions({
    name: "FormDisconnectClient",
});

const props = defineProps({
    object: Object,
});

const emits = defineEmits(["removed"]);

const dialog = ref(false);
const currentObject = ref({
    current_address: null,
    client_id: null,
    zip: null,
    street: null,
    internal_number: null,
    external_number: null,
    state_id: null,
    municipality_id: null,
    colony_id: null,
    reason: null,
    fiber_quit: false,
});
const form = ref(null);
const relations = ref({
    state: {
        model: "App\\Models\\State",
        id: "id",
        text: "name",
        idModel: null,
        loading: false,
        options: [],
        filter: [
            {
                field_relation: null,
                field: null,
                value: null,
            },
        ],
    },
    municipality: {
        model: "App\\Models\\Municipality",
        id: "id",
        text: "name",
        filter: [
            {
                field_relation: "state",
                field: "id",
                value: null,
            },
        ],
        idModel: null,
        loading: false,
        options: [],
    },
    colony: {
        model: "App\\Models\\Colony",
        id: "id",
        text: "name",
        filter: [
            {
                field_relation: "municipio",
                field: "id",
                value: null,
            },
        ],
        idModel: null,
        loading: false,
        options: [],
    },
});

const options = [
    {
        label: "Cancelación",
        value: "Cancelación",
    },
    {
        label: "Cambio de domicilio",
        value: "Cambio de domicilio",
    },
    {
        label: "Inactivo",
        value: "Inactivo",
    },
];

const setDefaultData = (resetReason = true) => {
    const { address, client_id } = props.object.client;
    currentObject.value = {
        current_address: address,
        client_id,
        zip: null,
        street: null,
        internal_number: null,
        external_number: null,
        state_id: null,
        municipality_id: null,
        colony_id: null,
        fiber_quit: false,
    };
    if (resetReason) {
        currentObject.value["reason"] = null;
    }
};

const onShowDialog = () => {
    setDefaultData();
    loadRelations("state");
};

const save = () => {
    form.value.validate().then((success) => {
        if (success) {
            destroy();
        } else {
            errorValidation();
        }
    });
};

const destroy = async () => {
    showLoading();
    const result = await removeClientFromServiceBox(
        props.object.id,
        currentObject.value
    );
    hideLoading();
    if (result !== null) {
        emits("removed", result);
        message("Cliente eliminado correctamente");
        dialog.value = false;
    } else {
        message(
            "Ha ocurrido un error interno. Consulte al administrador",
            "error"
        );
    }
};

const loadRelations = async (relation, value = null) => {
    relations.value[relation].filter[0].value = value;
    relations.value[relation].loading = true;
    const result = await axios.post(
        "/get-options-select",
        relations.value[relation]
    );
    relations.value[relation].loading = false;
    if (result) {
        let options = [];
        for (let key in result.data) {
            options.push({
                label: result.data[key],
                value: parseInt(key),
            });
        }
        relations.value[relation].options = options;
    }
};
</script>

<style>
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
