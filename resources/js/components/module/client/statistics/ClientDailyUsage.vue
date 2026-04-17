<template>
    <q-card flat bordered class="q-mt-md border-radius-lg shadow-1">
        <!-- Header -->
        <q-card-section class="items-center justify-between q-py-sm bg-white d-flex justify-content-end">
            <div class="items-center d-flex justify-content-start me-3">
                <q-icon name="info_outline" color="grey-8" size="20px" class="q-mr-sm"/>
                <div class="text-subtitle2 text-weight-bold text-grey-9">Uso por día</div>
            </div>

            <div class="row q-gutter-x-xs">
                <q-btn
                    flat dense icon="bar_chart"
                    :color="viewMode === 'chart' ? 'primary' : 'grey-7'"
                    :class="viewMode === 'chart' ? 'bg-blue-1' : 'border-grey'"
                    @click="viewMode = 'chart'"
                />
                <q-btn
                    flat dense icon="grid_view"
                    :color="viewMode === 'table' ? 'primary' : 'grey-7'"
                    :class="viewMode === 'table' ? 'bg-blue-1' : 'border-grey'"
                    @click="viewMode = 'table'"
                />
            </div>
        </q-card-section>

        <q-separator/>

        <q-card-section class="q-pa-none">
            <div v-if="loading" class="flex flex-center" style="min-height: 350px">
                <q-spinner-bars color="indigo-6" size="40px"/>
            </div>

            <template v-else>
                <!-- VISTA DE GRÁFICO -->
                <div v-if="viewMode === 'chart'" class="q-pa-md">
                    <apexchart
                        type="bar"
                        height="350"
                        :options="chartOptions"
                        :series="series"
                    ></apexchart>
                </div>

                <!-- VISTA DE TABLA -->
                <div v-else class="table-container">
                    <q-table
                        :rows="tableRows"
                        :columns="tableColumns"
                        row-key="fecha"
                        flat
                        dense
                        :pagination="{ rowsPerPage: 0 }"
                        hide-pagination
                        class="daily-usage-table"
                        :dark="darkMode"
                    >
                        <!-- Fila de Totales al final -->
                        <template v-slot:bottom-row>
                            <q-tr class="bg-grey-2 text-weight-bold">
                                <q-td>Total</q-td>
                                <q-td class="text-right">{{ totalDownload }}</q-td>
                                <q-td class="text-right">{{ totalUpload }}</q-td>
                                <q-td colspan="2"></q-td>
                            </q-tr>
                        </template>
                    </q-table>
                </div>
            </template>
        </q-card-section>
    </q-card>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import VueApexCharts from "vue3-apexcharts";
import { darkMode } from "../../../../hook/appConfig.js";

export default {
    name: 'ClientDailyUsage',
    components: { apexchart: VueApexCharts },
    props: ['clientId'],
    setup(props) {
        const loading = ref(false);
        const viewMode = ref('chart');
        const rawData = ref({ labels: [], download: [], upload: [] });

        // 1. PRIMERO DEFINIMOS EL FORMATEADOR (Importante para que los computed lo vean)
        const formatValue = (val) => {
            const num = parseFloat(val) || 0;
            if (num >= 1024) return (num / 1024).toFixed(2) + " GB";
            return num.toFixed(2) + " MB";
        };

        const tableColumns = [
            { name: 'fecha', label: 'Fecha', field: 'fecha', align: 'left' },
            { name: 'down', label: 'Descargar pico', field: 'downFormatted', align: 'right' },
            { name: 'up', label: 'Pico de subida', field: 'upFormatted', align: 'right' },
            { name: 'peak', label: 'Descarga Max.', field: 'peak', align: 'left' },
            { name: 'offpeak', label: 'Subida Max.', field: 'offpeak', align: 'left' },
        ];

        // 2. COMPUTED PROPERTIES
        const tableRows = computed(() => {
            if (!rawData.value.labels) return [];
            return rawData.value.labels.map((label, index) => ({
                fecha: label,
                downFormatted: formatValue(rawData.value.download[index]),
                upFormatted: formatValue(rawData.value.upload[index]),
                peak: '---',
                offpeak: '---'
            }));
        });

        const totalDownload = computed(() => {
            const total = rawData.value.download.reduce((a, b) => parseFloat(a) + parseFloat(b), 0);
            return formatValue(total);
        });

        const totalUpload = computed(() => {
            const total = rawData.value.upload.reduce((a, b) => parseFloat(a) + parseFloat(b), 0);
            return formatValue(total);
        });

        const series = computed(() => [
            { name: 'Download peak', data: rawData.value.download.map(v => parseFloat(v)) },
            { name: 'Upload peak', data: rawData.value.upload.map(v => parseFloat(v)) }
        ]);

        const chartOptions = ref({
            chart: { type: 'bar', stacked: true, toolbar: { show: false } },
            colors: ['#f2c037', '#7ab1e8'],
            plotOptions: { bar: { horizontal: false, columnWidth: '55%' } },
            xaxis: {
                categories: [],
                labels: { rotate: -45, style: { fontSize: '10px' } }
            },
            yaxis: {
                labels: {
                    formatter: (val) => val >= 1024 ? (val / 1024).toFixed(1) + " GB" : val.toFixed(0) + " MB"
                }
            },
            legend: { position: 'top' },
        });

        const fetchData = async () => {
            loading.value = true;
            try {
                const res = await axios.get(`/cliente/statistics/get-daily-usage/${props.clientId}`);
                if (res.data.success) {
                    // Aseguramos que los datos sean números para que el reduce no concatene strings
                    rawData.value = {
                        labels: res.data.labels || [],
                        download: (res.data.download || []).map(v => parseFloat(v)),
                        upload: (res.data.upload || []).map(v => parseFloat(v))
                    };

                    // Actualización reactiva de las categorías del gráfico
                    chartOptions.value = {
                        ...chartOptions.value,
                        xaxis: {
                            ...chartOptions.value.xaxis,
                            categories: res.data.labels
                        }
                    };
                }
            } catch (e) {
                console.error("Error fetching daily usage:", e);
            } finally {
                loading.value = false;
            }
        };

        onMounted(fetchData);

        return {
            series, chartOptions, loading, viewMode,
            tableRows, tableColumns, totalDownload, totalUpload,
            fetchData, darkMode
        };
    }
};
</script>

<style scoped>
.border-radius-lg { border-radius: 8px; }
.border-grey { border: 1px solid #e0e0e0; border-radius: 4px; }
.bg-blue-1 { background-color: #e3f2fd !important; border: 1px solid #2196f3 !important; }

.daily-usage-table :deep(thead tr th) {
    font-weight: bold;
    color: #555;
    background-color: #fdfdfd;
}

.daily-usage-table :deep(tbody tr:nth-of-type(even)) {
    background-color: #fafafa;
}
</style>
