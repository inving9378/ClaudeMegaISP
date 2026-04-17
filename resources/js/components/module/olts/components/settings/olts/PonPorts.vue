<template>
    <q-table
        v-table-resizable="columns"
        :columns="columns"
        :rows="rows"
        :loading="loading"
        :dark="darkMode"
        :filter="filter"
        flat
        row-key="id"
        color="primary"
        loading-label="Obteniendo datos, por favor espere..."
        no-data-label="No existen datos disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Filas por página"
        v-model:pagination="pagination"
        :pagination-label="(start, end, total) => `${start}-${end} de ${total}`"
        :rows-per-page-options="[20, 30, 50, 100]"
        @request="onRequest"
    >
        <template v-slot:top>
            <div class="row no-padding">
                <div class="col no-padding">
                    <q-input
                        dense
                        clearable
                        debounce="300"
                        v-model="pagination.search"
                        placeholder="Filtrar"
                        :dark="darkMode"
                        style="width: 300px"
                    >
                        <template v-slot:append>
                            <q-icon name="search" />
                        </template>
                    </q-input>
                </div>
                <div class="col col-auto no-padding">
                    <sync-from-api
                        :loading="loading"
                        :has-permission="hasPermission"
                        @reload="
                            (force) => {
                                pagination.force = force;
                                onRequest();
                            }
                        "
                    />
                </div>
            </div>
        </template>
    </q-table>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import { darkMode } from "../../../../../../hook/appConfig";
import SyncFromApi from "../../SyncFromApi.vue";
import axios from "axios";

defineOptions({
    name: "PonPorts",
});

const props = defineProps({
    oltId: Number | String,
    hasPermission: Object,
});

const emits = defineEmits(["reload", "update-columns"]);

const filter = ref("");
const rows = ref([]);
const loading = ref(false);

const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 1,
    search: null,
    force: false,
});

const columns = ref([
    {
        name: "board",
        field: "board",
        label: "Ranura",
        align: "left",
        sortable: true,
    },
    {
        name: "pon_port",
        field: "pon_port",
        label: "Puerto",
        align: "left",
        sortable: true,
    },
    {
        name: "pon_type",
        field: "pon_type",
        label: "Tipo",
        align: "left",
        sortable: true,
    },
    {
        name: "admin_status",
        field: "admin_status",
        label: "Estado admin",
        align: "left",
        sortable: true,
    },
    {
        name: "operational_status",
        field: "operational_status",
        label: "Estado operacional",
        align: "left",
        sortable: true,
    },
    {
        name: "onus_count",
        field: "onus_count",
        label: "ONU totales",
        align: "left",
        sortable: true,
    },
    {
        name: "online_onus_count",
        field: "online_onus_count",
        label: "ONU en línea",
        align: "left",
        sortable: true,
    },
    {
        name: "average_signal",
        field: "average_signal",
        label: "Señal promedio",
        align: "left",
        sortable: true,
    },
    {
        name: "min_range",
        field: "min_range",
        label: "Rango mínimo",
        align: "left",
        sortable: true,
    },
    {
        name: "max_range",
        field: "max_range",
        label: "Alcance máximo",
        align: "left",
        sortable: true,
    },
    {
        name: "tx_power",
        field: "tx_power",
        label: "Potencia TX",
        align: "left",
        sortable: true,
    },
    {
        name: "description",
        field: "description",
        label: "Descripción",
        align: "left",
        sortable: true,
    },
    {
        name: "last_synced_at_humans",
        field: "last_synced_at_humans",
        label: "Ultima sincronización",
        align: "left",
        sortable: false,
    },
]);

onMounted(() => {
    onRequest();
});

watch(
    () => pagination.value.search,
    () => {
        onRequest();
    }
);

const onRequest = async (attrs) => {
    if (attrs) {
        pagination.value = attrs.pagination;
    }
    loading.value = true;
    await axios
        .post(`/olts/settings/olts/${props.oltId}/pon-ports`, {
            ...pagination.value,
            columns: columns.value.map((c) => c.name.replace("_humans", "")),
        })
        .then((res) => {
            let { objects, total } = res.data;
            rows.value = objects;
            pagination.value.rowsNumber = total;
        })
        .catch(() => {
            rows.value = [];
        })
        .finally(() => {
            loading.value = false;
        });
};
</script>
<style scoped>
.q-field__append.row > button.q-icon {
    padding: 0px;
}
</style>
