<template>
    <div class="row q-col-gutter-md q-pa-md">

        <!-- TARJETA IZQUIERDA: TOTALES -->
        <div class="col-12 col-md-4">
            <q-card flat bordered class="full-height border-radius-lg shadow-1">
                <q-card-section class="items-center q-py-sm bg-grey-1 d-flex justify-content-end">
                    <q-icon name="info_outline" color="grey-8" size="20px" class="q-mr-sm"/>
                    <div class="text-subtitle2 text-weight-bold text-grey-9">Total del período</div>
                </q-card-section>

                <q-separator/>

                <q-card-section class="q-pa-none">
                    <q-list dense>
                        <q-item v-for="(val, label) in summaryList" :key="label" class="q-py-md border-bottom">
                            <q-item-section class="text-grey-8 text-weight-medium">{{ label }}</q-item-section>
                            <q-item-section side class="text-weight-bold text-black">{{ val }}</q-item-section>
                        </q-item>
                    </q-list>
                </q-card-section>
            </q-card>
        </div>

        <!-- TARJETA DERECHA: GRÁFICO -->
        <div class="col-12 col-md-8">
            <q-card flat bordered class="border-radius-lg shadow-1">

                <!-- CABECERA -->
                <q-card-section class="items-center justify-between q-py-sm q-px-md bg-grey-1">
                    <div class="items-center no-wrap q-gutter-x-sm d-flex justify-content-end">
                        <span class="text-caption text-grey-7">Tipo de estadísticas:</span>
                        <q-select
                            v-model="graphType"
                            :options="['Gráfico diario', 'Gráfico mensual']"
                            outlined
                            dense
                            bg-color="white"
                            style="min-width: 160px"
                            options-dense
                            hide-bottom-space
                            @update:model-value="fetchData"
                        />
                        <q-btn
                            icon="refresh"
                            flat
                            round
                            color="grey-7"
                            size="sm"
                            @click="fetchData"
                        >
                            <q-tooltip>Recargar datos</q-tooltip>
                        </q-btn>
                    </div>
                </q-card-section>

                <q-separator/>

                <!-- CUERPO DEL GRÁFICO -->
                <q-card-section class="q-pa-md">
                    <div v-if="loading" class="flex flex-center" style="min-height: 250px">
                        <q-spinner-dots color="primary" size="40px"/>
                    </div>

                    <div v-else-if="hasData" style="min-height: 250px">
                        <apexchart
                            :key="chartKey"
                            type="area"
                            height="250"
                            :options="chartOptions"
                            :series="series"
                        ></apexchart>
                    </div>

                    <div v-else class="flex flex-center" style="min-height: 250px">
                        <div class="full-width q-pa-md bg-orange-1 border-orange text-center rounded-borders shadow-sm">
                            <div class="text-weight-bold text-orange-9 text-body1">¡Atención!</div>
                            <div class="text-orange-8">Los gráficos no están disponibles para este periodo.</div>
                        </div>
                    </div>
                </q-card-section>
            </q-card>
        </div>
    </div>
</template>

<script>
import {ref, computed, onMounted} from 'vue';
import axios from 'axios';
import VueApexCharts from "vue3-apexcharts";

export default {
    components: {
        apexchart: VueApexCharts,
    },
    props: {
        clientId: {type: [String, Number], required: true}
    },
    setup(props) {
        const chartKey = ref(0);
        const graphType = ref('Gráfico diario');
        const loading = ref(false);
        const serverData = ref(null);

        const series = ref([]);
        const chartOptions = ref({
            chart: {id: 'consumption-chart', toolbar: {show: false}, fontFamily: 'inherit'},
            colors: ['#2196F3', '#FF9800'],
            dataLabels: {enabled: false},
            stroke: {curve: 'smooth', width: 3},
            xaxis: {categories: [], labels: {style: {colors: '#9e9e9e'}}},
            yaxis: {
                labels: {
                    style: {colors: '#9e9e9e'},
                    formatter: (val) => val ? val.toFixed(2) + ' GB' : '0 GB'
                }
            },
            legend: {position: 'top', horizontalAlign: 'right'},
            fill: {
                type: 'gradient',
                gradient: {shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05}
            },
            tooltip: {y: {formatter: (val) => val.toFixed(3) + " GB"}}
        });

        const summaryList = computed(() => {
            const d = serverData.value || {};
            return {
                'Sesión': d.sessions || 0,
                'Errores': d.errors || 0,
                'Tiempo': d.time || '00:00:00',
                'Descargar': d.download || '0 GB',
                'Registro': d.upload || '0 GB'
            };
        });

        const hasData = computed(() => {
            return serverData.value &&
                serverData.value.graph_data &&
                serverData.value.graph_data.labels.length > 0;
        });

        const fetchData = async () => {
            loading.value = true;
            try {
                const response = await axios.get(`/cliente/statistics/get-consumption-summary/${props.clientId}`);
                const res = response.data.data;
                serverData.value = res;

                if (res && res.graph_data) {
                    series.value = [
                        {name: 'Descarga (Down)', data: res.graph_data.download},
                        {name: 'Subida (Up)', data: res.graph_data.upload}
                    ];
                    chartOptions.value = {
                        ...chartOptions.value,
                        xaxis: {...chartOptions.value.xaxis, categories: res.graph_data.labels}
                    };
                    chartKey.value++;
                }
            } catch (e) {
                console.error("Error al cargar estadísticas:", e);
                serverData.value = null;
            } finally {
                loading.value = false;
            }
        };

        onMounted(fetchData);

        return {graphType, summaryList, hasData, fetchData, loading, series, chartOptions, chartKey};
    }
};
</script>

<style scoped>
.border-radius-lg {
    border-radius: 12px;
}

.border-bottom {
    border-bottom: 1px solid #f0f0f0;
}

.border-orange {
    border: 1px solid #ffcc80;
}

.bg-orange-1 {
    background-color: #fff3e0 !important;
}

.q-item:hover {
    background-color: #fafafa;
}
</style>
