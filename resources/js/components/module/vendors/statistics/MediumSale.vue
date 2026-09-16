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
                    type="donut"
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
import { ref, computed, onMounted, defineProps, watch } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import ChartCard from "../../../base/card/chart/ChartCard.vue";
import { salesByMedium } from "./helper/request.js";
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
const labels = ref([]);

// Paleta vibrante y armónica (colores distintos entre slices adyacentes; los dos primeros
// —slices más grandes— contrastan bien juntos, en vez del teal+azul apagado anterior).
const PALETTE = [
    "#6366F1", // indigo
    "#F59E0B", // ámbar
    "#EC4899", // rosa
    "#10B981", // esmeralda
    "#06B6D4", // cian
    "#8B5CF6", // violeta
    "#EF4444", // rojo
    "#84CC16", // lima
];

// "Rounded Spaced Donut": el stroke grueso del color de la superficie crea el ESPACIADO
// entre segmentos; el donut deja el hueco central con el total. Se adapta a claro/oscuro.
const chartOptions = computed(() => {
    const ink = darkMode.value ? "#e8edf6" : "#111827";
    const muted = darkMode.value ? "#9aa7bd" : "#6b7280";
    return {
        chart: {
            type: "donut",
            fontFamily:
                "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
            foreColor: ink,
        },
        labels: labels.value,
        colors: PALETTE,
        stroke: {
            // v7: los slices REDONDEADOS y ESPACIADOS los dan pie.borderRadius/spacing,
            // no el stroke → sin borde, igual que el demo oficial "Rounded Spaced".
            width: 0,
        },
        plotOptions: {
            pie: {
                borderRadius: 8,
                spacing: 3,
                expandOnClick: false,
                donut: {
                    size: "62%",
                    labels: {
                        show: true,
                        name: { fontSize: "13px", color: muted },
                        value: {
                            fontSize: "22px",
                            fontWeight: 700,
                            color: ink,
                        },
                        total: {
                            show: true,
                            label: "Total",
                            color: muted,
                            formatter: (w) =>
                                w.globals.seriesTotals.reduce(
                                    (a, b) => a + b,
                                    0
                                ),
                        },
                    },
                },
            },
        },
        dataLabels: {
            enabled: true,
            formatter: (val) => `${Math.round(val)}%`,
            style: { fontSize: "12px", fontWeight: 600 },
            dropShadow: { enabled: false },
        },
        legend: {
            position: "bottom",
            fontSize: "13px",
            labels: { colors: ink },
            markers: { radius: 12 },
        },
        tooltip: { theme: darkMode.value ? "dark" : "light" },
    };
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
        labels.value = newLabels;
    } else {
        series.value = [];
        labels.value = [];
    }
    showLoading.value = false;
};
</script>
