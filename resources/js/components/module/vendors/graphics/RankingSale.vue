<template>
    <chart-card title="Ranking de ventas por vendedores">
        <template #chart>
            <div id="sales-chart" style="min-height: 365px">
                <div>
                    <label class="form-label my-1"
                        >Filtrar por rango de fecha</label
                    >
                    <div class="d-flex gap-3">
                        <VueDatePicker
                            v-model="date"
                            position="left"
                            locale="es"
                            :max-date="new Date()"
                            :teleport="true"
                            placeholder="Selecciona un rango de fecha"
                            range
                            multi-calendars
                        >
                        </VueDatePicker>
                        <button
                            type="button"
                            class="btn btn-primary"
                            @click="getData"
                        >
                            Buscar
                        </button>
                    </div>
                </div>
                <apexchart
                    type="bar"
                    height="350"
                    :options="chartOptions"
                    :series="series"
                ></apexchart>
            </div>
        </template>
    </chart-card>
</template>

<script setup>
import { ref, onMounted } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import { rankingSales } from "../helper/request.js";
import ChartCard from "../../../base/card/chart/ChartCard.vue";

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

onMounted(() => {
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);
    const endDate = new Date();
    date.value = [startDate, endDate];

    getData();
});

const getData = async () => {
    try {
        const response = await rankingSales(
            date.value[0].toISOString().slice(0, 10),
            date.value[1].toISOString().slice(0, 10)
        );

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
    } catch (error) {
        console.log(error);
    }
};
</script>
