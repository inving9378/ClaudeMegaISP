<template>
    <chart-card title="Estadisticas de prospectos y ventas">
        <template #chart>
            <label class="form-label my-1">Filtrar por rango de fecha</label>
            <div
                id="sales-chart"
                style="min-height: 365px"
                class="relative-position"
            >
                <VueDatePicker
                    v-model="date"
                    position="left"
                    locale="es"
                    :max-date="new Date()"
                    min-date="2024/06/01"
                    :teleport="true"
                    placeholder="Selecciona un rango de fecha"
                    range
                    multi-calendars
                    :format="customFormat"
                    :enableTimePicker="false"
                />
                <apexchart
                    type="bar"
                    height="350"
                    :options="chartOptions"
                    :series="series"
                ></apexchart>
                <q-inner-loading :showing="showLoading" color="primary" />
            </div>
        </template>
    </chart-card>
</template>

<script setup>
import { ref, onMounted, defineProps, watch, onBeforeMount } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import ChartCard from "../../../base/card/chart/ChartCard.vue";
import { salesAndProspects } from "./helper/request.js";
import { useDatePicker } from "../../../../composables/useDatePicker.js";

const props = defineProps({
    id: {
        type: Number,
        default: null,
    },
});

const { customFormat } = useDatePicker();

const showLoading = ref(false);
const date = ref();
const series = ref([]);
const chartOptions = ref({
    chart: {
        id: "sales-chart",
    },
    plotOptions: {
        bar: {
            columnWidth: "45%",
            endingShape: "rounded",
            borderRadius: 4,
        },
    },
    xaxis: {
        categories: [],
    },
    yaxis: {
        title: {
            text: "Número de ventas",
        },
    },
});

onBeforeMount(() => {
    setDefaultValues();
});

onMounted(() => {
    getData();
});

watch(date, () => {
    getData();
});

const getData = async () => {
    showLoading.value = true;
    const response = await salesAndProspects(props.id, date.value);
    if (response && (response.sales.length > 0 || response.prospects.length)) {
        series.value = [
            {
                name: "Ventas",
                data: response.sales.map((item) => ({
                    x: item.date,
                    y: item.sales,
                })),
            },
            {
                name: "Prospectos",
                data: response.prospects.map((item) => ({
                    x: item.date,
                    y: item.prospects,
                })),
            },
        ];
    } else {
        setDefaultValues();
    }
    showLoading.value = false;
};

const setDefaultValues = () => {
    series.value = [
        {
            name: "Ventas",
            data: [],
        },
        {
            name: "Prospectos",
            data: [],
        },
    ];
};
</script>
