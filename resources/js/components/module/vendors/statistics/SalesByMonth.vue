<template>
    <chart-card title="Comparativa en ventas contra el mes anterior">
        <template #chart>
            <div
                id="bymonth-chart"
                class="relative-position"
                style="min-height: 365px"
            >
                <apexchart
                    type="area"
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
import { ref, computed, onMounted, defineProps } from "vue";
import ChartCard from "../../../base/card/chart/ChartCard.vue";
import { compareSalesByMonth } from "./helper/request.js";
import { darkMode } from "../../../../hook/appConfig.js";

const props = defineProps({
    id: {
        type: Number,
        default: null,
    },
});

const series = ref([
    { name: "Mes anterior", data: [] },
    { name: "Mes actual", data: [] },
]);
const categories = ref([]);
const showLoading = ref(false);

// Spline Area: área suave (stroke.curve: smooth) con gradiente, sobre eje de días del mes.
const chartOptions = computed(() => {
    const ink = darkMode.value ? "#e8edf6" : "#111827";
    const muted = darkMode.value ? "#9aa7bd" : "#6b7280";
    const line = darkMode.value ? "#2a3550" : "#e5e7eb";
    return {
        chart: {
            id: "salesbymonth-chart",
            type: "area",
            fontFamily:
                "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
            foreColor: ink,
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        colors: ["#94a3b8", "#0d9488"], // mes anterior (gris) / mes actual (teal)
        dataLabels: { enabled: false },
        stroke: { curve: "smooth", width: 2 },
        fill: {
            type: "gradient",
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.05,
                stops: [0, 90, 100],
            },
        },
        markers: { size: 0, hover: { size: 5 } },
        xaxis: {
            categories: categories.value,
            title: { text: "Día del mes", style: { color: muted } },
            labels: { style: { colors: muted } },
            axisBorder: { color: line },
            axisTicks: { color: line },
        },
        yaxis: {
            labels: { style: { colors: muted } },
        },
        grid: { borderColor: line, strokeDashArray: 4 },
        legend: {
            position: "top",
            horizontalAlign: "right",
            labels: { colors: ink },
        },
        tooltip: { theme: darkMode.value ? "dark" : "light" },
    };
});

onMounted(async () => {
    showLoading.value = true;
    const response = await compareSalesByMonth(props.id);
    if (response) {
        // Eje X = unión ordenada de los días de ambos meses (antes usaba new Date(dia) = fecha basura).
        const days = new Set();
        (response.previous_month || []).forEach((i) => days.add(Number(i.day)));
        (response.current_month || []).forEach((i) => days.add(Number(i.day)));
        const sortedDays = [...days].sort((a, b) => a - b);

        const prevMap = Object.fromEntries(
            (response.previous_month || []).map((i) => [Number(i.day), i.sales])
        );
        const currMap = Object.fromEntries(
            (response.current_month || []).map((i) => [Number(i.day), i.sales])
        );

        categories.value = sortedDays.map(String);
        series.value = [
            {
                name: "Mes anterior",
                data: sortedDays.map((d) => prevMap[d] ?? 0),
            },
            {
                name: "Mes actual",
                data: sortedDays.map((d) => currMap[d] ?? 0),
            },
        ];
    }
    showLoading.value = false;
});
</script>
