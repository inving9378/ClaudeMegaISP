<template>
    <q-btn
        color="primary"
        class="q-mr-sm"
        label="Añadir servicio"
        no-caps
        @click="showModal = true"
        v-if="!object"
    />

    <q-btn
        flat
        round
        dense
        color="primary"
        icon="edit"
        size="12px"
        @click="showModal = true"
        v-else
    />

    <modal
        :show="showModal"
        :size="'md'"
        @update:show="showModal = $event"
        :title="`${object ? 'Editar' : 'Registrar'} servicio`"
    >
        <template #body>
            <q-card flat style="margin: -15px">
                <q-card-section style="max-height: 60vh" class="scroll">
                    <q-form ref="formRef" greedy>
                        <div class="row text-left">
                            <div
                                class="col-xs-12 col-sm-6 col-md-6 col-lg-6 col-xl-6"
                            >
                                <select-client
                                    :options="clients"
                                    :required="true"
                                    :model-value="formData.client_id"
                                    option-value="main_id"
                                    @change="
                                        (opt) =>
                                            (formData.client_id =
                                                opt?.main_id ?? null)
                                    "
                                />

                                <label for="activated">Activó</label>
                                <q-select
                                    v-model="formData.activated"
                                    hint="Requerido"
                                    outlined
                                    for="activated"
                                    dense
                                    options-dense
                                    emit-value
                                    :clearable="true"
                                    map-options
                                    :options="[
                                        {
                                            label: 'Si',
                                            value: true,
                                        },
                                        {
                                            label: 'No',
                                            value: false,
                                        },
                                    ]"
                                    :rules="[
                                        (val) => val !== null || 'Requerido',
                                    ]"
                                    :dark="darkMode"
                                >
                                    <template v-slot:selected-item="scope">
                                        <q-item-label
                                            lines="1"
                                            style="margin-top: 5px"
                                            >{{ scope.opt.label }}</q-item-label
                                        >
                                    </template>
                                </q-select>

                                <label for="technical_id">Técnico</label>
                                <q-select
                                    v-model="formData.technical_id"
                                    hint="Requerido"
                                    outlined
                                    for="technical_id"
                                    dense
                                    options-dense
                                    option-label="full_name"
                                    option-value="id"
                                    emit-value
                                    :clearable="true"
                                    map-options
                                    :options="technicals"
                                    :rules="[(val) => !!val || 'Requerido']"
                                    :dark="darkMode"
                                >
                                    <template v-slot:selected-item="scope">
                                        <q-item-label
                                            lines="1"
                                            style="margin-top: 5px"
                                            >{{
                                                scope.opt.full_name
                                            }}</q-item-label
                                        >
                                    </template>
                                </q-select>
                            </div>
                            <div
                                class="col-xs-12 col-sm-6 col-md-6 col-lg-6 col-xl-6"
                            >
                                <label for="branch_id">Sucursal</label>
                                <q-select
                                    v-model="formData.branch_id"
                                    outlined
                                    for="branch_id"
                                    dense
                                    options-dense
                                    option-label="name"
                                    option-value="id"
                                    emit-value
                                    :clearable="true"
                                    map-options
                                    :options="branchs"
                                    :rules="[(val) => !!val || 'Requerido']"
                                    :dark="darkMode"
                                >
                                    <template v-slot:selected-item="scope">
                                        <q-item-label
                                            lines="1"
                                            style="margin-top: 5px"
                                            >{{ scope.opt.name }}</q-item-label
                                        >
                                    </template>
                                </q-select>
                                <label for="comments" class="form-label"
                                    >Comentarios</label
                                >
                                <textarea
                                    v-model="formData.comments"
                                    class="form-control"
                                    rows="6"
                                ></textarea>
                            </div>
                        </div>
                        <div class="row text-left">
                            <div class="col q-pt-md">
                                <label for="service_amount"
                                    >Servicio (precio)</label
                                >
                                <q-input
                                    for="service_amount"
                                    outlined
                                    dense
                                    v-model.number="formData.service_amount"
                                    :dark="darkMode"
                                />
                            </div>
                            <div class="col q-pt-md">
                                <label for="installation_cost"
                                    >Costo de instalación</label
                                >
                                <q-input
                                    for="installation_cost"
                                    outlined
                                    dense
                                    v-model.number="formData.installation_cost"
                                    :dark="darkMode"
                                />
                            </div>
                        </div>
                        <div class="row text-left">
                            <div class="col q-pt-md">
                                <label for="constance">Constancia</label>
                                <q-input
                                    for="constance"
                                    outlined
                                    dense
                                    v-model="formData.constance"
                                    :dark="darkMode"
                                />
                            </div>
                            <div class="col q-pt-md">
                                <label for="warranty_cost"
                                    >Garantía costo</label
                                >
                                <q-input
                                    for="warranty_cost"
                                    outlined
                                    dense
                                    v-model="formData.warranty_cost"
                                    :dark="darkMode"
                                />
                            </div>
                        </div>
                    </q-form>
                </q-card-section>
            </q-card>
        </template>
        <template #footer>
            <q-btn color="primary" label="Guardar" no-caps @click="save" />
        </template>
        <template #loading>
            <q-inner-loading
                :showing="loading"
                label="Guardando datos, por favor espere..."
                label-class="text-primary"
                label-style="font-size: 1.1em"
            />
        </template>
    </modal>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import Modal from "../../../../../../shared/ModalSimple.vue";
import SelectClient from "../../../../../../shared/SelectClient.vue";
import { darkMode } from "../../../../../../hook/appConfig";
import {
    error500,
    errorValidation,
    message,
} from "../../../../../../helpers/toastMsg";
import {
    storeInstallation,
    updateInstallation,
} from "../../helper/cutInstallations";

const props = defineProps({
    object: Object,
    boxId: Number,
    sucursalId: Number,
    branchs: {
        type: Array,
        default: [],
    },
    technicals: {
        type: Array,
        default: [],
    },
    clients: {
        type: Array,
        default: [],
    },
});

const emits = defineEmits(["created", "updated"]);

const showModal = ref(false);
const formRef = ref(null);
const formData = ref({
    activated: null,
    client_id: null,
    technical_id: null,
    branch_id: null,
    comments: null,
    installation_cost: null,
    constance: null,
    warranty_cost: null,
    service_amount: null,
});
const loading = ref(false);
const loadingClient = ref(false);

const currentOptions = ref([]);
const allOptions = ref([]);
const nextPage = ref(2);
const pageSize = ref(50);
const lastPage = ref(1);
const attributes = {
    model: "App\\Models\\ClientMainInformation",
    id: "client_id",
    text: "name",
};

onMounted(() => {
    setDataFromServer();
});

watch(showModal, (n) => {
    loading.value = false;
    if (n) {
        formData.value = props.object
            ? { ...props.object }
            : {
                  activated: null,
                  client_id: null,
                  technical_id: null,
                  branch_id: props.sucursalId ?? null,
                  comments: null,
                  installation_cost: null,
                  constance: null,
                  warranty_cost: null,
                  service_amount: null,
              };
    }
});

const save = () => {
    formRef.value.validate().then((success) => {
        if (success) {
            if (props.object) {
                update();
            } else {
                store();
            }
        } else {
            errorValidation();
        }
    });
};

const store = async () => {
    loading.value = true;
    const result = await storeInstallation({
        ...formData.value,
        box_id: props.boxId,
    });
    if (result) {
        emits("created", result);
        showModal.value = false;
        message("Instalación adicionada correctamente");
    } else {
        error500();
    }
    loading.value = false;
};

const update = async () => {
    loading.value = true;
    const result = await updateInstallation(props.object.id, {
        ...formData.value,
    });
    if (result) {
        emits("updated", result);
        showModal.value = false;
        message("Instalación modificada correctamente");
    } else {
        error500();
    }
    loading.value = false;
};

const setDataFromServer = async () => {
    let currentSelectedValue = null;
    loadingClient.value = true;
    await axios
        .post("/get-options-client", {
            ...attributes,
            pageSize: pageSize.value,
            page: nextPage.value - 1,
            currentSelected: currentSelectedValue,
        })
        .then((response) => {
            let data = response.data;
            let options = data.options;
            currentOptions.value.push(...options);
            allOptions.value.push(...options);
            let current = data.currentSelected;
            if (current !== null) {
                let opt = currentOptions.value.find(
                    (o) => o.value === current.value
                );
                if (!opt) {
                    currentOptions.value.push(current);
                    allOptions.value.push(current);
                }
            }
            lastPage.value = Math.ceil(
                response.data.totalCount / pageSize.value
            );
        })
        .catch((error) => {
            currentOptions.value = [];
            allOptions.value = [];
            lastPage.value = 1;
        });
    loadingClient.value = false;
};

const filterFn = (val, update, abort) => {
    update(() => {
        if (val === "" || val === null || val.length > 2) {
            currentOptions.value = [];
            allOptions.value = [];
            nextPage.value = 2;
            attributes["filter"] = val === "" ? null : val;
            setDataFromServer();
        }
    });
};

const onScroll = async ({ to, ref }) => {
    const lastIndex = currentOptions.value.length - 1;
    if (
        !loadingClient.value &&
        nextPage.value < lastPage.value &&
        to === lastIndex
    ) {
        nextPage.value++;
        await setDataFromServer();
        nextTick(() => {
            ref.refresh();
        });
    }
};
</script>
