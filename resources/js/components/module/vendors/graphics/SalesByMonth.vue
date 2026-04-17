<template>
    <chart-card title="Comparativa en ventas contra el mes anterior">
        <template #chart>
            <div id="bymonth-chart" style="min-height: 365px">
                <apexchart
                    type="line"
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
import { compareSalesByMonth } from "../helper/request.js";
import ChartCard from "../../../base/card/chart/ChartCard.vue";

let series = ref([]);
let chartOptions = ref({
    chart: {
        id: "basic-line",
    },
    stroke: {
        curve: "smooth",
    },
    xaxis: {
        categories: [],
    },
});

onMounted(async () => {
    try {
        const response = await compareSalesByMonth();
        if (response) {
            let labels = [];
            let dataPreviousMonth = [];
            let dataCurrentMonth = [];

            response.previous_month.forEach((item) => {
                const date = new Date(item.day);
                if (!labels.includes(date)) {
                    labels.push(date);
                }
                dataPreviousMonth.push(item.sales);
            });

            response.current_month.forEach((item) => {
                const date = new Date(item.day);
                if (!labels.includes(date)) {
                    labels.push(date);
                }
                dataCurrentMonth.push(item.sales);
            });

            series.value = [
                {
                    name: "Mes anterior",
                    data: dataPreviousMonth,
                },
                {
                    name: "Mes actual",
                    data: dataCurrentMonth,
                },
            ];

            chartOptions.value.xaxis.categories = labels;
        }
    } catch (error) {
        console.log(error);
    }
});
</script>
