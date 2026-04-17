<template>
    <q-item-label
        class="cursor-pointer text-primary"
        @click="showDialog = true"
        >{{ defaultValue(object[field]) }}</q-item-label
    >

    <q-dialog
        v-model="showDialog"
        persistent
        @before-show="beforeShow"
        @hide="emits('hide')"
    >
        <q-card style="width: 400px; max-width: 50vw">
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">Mover ONU</div>
                    </q-item-section>
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
            <q-card-section style="max-height: 60vh" class="scroll">
                <q-form ref="form" greedy>
                    <div class="row q-my-sm" v-if="field === 'olt_name'">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="olt"
                            >OLT</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="olt_id"
                                option-label="name"
                                option-value="id"
                                :model-value="formData.olt_id"
                                :options="oltsOptions"
                                :required="true"
                                :loading="loading"
                                @change="
                                    (name, val) => {
                                        formData[name] = val;
                                        onChangeOlt(val);
                                    }
                                "
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="board"
                            >Board</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="board"
                                option-value="slot"
                                :model-value="formData.board"
                                :options="cardsOptions"
                                :required="true"
                                :loading="loading"
                                @change="
                                    (name, val) => {
                                        formData[name] = val;
                                        onChangeCard(val);
                                    }
                                "
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="port"
                            >Puerto</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="port"
                                :model-value="formData.port"
                                :options="portOptions"
                                :required="true"
                                :loading="loading"
                                @change="(name, val) => (formData[name] = val)"
                            />
                        </div>
                    </div>
                </q-form>
            </q-card-section>
            <q-separator />
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Guardar"
                    no-caps
                    color="primary"
                    class="q-mr-sm"
                    @click="save"
                />
                <q-btn
                    label="Cerrar"
                    no-caps
                    @click="showDialog = false"
                    color="grey-7"
                />
            </q-card-actions>
            <q-inner-loading
                :showing="saving"
                label="Procesando, por favor espere..."
                label-class="text-primary"
                label-style="font-size: 1.1em"
            />
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, watch } from "vue";
import { errorValidation, message } from "../../../../../helpers/toastMsg";
import axios from "axios";
import { getOLTs } from "../../helper/request";
import { useUtils } from "../../../../../composables/useUtils";
import SelectFormComponent from "./SelectFormComponent.vue";

defineOptions({
    name: "FormMoveOnu",
});

const props = defineProps({
    object: Object,
    show: Boolean,
    field: {
        type: String,
        default: "olt_name",
    },
});

const emits = defineEmits(["update", "hide"]);

const showDialog = ref(false);

const form = ref("form");
const formData = ref({});

const saving = ref(false);

const oltsOptions = ref([]);
const portOptions = ref([]);
const cardsOptions = ref([]);
const loading = ref(false);

const { defaultValue } = useUtils();

watch(
    () => props.show,
    (n) => {
        showDialog.value = n;
    }
);

const beforeShow = async () => {
    if (oltsOptions.value.length === 0) {
        loading.value = true;
        let result = await getOLTs();
        loading.value = false;
        if (result) {
            oltsOptions.value = result.rows;
        }
    }
    let obj = props.object,
        olt_id = null,
        board = null,
        port = null,
        found = oltsOptions.value.find((o) => o.id === obj.olt_id);
    if (found) {
        olt_id = found.id;
        onChangeOlt(olt_id);
    }
    board = obj.board;
    onChangeCard(board);
    port = obj.port;
    formData.value = {
        olt_id,
        board,
        port,
    };
};

const onChangeCard = (val) => {
    formData.value.port = null;
    if (val !== null) {
        let selectedCard =
            cardsOptions.value.find((c) => c.slot === val) ?? null;
        let list = [];
        if (selectedCard) {
            for (let i = 0; i < selectedCard.ports; i++) {
                list.push({
                    label: i,
                    value: i,
                });
            }
        }
        portOptions.value = list;
    } else {
        portOptions.value = [];
    }
};

const onChangeOlt = (val) => {
    formData.value.board = null;
    formData.value.port = null;
    if (val !== null) {
        let foundOlt = oltsOptions.value.find((c) => c.id === val) ?? null;
        if (foundOlt) {
            cardsOptions.value = foundOlt.cards;
        }
    } else {
        cardsOptions.value = [];
        portOptions.value = [];
    }
};

const save = async () => {
    form.value.validate().then(async (success) => {
        if (success) {
            saving.value = true;
            await axios
                .post(`/olts/onus/move/${props.object.id}`, formData.value)
                .then((res) => {
                    let response = res.data;
                    if (!response.success) {
                        message(response.error ?? response.message, "error");
                    } else {
                        emits("update", response.onu);
                        message("ONU actualizada correctamente", "success");
                        showDialog.value = false;
                    }
                })
                .catch((err) => {
                    message(
                        "Ha ocurrido un error al procesar la solicitud",
                        "error"
                    );
                })
                .finally(() => {
                    saving.value = false;
                });
        } else {
            errorValidation();
        }
    });
};
</script>
