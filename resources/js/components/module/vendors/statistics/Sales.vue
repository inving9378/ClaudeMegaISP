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
import { ref, computed, onMounted, defineProps, watch, onBeforeMount } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import ChartCard from "../../../base/card/chart/ChartCard.vue";
import { salesAndProspects } from "./helper/request.js";
import { useDatePicker } from "../../../../composables/useDatePicker.js";
import { darkMode } from "../../../../hook/appConfig.js";

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

// Zoomable Timeseries: área con gradiente sobre eje de fechas, con zoom/pan y toolbar.
const chartOptions = computed(() => {
    const ink = darkMode.value ? "#e8edf6" : "#111827";
    const muted = darkMode.value ? "#9aa7bd" : "#6b7280";
    const line = darkMode.value ? "#2a3550" : "#e5e7eb";
    return {
        chart: {
            id: "sales-chart",
            type: "area",
            fontFamily:
                "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
            foreColor: ink,
            zoom: { enabled: true, type: "x", autoScaleYaxis: true },
            toolbar: {
                show: true,
                autoSelected: "zoom",
                tools: {
                    download: true,
                    selection: true,
                    zoom: true,
                    zoomin: true,
                    zoomout: true,
                    pan: true,
                    reset: true,
                },
            },
        },
        colors: ["#0d9488", "#2563eb"],
        dataLabels: { enabled: false },
        stroke: { curve: "smooth", width: 2 },
        fill: {
            type: "gradient",
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.35,
                opacityTo: 0.05,
                stops: [0, 90, 100],
            },
        },
        markers: { size: 0, hover: { size: 5 } },
        xaxis: {
            type: "datetime",
            labels: { style: { colors: muted } },
            axisBorder: { color: line },
            axisTicks: { color: line },
        },
        yaxis: {
            title: { text: "Cantidad", style: { color: muted } },
            labels: { style: { colors: muted } },
        },
        grid: { borderColor: line, strokeDashArray: 4 },
        legend: {
            position: "top",
            horizontalAlign: "right",
            labels: { colors: ink },
        },
        tooltip: {
            theme: darkMode.value ? "dark" : "light",
            x: { format: "dd MMM yyyy" },
        },
    };
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
