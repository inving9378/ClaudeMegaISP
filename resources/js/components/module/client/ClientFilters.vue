<template>
    <q-btn icon="mdi-filter" dense outline color="info" padding="8px"
        ><q-tooltip> Filtrar </q-tooltip>
        <q-badge
            floating
            color="warning"
            style="width: auto; padding: 2px 5px !important; position: absolute"
            v-if="filtersCount > 0"
            >{{ filtersCount }}</q-badge
        >

        <q-menu persistent>
            <q-card class="no-gutter-x">
                <q-card-section>
                    <q-form ref="form" greedy>
                        <div class="row q-my-sm">
                            <label
                                class="col-12 col-sm-4 text-right col-form-label"
                                for="seller_id"
                                >Vendedor</label
                            >
                            <div class="col-12 col-sm-8 object-field">
                                <select-form-component
                                    name="seller_id"
                                    option-label="text"
                                    :model-value="formData.seller_id"
                                    :options="options.sellers"
                                    :loading="loadings.sellers"
                                    @change="
                                        (name, val) => onChangeOption(name, val)
                                    "
                                />
                            </div>
                        </div>
                        <div class="row q-my-sm">
                            <label
                                class="col-12 col-sm-4 text-right col-form-label"
                                for="state_id"
                                >Estado</label
                            >
                            <div class="col-12 col-sm-8 object-field">
                                <select-form-component
                                    name="state_id"
                                    option-label="text"
                                    :model-value="formData.state_id"
                                    :options="options.states"
                                    :loading="loadings.states"
                                    @change="
                                        (name, val) =>
                                            onChangeOption(name, val, {
                                                params: {
                                                    model: 'App\\Models\\Municipality',
                                                    id: 'id',
                                                    text: 'name',
                                                    filter: [
                                                        {
                                                            field: 'state_id',
                                                            value: val,
                                                        },
                                                    ],
                                                    idModel: null,
                                                },
                                                options: 'municipalities',
                                            })
                                    "
                                />
                            </div>
                        </div>
                        <div class="row q-my-sm">
                            <label
                                class="col-12 col-sm-4 text-right col-form-label"
                                for="municipality_id"
                                >Municipio</label
                            >
                            <div class="col-12 col-sm-8 object-field">
                                <select-form-component
                                    name="municipality_id"
                                    option-label="text"
                                    :model-value="formData.municipality_id"
                                    :options="options.municipalities"
                                    :loading="loadings.municipalities"
                                    @change="
                                        (name, val) =>
                                            onChangeOption(name, val, {
                                                params: {
                                                    model: 'App\\Models\\Colony',
                                                    id: 'id',
                                                    text: 'name',
                                                    filter: [
                                                        {
                                                            field: 'municipality_id',
                                                            value: val,
                                                        },
                                                    ],
                                                    idModel: null,
                                                },
                                                options: 'colonies',
                                            })
                                    "
                                />
                            </div>
                        </div>
                        <div class="row q-my-sm">
                            <label
                                class="col-12 col-sm-4 text-right col-form-label"
                                for="colony_id"
                                >Colonia</label
                            >
                            <div class="col-12 col-sm-8 object-field">
                                <select-form-component
                                    name="colony_id"
                                    option-label="text"
                                    :model-value="formData.colony_id"
                                    :options="options.colonies"
                                    @change="
                                        (name, val) =>
                                            onChangeOption(name, val, {
                                                params: {
                                                    model: 'App\\Models\\ClientMainInformation',
                                                    id: 'street',
                                                    text: 'street',
                                                    filter: [
                                                        {
                                                            field: 'state_id',
                                                            value: formData.state_id,
                                                        },
                                                        {
                                                            field: 'municipality_id',
                                                            value: formData.municipality_id,
                                                        },
                                                        {
                                                            field: 'colony_id',
                                                            value: val,
                                                        },
                                                    ],
                                                    idModel: null,
                                                },
                                                options: 'street',
                                            })
                                    "
                                />
                            </div>
                        </div>
                        <div class="row q-my-sm">
                            <label
                                class="col-12 col-sm-4 text-right col-form-label"
                                for="street"
                                >Dirección</label
                            >
                            <div class="col-12 col-sm-8 object-field">
                                <select-form-component
                                    name="street"
                                    option-label="text"
                                    :model-value="formData.street"
                                    :options="options.street"
                                    @change="onChangeOption"
                                />
                            </div>
                        </div>
                        <div class="row q-my-sm">
                            <label
                                class="col-12 col-sm-4 text-right col-form-label"
                                for="created_at"
                                >Fecha creado</label
                            >
                            <div class="col-12 col-sm-8 object-field">
                                <VueDatePicker
                                    id="end_at"
                                    v-model="formData.created_at"
                                    position="right"
                                    locale="es"
                                    :teleport="true"
                                    week-start="0"
                                    range
                                    :format="customFormat"
                                    :enableTimePicker="false"
                                    :dark="darkMode"
                                    :disabled-dates="(d) => disableDates(d)"
                                >
                                </VueDatePicker>
                            </div>
                        </div>
                        <div class="row q-my-sm">
                            <label
                                class="col-12 col-sm-4 text-right col-form-label"
                                for="activation_date"
                                >Fecha activación</label
                            >
                            <div class="col-12 col-sm-8 object-field">
                                <VueDatePicker
                                    id="end_at"
                                    v-model="formData.activation_date"
                                    position="right"
                                    locale="es"
                                    :teleport="true"
                                    week-start="0"
                                    range
                                    :format="customFormat"
                                    :enableTimePicker="false"
                                    :dark="darkMode"
                                    :disabled-dates="(d) => disableDates(d)"
                                >
                                </VueDatePicker>
                            </div>
                        </div>
                        <div class="row q-my-sm">
                            <label
                                class="col-12 col-sm-4 text-right col-form-label"
                                for="discharge_date"
                                >Fecha alta</label
                            >
                            <div class="col-12 col-sm-8 object-field">
                                <VueDatePicker
                                    id="end_at"
                                    v-model="formData.discharge_date"
                                    position="right"
                                    locale="es"
                                    :teleport="true"
                                    week-start="0"
                                    range
                                    :format="customFormat"
                                    :enableTimePicker="false"
                                    :dark="darkMode"
                                    :disabled-dates="(d) => disableDates(d)"
                                >
                                </VueDatePicker>
                            </div>
                        </div>
                    </q-form>
                </q-card-section>
            </q-card>
        </q-menu>
    </q-btn>
    <q-btn
        icon="mdi-eraser"
        color="grey-6"
        dense
        outline
        padding="8px"
        @click="() => onClear()"
        v-if="filtersCount > 0"
        ><q-tooltip> Limpiar filtros </q-tooltip></q-btn
    >
</template>

<script setup>
import { computed, onBeforeMount, onMounted, ref, watch } from "vue";
import { darkMode } from "../../../hook/appConfig";

import {
    useQuasar,
    Screen,
} from "../../../../../public/plugins/quasar/js/quasar.umd.prod";

import SelectFormComponent from "../olts/components/form/SelectFormComponent.vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import { useDatePicker } from "../../../composables/useDatePicker";

import { getOptions } from "../../../helpers/Transform";
import moment from "moment/moment";

defineOptions({
    name: "ClientsToPromotionComponent",
});

const props = defineProps({
    object: Object,
    parent_id: {
        type: Number,
        default: null,
    },
    filters: {
        type: Object,
        default: null,
    },
});

const emits = defineEmits(["change"]);

const $q = useQuasar();

const { customFormat, disableFutureDates } = useDatePicker();

const dialog = ref(false);

const form = ref(false);

const formData = ref(props.filters);

const options = ref({
    states: [],
    municipalities: [],
    colonies: [],
    street: [],
    sellers: [],
});

const loadings = ref({
    states: false,
    municipalities: false,
    colonies: false,
    street: false,
    sellers: false,
});

onBeforeMount(async () => {
    loadings.value.sellers = true;
    let result = await getOptions({
        model: "App\\Models\\User",
        id: "id",
        text: "name",
        scope: "sellerRole",
    });
    loadings.value.sellers = false;
    options.value.sellers = result;

    loadings.value.states = true;
    result = await getOptions({
        model: "App\\Models\\State",
        id: "id",
        text: "name",
    });
    loadings.value.states = false;
    options.value.states = result;
});

watch(
    () => formData.value.state_id,
    () => {
        formData.value.municipality_id = null;
        formData.value.colony_id = null;
        formData.value.street = null;
    }
);

watch(
    () => formData.value.municipality_id,
    () => {
        formData.value.colony_id = null;
        formData.value.street = null;
    }
);

watch(
    () => formData.value.colony_id,
    () => {
        formData.value.street = null;
    }
);

const filtersCount = computed(() => {
    let count = 0,
        filters = props.filters;
    if (filters) {
        Object.keys(filters).forEach((k) => {
            if (filters[k] !== null) {
                count++;
            }
        });
    }
    return count;
});

const onChangeOption = async (name, val, load = null) => {
    formData.value[name] = val;
    if (load) {
        const result = await getOptions(load.params);
        options.value[load.options] = result;
    }
};

const disableDates = (date) => {
    date = moment(date);
    const start = moment("2024-06-01");
    const end = moment();
    if (date.isBefore(start, "day") || date.isAfter(end, "day")) {
        return true;
    }
    return false;
};

const onClear = () => {
    Object.keys(formData.value).forEach((k) => {
        formData.value[k] = null;
    });
};
</script>
