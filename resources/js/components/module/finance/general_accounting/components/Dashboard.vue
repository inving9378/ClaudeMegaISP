<template>
    <div class="dashboard-container">
        <!-- Filtro de fechas barra -->
        <div class="filter-group">
            <VueDatePicker
                v-model="barDateRange"
                range
                format="yyyy-MM-dd"
                placeholder="Rango para gráfico de barras"
            />
            <button @click="getBarData" class="btn btn-primary">Filtrar</button>
        </div>

        <div class="chart-box">
            <h4>Ingresos vs Gastos por Mes</h4>
            <apexchart
                type="bar"
                height="350"
                :options="barChartOptions"
                :series="barSeries"
            />
        </div>

        <!-- Filtro de fechas dona -->
        <div class="filter-group">
            <VueDatePicker
                v-model="donutDateRange"
                range
                format="yyyy-MM-dd"
                placeholder="Rango para gráfico de dona"
            />
            <button @click="getDonutData" class="btn btn-primary">
                Filtrar
            </button>
        </div>

        <div class="chart-box">
            <h4>Distribución Total(Por defecto mes actual)</h4>
            <apexchart
                type="donut"
                height="350"
                :options="donutChartOptions"
                :series="donutSeries"
            />
        </div>
    </div>
</template>

<script>
import { ref, onMounted } from "vue";
import VueApexCharts from "vue3-apexcharts";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import Swal from "sweetalert2";
import axios from "axios";
import { format, startOfMonth, endOfMonth } from "date-fns";

export default {
    components: { apexchart: VueApexCharts, VueDatePicker },
    setup() {
        const barDateRange = ref(null);
        const donutDateRange = ref([
            startOfMonth(new Date()),
            endOfMonth(new Date()),
        ]);

        const barSeries = ref([]);
        const barChartOptions = ref({
            chart: { type: "bar", stacked: false },
            xaxis: { categories: [] },
            colors: ["#28a745", "#dc3545"],
            legend: { position: "top" },
        });

        const donutSeries = ref([]);
        const donutChartOptions = ref({
            labels: ["Ingresos", "Gastos"],
            colors: ["#28a745", "#dc3545"],
            legend: { position: "bottom" },
            plotOptions: {
                pie: {
                    donut: {
                        size: "70%",
                        labels: {
                            show: true,
                            total: { show: true, label: "Total" },
                        },
                    },
                },
            },
        });

        const getBarData = async () => {
            try {
                let params = {};
                if (barDateRange.value?.length === 2) {
                    params.start_date = format(
                        new Date(barDateRange.value[0]),
                        "yyyy-MM-dd"
                    );
                    params.end_date = format(
                        new Date(barDateRange.value[1]),
                        "yyyy-MM-dd"
                    );
                }
                const { data } = await axios.get(
                    "/finanzas/general-accounting/get-bar-data",
                    { params }
                );
                if (data.success) {
                    barSeries.value = [
                        {
                            name: "Ingresos",
                            data: (data.bar.ingresos || []).map(
                                (v) => parseFloat(v) || 0
                            ),
                        },
                        {
                            name: "Gastos",
                            data: (data.bar.egresos || []).map(
                                (v) => parseFloat(v) || 0
                            ),
                        },
                    ];
                    barChartOptions.value.xaxis.categories = (
                        data.categories || []
                    ).map((c) => String(c));
                }
            } catch (error) {
                Swal.fire(
                    "Error",
                    error.response?.data?.message || "Error inesperado",
                    "error"
                );
            }
        };

        const getDonutData = async () => {
            try {
                let params = {};
                if (donutDateRange.value?.length === 2) {
                    params.start_date = format(
                        new Date(donutDateRange.value[0]),
                        "yyyy-MM-dd"
                    );
                    params.end_date = format(
                        new Date(donutDateRange.value[1]),
                        "yyyy-MM-dd"
                    );
                }
                const { data } = await axios.get(
                    "/finanzas/general-accounting/get-donut-data",
                    { params }
                );
                if (data.success) {
                    donutSeries.value = [
                        parseFloat(data.donut.ingresos) || 0,
                        parseFloat(data.donut.egresos) || 0,
                    ];
                }
            } catch (error) {
                Swal.fire(
                    "Error",
                    error.response?.data?.message || "Error inesperado",
                    "error"
                );
            }
        };

        onMounted(() => {
            getBarData();
            getDonutData();
        });

        return {
            barDateRange,
            donutDateRange,
            barSeries,
            barChartOptions,
            donutSeries,
            donutChartOptions,
            getBarData,
            getDonutData,
        };
    },
};
</script>

<style scoped>
.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    max-width: 400px;
}

.filter-group .dp__main {
    flex: 1;
    min-width: 220px;
}

.filter-group button {
    white-space: nowrap;
    padding: 6px 14px;
}

.chart-box {
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
}
</style>
