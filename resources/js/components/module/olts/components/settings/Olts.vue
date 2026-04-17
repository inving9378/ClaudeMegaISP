<template>
    <template v-if="currentOlt">
        <q-item style="padding: 0">
            <q-item-section>
                <q-item-label class="text-primary text-bold">
                    OLT: {{ currentOlt.name }}
                </q-item-label>
            </q-item-section>
            <q-item-section avatar>
                <q-btn
                    color="primary"
                    label="Volver a la lista de OLTs"
                    no-caps
                    @click="currentOlt = null"
                />
            </q-item-section>
        </q-item>

        <olt-setting-panel
            :olt-id="currentOlt.id"
            :has-permission="hasPermission"
        />
    </template>

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
        loading-label="Obteniendo olts, por favor espere..."
        no-data-label="No existen olts disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Olts por página"
        v-model:pagination="pagination"
        :pagination-label="(start, end, total) => `${start}-${end} de ${total}`"
        :rows-per-page-options="[20, 30, 50, 100]"
        @request="onRequest"
        v-else
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

        <template v-slot:body-cell-actions="props">
            <td class="text-center" style="width: 200px">
                <q-btn
                    label="Ver"
                    color="primary"
                    size="sm"
                    no-caps
                    @click="currentOlt = props.row"
                />
            </td>
        </template>
    </q-table>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import { darkMode } from "../../../../../hook/appConfig";
import SyncFromApi from "../SyncFromApi.vue";
import OltSettingPanel from "./olts/OltSettingPanel.vue";
import axios from "axios";

defineOptions({
    name: "Olts",
});

const props = defineProps({
    object: Object,
    hasPermission: Object,
});

const emits = defineEmits(["reload", "update-columns"]);

const filter = ref("");
const rows = ref([]);
const loading = ref(false);
const currentOlt = ref(null);

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
        name: "id",
        field: "id",
        label: "ID",
        align: "left",
        sortable: true,
    },
    {
        name: "name",
        field: "name",
        label: "Nombre",
        align: "left",
        sortable: true,
    },
    {
        name: "ip",
        field: "ip",
        label: "IP",
        align: "left",
        sortable: true,
    },
    {
        name: "telnet_port",
        field: "telnet_port",
        label: "TCP",
        align: "left",
        sortable: true,
    },
    {
        name: "snmp_port",
        field: "snmp_port",
        label: "UDP",
        align: "left",
        sortable: true,
    },
    {
        name: "olt_hardware_version",
        field: "olt_hardware_version",
        label: "Versión hardware",
        align: "left",
        sortable: true,
    },
    {
        name: "env_temp",
        field: "env_temp",
        label: "Temperatura",
        align: "left",
        sortable: true,
    },
    {
        name: "uptime",
        field: "uptime",
        label: "Tiempo en línea",
        align: "left",
        sortable: true,
    },
    {
        name: "status",
        field: "status",
        label: "Estado",
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
    {
        name: "actions",
        field: "actions",
        label: "",
        align: "center",
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
        .post("/olts/settings/olts", {
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
