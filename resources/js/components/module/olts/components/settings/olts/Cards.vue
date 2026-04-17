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
    name: "Cards",
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
        name: "type",
        field: "type",
        label: "Tipo",
        align: "left",
        sortable: true,
    },
    {
        name: "real_type",
        field: "real_type",
        label: "Tipo real",
        align: "left",
        sortable: true,
    },
    {
        name: "ports",
        field: "ports",
        label: "Puertos",
        align: "left",
        sortable: true,
    },
    {
        name: "software_version",
        field: "software_version",
        label: "Versión de software",
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
        name: "slot",
        field: "slot",
        label: "Slot",
        align: "left",
        sortable: true,
    },
    {
        name: "role",
        field: "role",
        label: "Rol",
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

watch(
    () => props.oltId,
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
        .post(`/olts/settings/olts/${props.oltId}/cards`, {
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
