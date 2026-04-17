<template>
    <chart-card title="Medios de venta">
        <template #chart>
            <div id="medium-chart" style="height: 430px">
                <div class="mb-3">
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
                    type="pie"
                    height="300"
                    :options="chartOptions"
                    :series="series"
                ></apexchart>
            </div>
        </template>
    </chart-card>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { salesByMedium } from "../helper/request.js";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import ChartCard from "../../../base/card/chart/ChartCard.vue";

const date = ref();
const series = ref([]);
const chartOptions = ref({
    labels: [],
    fill: {
        type: "gradient",
    },
    responsive: [
        {
            breakpoint: 200,
            options: {
                chart: {
                    width: 200,
                },
                legend: {
                    position: "bottom",
                },
            },
        },
    ],
});

onMounted(async () => {
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);
    const endDate = new Date();
    date.value = [startDate, endDate];

    getData();
});

const getData = async () => {
    try {
        const response = await salesByMedium(
            date.value[0].toISOString().slice(0, 10),
            date.value[1].toISOString().slice(0, 10)
        );
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
        }
    } catch (error) {
        console.log(error);
    }
};
</script>
