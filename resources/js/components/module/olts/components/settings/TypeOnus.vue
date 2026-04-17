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
        loading-label="Obteniendo tipo de onus, por favor espere..."
        no-data-label="No existen tipo de onus disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Tipo de onus por página"
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
                    v-if="hasPermission?.data.canView('onu_type_add')"
                >
                    <form-type-onu
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
import FormTypeOnu from "./FormTypeOnu.vue";
import axios from "axios";

defineOptions({
    name: "TypeOnus",
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
        label: "Tipo",
        align: "left",
        sortable: true,
    },
    {
        name: "pon_type",
        field: "pon_type",
        label: "PON",
        align: "left",
        sortable: true,
    },
    {
        name: "ethernet_ports",
        field: "ethernet_ports",
        label: "Puertos Eth",
        align: "center",
        sortable: true,
    },
    {
        name: "wifi_ports",
        field: "wifi_ports",
        label: "Puertos WiFi",
        align: "center",
        sortable: true,
    },
    {
        name: "voip_ports",
        field: "voip_ports",
        label: "Puertos VoIP",
        align: "center",
        sortable: true,
    },
    {
        name: "catv",
        field: "catv",
        label: "CATV",
        align: "left",
        sortable: true,
    },
    {
        name: "allow_custom_profiles",
        field: "allow_custom_profiles",
        label: "Permitir perfiles personalizados",
        align: "center",
        sortable: true,
        format: (val) => {
            return val ? "Si" : "No";
        },
    },
    {
        name: "capability",
        field: "capability",
        label: "Capacidad",
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
        .post("/olts/settings/type-onus", {
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
