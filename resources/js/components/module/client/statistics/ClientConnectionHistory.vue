<template>
    <q-card flat bordered class="q-mt-md border-radius-lg shadow-1">
        <q-card-section class="items-center justify-between q-py-sm d-flex justify-content-between">
            <div class="items-center q-gutter-x-sm d-flex justify-content-start">
                <span class="text-grey-7">Show</span>
                <q-select
                    v-model="pagination.rowsPerPage"
                    :options="[10, 50, 100, 200]"
                    dense
                    outlined
                    options-dense
                    @update:model-value="fetchHistory"
                />
                <span class="text-grey-7">entries</span>
            </div>

            <q-input
                v-model="filter"
                placeholder="Search"
                dense
                outlined
                @update:model-value="onSearch"
            >
                <template v-slot:append>
                    <q-icon name="search"/>
                </template>
            </q-input>
        </q-card-section>

        <q-table
            :rows="rows"
            :columns="columns"
            row-key="id"
            v-model:pagination="pagination"
            :loading="loading"
            @request="onRequest"
            flat
            bordered
            dense
            binary-state-sort
        >
            <!-- Slot para el ID con # -->
            <template v-slot:body-cell-index="props">
                <q-td :props="props" class="text-blue-7 text-weight-bold">
                    {{ props.row.id }}
                </q-td>
            </template>

            <!-- Resaltar Errores -->
            <template v-slot:body-cell-error="props">
                <q-td :props="props" :class="props.value > 0 ? 'text-red text-weight-bold' : ''">
                    {{ props.value }}
                </q-td>
            </template>

            <!-- Totales al pie de la tabla -->
            <template v-slot:bottom-row>
                <q-tr class="bg-grey-1">
                    <q-td colspan="10" class="text-caption q-pa-md">
                        <div class="text-weight-bold">Total Errors: {{ summaryTotals.errors }}</div>
                        <div class="text-weight-bold">Download MB: {{ summaryTotals.down }}</div>
                        <div class="text-weight-bold">Upload MB: {{ summaryTotals.up }}</div>
                    </q-td>
                </q-tr>
            </template>
        </q-table>
    </q-card>
</template>

<script>
import {ref, onMounted, reactive, computed} from 'vue';
import axios from 'axios';

export default {
    props: ['clientId'],
    setup(props) {
        const rows = ref([]);
        const loading = ref(false);
        const filter = ref('');
        const pagination = ref({
            page: 1,
            rowsPerPage: 100,
            rowsNumber: 0 // Total de registros en la DB
        });

        const columns = [
            {name: 'index', label: '#', field: 'id', align: 'left'},
            {name: 'connected', label: 'Connected', field: 'connected', align: 'left', sortable: true},
            {name: 'disconnected', label: 'Disconnected', field: 'disconnected', align: 'left'},
            {name: 'time', label: 'Time', field: 'time', align: 'center'},
            {name: 'error', label: 'Error', field: 'error', align: 'center'},
            {name: 'down', label: 'Download MB', field: 'down_mb', align: 'right'},
            {name: 'up', label: 'Upload MB', field: 'up_mb', align: 'right'},
            {name: 'ip', label: 'IPv4 address', field: 'ip', align: 'center'},
            {name: 'mac', label: 'MAC', field: 'mac', align: 'left'},
            {name: 'nas', label: 'Access server', field: 'nas', align: 'left'},
        ];

        const summaryTotals = computed(() => {
            return {
                errors: rows.value.reduce((a, b) => a + b.error, 0),
                down: rows.value.reduce((a, b) => a + b.down_mb, 0).toFixed(2),
                up: rows.value.reduce((a, b) => a + b.up_mb, 0).toFixed(2),
            };
        });

        const fetchHistory = async (page = 1) => {
            loading.value = true;
            try {
                const params = {
                    page: page,
                    per_page: pagination.value.rowsPerPage,
                    search: filter.value
                };
                const res = await axios.get(`/cliente/statistics/get-history/${props.clientId}`, {params});
                rows.value = res.data.data.data;
                pagination.value.rowsNumber = res.data.data.total;
                pagination.value.page = res.data.data.current_page;
            } catch (e) {
                console.error(e);
            } finally {
                loading.value = false;
            }
        };

        const onRequest = (props) => {
            pagination.value.rowsPerPage = props.pagination.rowsPerPage;
            fetchHistory(props.pagination.page);
        };

        const onSearch = () => {
            fetchHistory(1);
        };

        onMounted(() => fetchHistory());

        return {rows, columns, loading, pagination, filter, onRequest, fetchHistory, onSearch, summaryTotals};
    }
};
</script>

<style scoped>
.border-radius-lg {
    border-radius: 8px;
}
</style>
