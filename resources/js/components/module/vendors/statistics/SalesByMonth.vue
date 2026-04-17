<template>
    <chart-card title="Comparativa en ventas contra el mes anterior">
        <template #chart>
            <div
                id="bymonth-chart"
                class="relative-position"
                style="min-height: 365px"
            >
                <apexchart
                    type="line"
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
import { ref, onMounted, defineProps } from "vue";
import ChartCard from "../../../base/card/chart/ChartCard.vue";
import { compareSalesByMonth } from "./helper/request.js";

const props = defineProps({
    id: {
        type: Number,
        default: null,
    },
});

let series = ref([
    {
        name: "Mes anterior",
        data: [],
    },
    {
        name: "Mes actual",
        data: [],
    },
]);
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
const showLoading = ref(false);

onMounted(async () => {
    showLoading.value = true;
    const response = await compareSalesByMonth(props.id);
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
    showLoading.value = false;
});
</script>
