<template>
    <q-card v-if="isDedicated" flat bordered class="q-mt-md border-radius-lg shadow-1">
        <!-- Header -->
        <q-card-section class="items-center justify-between q-py-sm bg-white d-flex justify-content-end">
            <div class="items-center d-flex justify-content-start me-3">
                <q-icon name="network_ping" color="grey-8" size="20px" class="q-mr-sm"/>
                <div class="text-subtitle2 text-weight-bold text-grey-9">Ping / Latencia</div>
                <q-badge
                    v-if="latestStatus"
                    :color="statusColor(latestStatus)"
                    class="q-ml-sm"
                    :label="latestStatus.toUpperCase()"
                />
            </div>
            <q-btn flat dense icon="refresh" color="grey-7" @click="fetchAll" :loading="loading"/>
        </q-card-section>

        <q-separator/>

        <q-card-section>
            <div v-if="loading" class="flex flex-center" style="min-height: 200px">
                <q-spinner-bars color="indigo-6" size="40px"/>
            </div>

            <template v-else>
                <!-- Tarjetas resumen -->
                <div class="row q-col-gutter-sm q-mb-md">
                    <div class="col-6 col-sm-3">
                        <q-card flat bordered class="text-center q-pa-sm">
                            <div class="text-caption text-grey-6">Avg latencia</div>
                            <div class="text-h6 text-weight-bold text-indigo-7">
                                {{ latest?.avg_ms != null ? latest.avg_ms + ' ms' : '—' }}
                            </div>
                            <div class="text-caption text-grey-5">último ping</div>
                        </q-card>
                    </div>
                    <div class="col-6 col-sm-3">
                        <q-card flat bordered class="text-center q-pa-sm">
                            <div class="text-caption text-grey-6">Min / Max</div>
                            <div class="text-h6 text-weight-bold text-teal-7">
                                {{ latest?.min_ms != null ? latest.min_ms : '—' }}
                                <span class="text-grey-5 text-body2">/</span>
                                {{ latest?.max_ms != null ? latest.max_ms + ' ms' : '—' }}
                            </div>
                            <div class="text-caption text-grey-5">último ping</div>
                        </q-card>
                    </div>
                    <div class="col-6 col-sm-3">
                        <q-card flat bordered class="text-center q-pa-sm">
                            <div class="text-caption text-grey-6">Pérdida paquetes</div>
                            <div
                                class="text-h6 text-weight-bold"
                                :class="(latest?.packet_loss ?? 0) > 0 ? 'text-negative' : 'text-positive'"
                            >
                                {{ latest?.packet_loss != null ? latest.packet_loss + ' %' : '—' }}
                            </div>
                            <div class="text-caption text-grey-5">último ping</div>
                        </q-card>
                    </div>
                    <div class="col-6 col-sm-3">
                        <q-card flat bordered class="text-center q-pa-sm">
                            <div class="text-caption text-grey-6">Uptime hoy</div>
                            <div
                                class="text-h6 text-weight-bold"
                                :class="(today?.uptime_percent ?? 100) >= 99 ? 'text-positive' : (today?.uptime_percent ?? 100) >= 95 ? 'text-warning' : 'text-negative'"
                            >
                                {{ today?.uptime_percent != null ? today.uptime_percent + ' %' : '—' }}
                            </div>
                            <div class="text-caption text-grey-5">
                                {{ today ? today.down_checks + ' caídas de ' + today.total_checks : 'sin datos' }}
                            </div>
                        </q-card>
                    </div>
                </div>

                <!-- Gráfica mensual -->
                <div class="q-mb-md">
                    <div class="text-caption text-grey-6 q-mb-xs">Latencia promedio — mes actual</div>
                    <apexchart
                        v-if="chartSeries[0].data.length > 0"
                        type="line"
                        height="220"
                        :options="chartOptions"
                        :series="chartSeries"
                    />
                    <div v-else class="text-center text-grey-5 q-py-md">Sin datos de gráfica este mes</div>
                </div>

                <q-separator class="q-mb-md"/>

                <!-- Tabla historial 48h -->
                <div class="text-caption text-grey-6 q-mb-xs">Historial últimas 48 horas</div>
                <q-table
                    :rows="history"
                    :columns="columns"
                    row-key="recorded_at"
                    flat
                    dense
                    :pagination="pagination"
                    :loading="loadingHistory"
                    :dark="darkMode"
                    class="ping-history-table"
                >
                    <template v-slot:body-cell-status="props">
                        <q-td :props="props">
                            <q-badge :color="statusColor(props.row.status)" :label="props.row.status"/>
                        </q-td>
                    </template>
                    <template v-slot:body-cell-packet_loss="props">
                        <q-td :props="props">
                            <span :class="props.row.packet_loss > 0 ? 'text-negative text-weight-bold' : 'text-positive'">
                                {{ props.row.packet_loss }} %
                            </span>
                        </q-td>
                    </template>
                </q-table>

                <div v-if="lastPage > 1" class="flex flex-center q-mt-sm">
                    <q-pagination
                        v-model="currentPage"
                        :max="lastPage"
                        boundary-numbers
                        @update:model-value="fetchHistory"
                    />
                </div>
            </template>
        </q-card-section>
    </q-card>
</template>

<script>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import VueApexCharts from 'vue3-apexcharts';
import { darkMode } from '../../../../hook/appConfig.js';

export default {
    name: 'ClientPingStatus',
    components: { apexchart: VueApexCharts },
    props: ['clientId'],
    setup(props) {
        const loading        = ref(false);
        const loadingHistory = ref(false);
        const isDedicated    = ref(false);

        const latest      = ref(null);
        const today       = ref(null);
        const dailyData   = ref([]);
        const history     = ref([]);
        const currentPage = ref(1);
        const lastPage    = ref(1);
        const pagination  = ref({ rowsPerPage: 50 });

        const latestStatus = computed(() => latest.value?.status ?? null);

        const statusColor = (status) => {
            if (status === 'up')      return 'positive';
            if (status === 'down')    return 'negative';
            if (status === 'timeout') return 'warning';
            return 'grey';
        };

        // ── Gráfica ──────────────────────────────────────────────
        const chartSeries = computed(() => [{
            name: 'Avg ms',
            data: dailyData.value.map(d => d.avg_ms ?? 0),
        }]);

        const chartOptions = ref({
            chart: { type: 'line', toolbar: { show: false }, zoom: { enabled: false } },
            stroke: { curve: 'smooth', width: 2 },
            colors: ['#5c6bc0'],
            markers: { size: 3 },
            xaxis: { categories: [], labels: { rotate: -45, style: { fontSize: '10px' } } },
            yaxis: { labels: { formatter: v => v.toFixed(1) + ' ms' } },
            tooltip: { y: { formatter: v => v.toFixed(2) + ' ms' } },
            grid: { borderColor: '#f0f0f0' },
        });

        // ── Columnas tabla ────────────────────────────────────────
        const columns = [
            { name: 'recorded_at', label: 'Hora',     field: 'recorded_at', align: 'left',   sortable: true },
            { name: 'ip_address',  label: 'IP',        field: 'ip_address',  align: 'left' },
            { name: 'avg_ms',      label: 'Avg ms',    field: 'avg_ms',      align: 'right',  sortable: true },
            { name: 'min_ms',      label: 'Min ms',    field: 'min_ms',      align: 'right' },
            { name: 'max_ms',      label: 'Max ms',    field: 'max_ms',      align: 'right' },
            { name: 'jitter_ms',   label: 'Jitter ms', field: 'jitter_ms',   align: 'right' },
            { name: 'packet_loss', label: 'Pérdida %', field: 'packet_loss', align: 'right',  sortable: true },
            { name: 'status',      label: 'Estado',    field: 'status',      align: 'center', sortable: true },
        ];

        // ── Fetch ──────────────────────────────────────────────────
        const fetchStatus = async () => {
            const res = await axios.get(`/cliente/statistics/get-ping-status/${props.clientId}`);
            isDedicated.value = res.data.is_dedicated;
            if (res.data.is_dedicated) {
                latest.value = res.data.latest;
                today.value  = res.data.today;
            }
        };

        const fetchDaily = async () => {
            const res = await axios.get(`/cliente/statistics/get-ping-daily/${props.clientId}`);
            if (res.data.is_dedicated) {
                dailyData.value = res.data.data;
                chartOptions.value = {
                    ...chartOptions.value,
                    xaxis: { ...chartOptions.value.xaxis, categories: res.data.data.map(d => d.date) },
                };
            }
        };

        const fetchHistory = async () => {
            loadingHistory.value = true;
            try {
                const res = await axios.get(`/cliente/statistics/get-ping-history/${props.clientId}`, {
                    params: { page: currentPage.value },
                });
                if (res.data.is_dedicated) {
                    history.value  = res.data.data;
                    lastPage.value = res.data.last_page;
                }
            } finally {
                loadingHistory.value = false;
            }
        };

        const fetchAll = async () => {
            loading.value = true;
            try {
                await Promise.all([fetchStatus(), fetchDaily(), fetchHistory()]);
            } catch (e) {
                console.error('ClientPingStatus error:', e);
            } finally {
                loading.value = false;
            }
        };

        onMounted(fetchAll);

        return {
            loading, loadingHistory, isDedicated,
            latest, today, latestStatus,
            dailyData, chartSeries, chartOptions,
            history, columns, pagination, currentPage, lastPage,
            statusColor, fetchAll, fetchHistory,
            darkMode,
        };
    },
};
</script>

<style scoped>
.border-radius-lg { border-radius: 8px; }

.ping-history-table :deep(thead tr th) {
    font-weight: bold;
    color: #555;
    background-color: #fdfdfd;
}
.ping-history-table :deep(tbody tr:nth-of-type(even)) {
    background-color: #fafafa;
}
</style>
