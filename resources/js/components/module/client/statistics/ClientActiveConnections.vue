<template>
    <div class="active-connections-container q-pa-sm">
        <q-table
            title="Estado de Conexión en Tiempo Real"
            :rows="rows"
            :columns="columns"
            row-key="client_name"
            :loading="loading"
            flat
            bordered
            dense
            :dark="darkMode"
            no-data-label="El cliente no tiene sesiones activas actualmente en el MikroTik"
        >
            <!-- Slot para el estado Online -->
            <template v-slot:body-cell-status="props">
                <q-td :props="props">
                    <q-badge color="positive" label="Online" rounded>
                        <q-tooltip>Sesión activa en PPPoE Server</q-tooltip>
                    </q-badge>
                </q-td>
            </template>

            <!-- Slot para Descarga (Velocidad actual + Total) -->
            <template v-slot:body-cell-download="props">
                <q-td :props="props">
                    <div class="column items-end">
                        <div class="text-green text-weight-bolder">
                            <q-icon name="speed" size="xs"/>
                            {{ props.row.speed_out }}
                        </div>
                        <div class="text-caption text-grey-7">
                            Total: {{ props.row.bytes_out }}
                        </div>
                    </div>
                </q-td>
            </template>

            <!-- Slot para Subida (Velocidad actual + Total) -->
            <template v-slot:body-cell-upload="props">
                <q-td :props="props">
                    <div class="column items-end">
                        <div class="text-blue text-weight-bolder">
                            <q-icon name="navigation" size="xs" style="transform: rotate(0deg)"/>
                            {{ props.row.speed_in }}
                        </div>
                        <div class="text-caption text-grey-7">
                            Total: {{ props.row.bytes_in }}
                        </div>
                    </div>
                </q-td>
            </template>

            <!-- Slot para IP -->
            <template v-slot:body-cell-address="props">
                <q-td :props="props">
                    <q-chip dense square color="grey-2" text-color="black" size="sm">
                        <q-icon name="lan" size="xs" class="q-mr-xs"/>
                        {{ props.value }}
                    </q-chip>
                </q-td>
            </template>

            <!-- Slot para NAS -->
            <template v-slot:body-cell-nas="props">
                <q-td :props="props">
                    <q-badge outline color="brown-5" icon="router">
                        {{ props.value }}
                    </q-badge>
                </q-td>
            </template>

            <!-- Slot para Radius -->
            <template v-slot:body-cell-radius="props">
                <q-td :props="props" class="text-center">
                    <q-icon
                        :name="props.value === 'true' ? 'check_circle' : 'report_problem'"
                        :color="props.value === 'true' ? 'blue' : 'orange'"
                        size="sm"
                    >
                        <q-tooltip>
                            {{
                                props.value === 'true' ? 'Reportando consumo (RADIUS)' : 'Sesión Local (No reporta consumo)'
                            }}
                        </q-tooltip>
                    </q-icon>
                </q-td>
            </template>

            <!-- Slot para Uptime -->
            <template v-slot:body-cell-uptime="props">
                <q-td :props="props">
                    <div class="column items-center">
                        <q-chip outline color="indigo-6" size="sm" icon="timer">
                            {{ props.value }}
                        </q-chip>
                        <div class="text-grey-6" style="font-size: 10px">Desde: {{ props.row.start_at }}</div>
                    </div>
                </q-td>
            </template>

            <!-- Botón de Refresh -->
            <template v-slot:top-right>
                <q-btn
                    flat
                    round
                    color="primary"
                    icon="refresh"
                    @click="fetchData"
                    :loading="loading"
                >
                    <q-tooltip>Actualizar Tráfico Live</q-tooltip>
                </q-btn>
            </template>
        </q-table>
    </div>
</template>

<script>
import {onMounted, ref} from "vue";
import axios from "axios";
import {darkMode} from "../../../../hook/appConfig.js";

export default {
    name: "ClientActiveConnections",
    props: {
        clientId: {type: [String, Number], required: true}
    },
    setup(props) {
        const rows = ref([]);
        const loading = ref(false);

        const columns = [
            {name: 'status', label: 'Estado', align: 'left', field: 'status'},
            {name: 'client_name', label: 'Usuario', field: 'client_name', align: 'left'},
            {name: 'uptime', label: 'Conexión / Uptime', field: 'uptime', align: 'center'},
            {name: 'download', label: 'Tráfico Bajada', field: 'speed_out', align: 'right'},
            {name: 'upload', label: 'Tráfico Subida', field: 'speed_in', align: 'right'},
            {name: 'address', label: 'IP Asignada', field: 'ip_assigned', align: 'center'},
            {name: 'radius', label: 'Radius', field: 'radius', align: 'center'},
            {name: 'nas', label: 'Router (NAS)', field: 'nas', align: 'center'},
        ];

        const fetchData = async () => {
            loading.value = true;
            try {
                const response = await axios.get(`/cliente/statistics/get-active-connections/${props.clientId}`);
                if (response.data && response.data.success) {
                    rows.value = response.data.data;
                } else {
                    rows.value = Array.isArray(response.data) ? response.data : [];
                }
            } catch (error) {
                console.error("Error al obtener conexiones activas:", error);
                rows.value = [];
            } finally {
                loading.value = false;
            }
        };

        onMounted(fetchData);

        return {rows, columns, loading, darkMode, fetchData};
    }
};
</script>

<style scoped>
.active-connections-container {
    width: 100%;
}

/* Ajuste para que las celdas de tráfico no sean tan anchas */
.q-td {
    white-space: nowrap;
}
</style>
