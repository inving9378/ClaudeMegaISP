<template>
    <q-tabs
        v-model="tab"
        dense
        no-caps
        inline-label
        class="bg-grey-3 text-grey-7"
        active-color="primary"
        indicator-color="primary"
        align="justify"
        content-class="no-gutter-x width-auto"
        @update:model-value="onChangeTab"
    >
        <q-tab name="download" label="Descarga" />
        <q-tab name="upload" label="Subida" />
    </q-tabs>

    <q-separator />

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
        loading-label="Obteniendo perfiles de velocidad, por favor espere..."
        no-data-label="No existen perfiles de velocidad disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Perfiles de velocidad por página"
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
import { darkMode } from "../../../../../hook/appConfig";
import SyncFromApi from "../SyncFromApi.vue";
import axios from "axios";
import { setActiveTab } from "../../../../../hook/appConfig";

defineOptions({
    name: "Profiles",
});

const props = defineProps({
    object: Object,
    hasPermission: Object,
});

const tab = ref(
    localStorage.getItem("tab-olt-settings-profiles") || "download"
);

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
        name: "speed",
        field: "speed",
        label: "Velocidad",
        align: "left",
        sortable: true,
        format: (val) => {
            return `${val} kbps`;
        },
    },
    {
        name: "type",
        field: "type",
        label: "Typo",
        align: "left",
        sortable: true,
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

const onChangeTab = (t) => {
    setActiveTab("tab-olt-settings-profiles", t);
    onRequest();
};

const onRequest = async (attrs) => {
    if (attrs) {
        pagination.value = attrs.pagination;
    }
    loading.value = true;
    await axios
        .post("/olts/settings/profiles", {
            ...pagination.value,
            columns: columns.value.map((c) => c.name),
            direction: tab.value,
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
