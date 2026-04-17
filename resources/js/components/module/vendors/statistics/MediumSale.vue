<template>
    <chart-card title="Medios de venta">
        <template #chart>
            <label class="form-label my-1">Filtrar por rango de fecha</label>
            <div
                id="medium-chart"
                class="relative-position"
                style="height: 365px"
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
                >
                </VueDatePicker>
                <apexchart
                    type="pie"
                    height="300"
                    :options="chartOptions"
                    :series="series"
                    v-if="series.length > 0"
                ></apexchart>
                <p v-if="!showLoading && series.length === 0">
                    No existen datos
                </p>
                <q-inner-loading :showing="showLoading" color="primary" />
            </div>
        </template>
    </chart-card>
</template>

<script setup>
import { ref, onMounted, defineProps, watch } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import ChartCard from "../../../base/card/chart/ChartCard.vue";
import { salesByMedium } from "./helper/request.js";
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
    colors: [
        "#008FFB",
        "#00E396",
        "#FEB019",
        "#FF4560",
        "#775DD0",
        "#546E7A",
        "#26a69a",
        "#D10CE8",
    ],
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

onMounted(async () => {
    getData();
});

watch(date, () => {
    getData();
});

const getData = async () => {
    showLoading.value = true;
    const response = await salesByMedium(props.id, date.value);
    if (response && response.length > 0) {
        const newLabels = [];
        const newSeries = [];
        response.forEach((item) => {
            newLabels.push(item.name);
            newSeries.push(item.total);
        });
        series.value = newSeries;
        chartOptions.value = {
            ...chartOptions.value,
            labels: newLabels,
        };
    } else {
        series.value = [];
    }
    showLoading.value = false;
};
</script>
