<template>
    <q-btn
        icon="mdi-calendar"
        dense
        outline
        color="info"
        padding="8px"
        @click="dialog = true"
        ><q-tooltip> Promociones </q-tooltip></q-btn
    >
    <q-dialog v-model="dialog" persistent @before-show="onShow" @hide="onHide">
        <q-card style="width: 800px; max-width: 90vw">
            <q-card-section class="q-pa-none">
                <q-item>
                    <q-item-section
                        ><h6>Especifique promoción</h6></q-item-section
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
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="current_download_profile"
                            >Promoción</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <select-form-component
                                name="promotion"
                                option-label="name"
                                option-value="id"
                                :required="true"
                                :model-value="formData.promotion"
                                :options="promotions"
                                @change="onChangePromotion"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="current_download_profile"
                            >Velocidad de descarga</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                dense
                                outlined
                                readonly
                                v-model="formData.current_download_profile"
                                :dark="darkMode"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="current_upload_profile"
                            >Velocidad de subida</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-input
                                dense
                                outlined
                                readonly
                                v-model="formData.current_upload_profile"
                                :dark="darkMode"
                            />
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label
                            class="col-12 col-sm-4 text-right col-form-label"
                            for="end_at"
                            >Hasta</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <VueDatePicker
                                id="end_at"
                                v-model="formData.end_at"
                                position="right"
                                locale="es"
                                :teleport="true"
                                week-start="0"
                                :format="customFormat"
                                :enableTimePicker="false"
                                :dark="darkMode"
                                :disabled-dates="
                                    (d) => disableLastDates(d, true)
                                "
                                :readonly="!definedByUser"
                            >
                            </VueDatePicker>
                        </div>
                    </div>
                    <div class="row q-my-sm">
                        <label class="col-12 col-sm-4 text-right col-form-label"
                            >Clientes</label
                        >
                        <div class="col-12 col-sm-8 object-field">
                            <q-card>
                                <q-card-section class="no-padding">
                                    <q-item>
                                        <q-item-section>
                                            <q-input
                                                dense
                                                outlined
                                                v-model="avaiablesQuery"
                                                clearable
                                                placeholder="Buscar..."
                                                :dark="darkMode"
                                            >
                                                <template #after>
                                                    <client-filters
                                                        :filters="filters"
                                                        @change="
                                                            onChangeFilters
                                                        "
                                                    />
                                                </template>
                                            </q-input>
                                        </q-item-section>
                                    </q-item>
                                    <q-item>
                                        <q-item-section>
                                            <q-btn
                                                label="Seleccionar todo"
                                                class="full-width"
                                                outlined
                                                no-caps
                                                @click="
                                                    () =>
                                                        selectAllAvaiablesClient(
                                                            true
                                                        )
                                                "
                                            />
                                        </q-item-section>
                                        <q-item-section>
                                            <q-btn
                                                label="Deseleccionar todo"
                                                class="full-width"
                                                outlined
                                                no-caps
                                                @click="
                                                    () =>
                                                        selectAllAvaiablesClient(
                                                            false
                                                        )
                                                "
                                            />
                                        </q-item-section>
                                    </q-item>
                                    <q-virtual-scroll
                                        style="max-height: 300px"
                                        :items="filteredAvaiables"
                                        v-slot="{ item, index }"
                                    >
                                        <q-item
                                            :key="index"
                                            clickable
                                            :dark="darkMode"
                                            @click="
                                                item.selected = !item.selected
                                            "
                                        >
                                            <q-item-section avatar>
                                                <q-checkbox
                                                    :dark="darkMode"
                                                    v-model="item.selected"
                                                />
                                            </q-item-section>
                                            <q-item-section>
                                                <q-item-label
                                                    class="cursor-pointer"
                                                >
                                                    {{ item.client_id }}
                                                    -
                                                    {{ item.client_name }}
                                                </q-item-label>
                                                <q-item-label caption>
                                                    <q-icon name="upload" />{{
                                                        item.service_ports
                                                            ?.upload_speed
                                                    }}
                                                    /
                                                    <q-icon name="download" />{{
                                                        item.service_ports
                                                            ?.download_speed
                                                    }}
                                                </q-item-label>
                                            </q-item-section>
                                        </q-item>
                                    </q-virtual-scroll>
                                    <q-inner-loading
                                        :showing="avaiablesLoading"
                                        label="Obteniendo clientes, por favor espere..."
                                        label-class="text-primary"
                                        label-style="font-size: 1.1em"
                                        :dark="darkMode"
                                    />
                                </q-card-section>
                            </q-card>
                        </div>
                    </div>
                </q-form>
            </q-card-section>

            <q-separator />

            <q-card-actions align="right" style="margin: 0px !important">
                <q-btn
                    label="Guardar"
                    no-caps
                    color="primary"
                    class="q-mr-sm"
                    @click="save"
                />
                <q-btn
                    label="Cancelar"
                    no-caps
                    color="grey-7"
                    @click="dialog = false"
                />
            </q-card-actions>
            <q-inner-loading
                :showing="saving"
                label="Procesando, por favor espere..."
                label-class="text-primary"
                label-style="font-size: 1.1em"
                :dark="darkMode"
            />
        </q-card>
    </q-dialog>
</template>

<script setup>
import { computed, onBeforeMount, ref, watch } from "vue";
import { darkMode } from "../../../hook/appConfig";
import {
    addClientsToServiceBox,
    getSelectedClients,
    removeClientsFromServiceBox,
} from "../maps/helper/request";

import {
    addMultiClientsToPromotion,
    clientsWithOutDataPromotion,
} from "./helpers/helper";
import { getAvaiablesPromotions } from "../adminstration/user/helper/request";
import {
    useQuasar,
    Screen,
    debounce,
} from "../../../../../public/plugins/quasar/js/quasar.umd.prod";

import SelectFormComponent from "../olts/components/form/SelectFormComponent.vue";
import ClientFilters from "./ClientFilters.vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import { useDatePicker } from "../../../composables/useDatePicker";
import { errorValidation, message } from "../../../helpers/toastMsg";

defineOptions({
    name: "ClientsToPromotionComponent",
});

const props = defineProps({
    object: Object,
    parent_id: {
        type: Number,
        default: null,
    },
});

const emits = defineEmits(["change"]);

const $q = useQuasar();

const { customFormat, disableLastDates } = useDatePicker();

const dialog = ref(false);
const selectedLoading = ref(false);
const avaiablesLoading = ref(false);

const promotions = ref([]);
const promotion = ref(null);
const definedByUser = ref(false);

const avaiablesQuery = ref(null);
const filteredAvaiables = ref([]);

const avaiableClients = ref([]);

const saving = ref(false);

const form = ref(false);
const formData = ref({
    current_download_profile: null,
    current_upload_profile: null,
    end_at: null,
    download_profile: null,
    upload_profile: null,
    clients: [],
});

const filters = ref({
    state_id: null,
    municipality_id: null,
    colony_id: null,
    street: null,
    activation_date: null,
    discharge_date: null,
    created_at: null,
    seller_id: null,
});

onBeforeMount(() => {
    loadPromotions();
});

watch(
    () => avaiablesQuery.value,
    () => {
        debouncedGetList();
    }
);

watch(
    () => filteredAvaiables.value,
    () => {
        formData.value.clients = filteredAvaiables.value
            .filter((f) => f.selected)
            .map((c) => {
                return {
                    id: c.client_id,
                    upload_speed: c.service_ports.upload_speed,
                    download_speed: c.service_ports.download_speed,
                    service_port: c.service_ports.service_port,
                };
            });
    },
    {
        deep: true,
    }
);

watch(
    () => filters,
    () => {
        onRequestAvaiables();
    },
    {
        deep: true,
    }
);

const debouncedGetList = debounce(() => {
    onRequestAvaiables();
}, 400);

const selectAllAvaiablesClient = (selected) => {
    filteredAvaiables.value.forEach((f) => {
        f.selected = selected;
    });
};

const onChangePromotion = (name, val) => {
    formData.value[name] = val;
    let promotion = promotions.value.find((p) => p.id === val);
    if (promotion) {
        formData.value.current_download_profile = promotion.download ?? null;
        formData.value.current_upload_profile = promotion.upload ?? null;
        if (promotion.defined_by_user) {
            definedByUser.value = true;
            formData.value.end_at = null;
        } else {
            formData.value.end_at = moment().add(
                promotion.duration,
                promotion.type_duration
            );
            definedByUser.value = false;
        }
    } else {
        formData.value.current_download_profile = null;
        formData.value.current_upload_profile = null;
        formData.value.end_at = null;
    }
};

const onChangeFilters = (val) => {
    filters.value = val;
    onRequestAvaiables();
};

const onShow = () => {
    avaiableClients.value = [];
    formData.value = {
        current_download_profile: null,
        current_upload_profile: null,
        end_at: null,
        download_profile: null,
        upload_profile: null,
        clients: [],
    };
    onRequestAvaiables();
};

const loadPromotions = async () => {
    const result = await getAvaiablesPromotions("data_plan_promotion");
    if (result) {
        promotions.value = result.promotions.map((p) => p.promotionable);
    }
};

const onRequestAvaiables = async () => {
    avaiablesLoading.value = true;
    let data = await clientsWithOutDataPromotion({
        ...filters.value,
        search: avaiablesQuery.value,
    });
    avaiablesLoading.value = false;
    if (data !== null) {
        let clients = formData.value.clients.map((c) => c.id);
        data.forEach((d) => {
            d["selected"] = clients.includes(d.client_id);
            d.service_ports = d.service_ports
                ? JSON.parse(d.service_ports)[0]
                : null;
        });
        filteredAvaiables.value = data;
        avaiableClients.value = data;
    } else {
        avaiableClients.value = [];
    }
};

const save = () => {
    form.value.validate().then(async (success) => {
        if (success) {
            if (formData.value.end_at) {
                create();
            } else {
                message("Debe especificar la fecha", "error");
            }
        } else {
            errorValidation();
        }
    });
};

const create = async () => {
    if (formData.value.clients.length > 0) {
        saving.value = true;
        const result = await addMultiClientsToPromotion(formData.value);
        saving.value = false;
        if (result?.success) {
            message(result.message, "success");
            dialog.value = false;
        } else {
            message(
                result?.message ??
                    result?.error ??
                    "Error al procesar esta solicitud",
                "error"
            );
        }
    } else {
        message("Al menos debe especificar un cliente", "error");
    }
};

const onHide = () => {
    saving.value = false;
    avaiablesQuery.value = null;
    filters.value = {
        state_id: null,
        municipality_id: null,
        colony_id: null,
        street: null,
        activation_date: null,
        discharge_date: null,
        created_at: null,
        seller_id: null,
    };
    avaiablesQuery.value = null;
};
</script>
