<template>
    <chart-card title="Ranking de ventas por vendedores">
        <template #chart>
            <label class="form-label my-1">Filtrar por rango de fecha</label>
            <div
                id="sales-chart"
                class="relative-position"
                style="min-height: 365px"
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
import { ref, onMounted, watch } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import { rankingSales } from "./helper/request.js";
import ChartCard from "../../../base/card/chart/ChartCard.vue";
import { useDatePicker } from "../../../../composables/useDatePicker.js";

const { customFormat } = useDatePicker();

const date = ref();
const series = ref([]);
const chartOptions = ref({
    chart: {
        id: "basic-bar",
    },
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
            distributed: true,
            endingShape: "rounded",
            borderRadius: 4,
        },
    },
    xaxis: {
        categories: [],
        labels: {
            style: {
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
                fontSize: "14px",
            },
        },
    },
    yaxis: {
        title: {
            text: "Número de ventas",
        },
    },
});
const showLoading = ref(false);
onMounted(() => {
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);
    const endDate = new Date();
    date.value = [startDate, endDate];
    getData();
});

watch(date, () => {
    getData();
});

const getData = async () => {
    showLoading.value = true;
    const response = await rankingSales(date.value);
    if (response) {
        series.value = [
            {
                name: "Ventas",
                data: response.map((item) => ({
                    x: item.name,
                    y: item.sales,
                })),
            },
        ];
    }
    showLoading.value = false;
};
</script>
