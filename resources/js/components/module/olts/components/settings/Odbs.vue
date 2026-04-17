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
        loading-label="Obteniendo odbs, por favor espere..."
        no-data-label="No existen odbs disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Odbs por página"
        v-model:pagination="pagination"
        :pagination-label="(start, end, total) => `${start}-${end} de ${total}`"
        :rows-per-page-options="[20, 30, 50, 100]"
        @request="onRequest"
    >
        <template v-slot:top>
            <div class="row no-padding q-gutter-xs">
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
                <div
                    class="col col-auto"
                    v-if="hasPermission?.data.canView('odb_add')"
                >
                    <form-odb
                        @reload="
                            (force) => {
                                pagination.force = force;
                                onRequest();
                            }
                        "
                    />
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
import { darkMode } from "../../../../../hook/appConfig";
import SyncFromApi from "../SyncFromApi.vue";
import FormOdb from "./FormOdb.vue";
import axios from "axios";

defineOptions({
    name: "Odbs",
});

const props = defineProps({
    object: Object,
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
        name: "name",
        field: "name",
        label: "Nombre",
        align: "left",
        sortable: true,
    },
    {
        name: "zone_name",
        field: "zone_name",
        label: "Zona",
        align: "left",
        sortable: true,
    },
    {
        name: "latitude",
        field: "latitude",
        label: "Latitud",
        align: "left",
        sortable: true,
    },
    {
        name: "longitude",
        field: "longitude",
        label: "Longitud",
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
        .post("/olts/settings/odbs", {
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
