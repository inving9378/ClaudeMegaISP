<template>
    <q-table
        v-table-resizable="visibleColumns"
        :columns="visibleColumns"
        :rows="rows"
        :loading="loading"
        :dark="darkMode"
        flat
        wrap-cells
        row-key="id"
        color="primary"
        loading-label="Obteniendo onus, por favor espere..."
        no-data-label="No existen onus disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Onus por página"
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
                        debounce="300"
                        v-model="pagination.search"
                        placeholder="Buscar..."
                        clearable
                        :dark="darkMode"
                        style="width: 300px"
                    >
                        <template v-slot:append>
                            <q-icon name="search" />
                        </template>
                    </q-input>
                </div>
                <div class="col col-auto">
                    <q-btn
                        color="primary"
                        class="q-mr-sm"
                        label="..."
                        @click="columnsDialog = true"
                    />
                </div>
                <div class="col col-auto">
                    <sync-from-api
                        :has-permission="hasPermission"
                        :loading="loading"
                        @reload="
                            (force) => {
                                pagination.force = force;
                                onRequest();
                            }
                        "
                    />
                </div>
            </div>
            <div class="row" style="padding: 0">
                <div class="col" style="padding: 0">
                    <filter-onus
                        :current-filters="formFilters"
                        @change="
                            (data) => {
                                formFilters = data;
                                onRequest();
                            }
                        "
                    />
                </div>
            </div>
        </template>
        <template v-slot:body-cell-status="props">
            <td class="text-center">
                <q-icon
                    :name="props.row.icon"
                    :color="props.row.status_cls"
                    size="sm"
                >
                    <q-tooltip>
                        {{ props.row.status }}
                    </q-tooltip>
                </q-icon>
            </td>
        </template>
        <template v-slot:body-cell-signal="props">
            <td>
                <q-icon
                    name="mdi-signal"
                    :color="props.row.signal_cls"
                    size="sm"
                    v-if="props.row.signal !== '-'"
                >
                    <q-tooltip>
                        {{ props.row.signal }}
                    </q-tooltip>
                </q-icon>
            </td>
        </template>
        <template v-slot:body-cell-mode="props">
            <td class="text-center">
                <q-badge
                    color="indigo-10"
                    v-if="props.row.wan_mode === 'PPPoE'"
                    >{{ `${props.row.mode.substring(0, 1)}:PPPoE` }}</q-badge
                >
                <q-badge
                    color="indigo-10"
                    v-else-if="props.row.mode === 'Routing'"
                    >Router</q-badge
                >
                <q-badge color="indigo-10" v-else>{{ props.row.mode }}</q-badge>
            </td>
        </template>
        <template v-slot:body-cell-voip_service="props">
            <td class="text-center">
                <q-badge
                    color="primary"
                    v-if="props.row.voip_service === 'Enabled'"
                    >VoIP</q-badge
                >
            </td>
        </template>
        <template v-slot:body-cell-catv="props">
            <td class="text-center">
                <q-badge color="positive" v-if="props.row.catv === 'Enabled'"
                    >CATV</q-badge
                >
            </td>
        </template>
        <template v-slot:body-cell-actions="props">
            <td class="text-center" style="width: 200px">
                <panel-onu
                    :onu="props.row"
                    :has-permission="hasPermission"
                    @enabled="onRequest"
                    @removed="onRequest"
                    @update="updateRow"
                    @filter="
                        (f) => {
                            formFilters = f;
                            onRequest();
                        }
                    "
                />
            </td>
        </template>
    </q-table>

    <q-dialog v-model="columnsDialog" persistent>
        <q-card>
            <q-card-section style="padding: 10px">
                <q-item dense style="padding: 0">
                    <q-item-section>
                        <div class="text-h6">
                            Mostrar columnas/Ocultar columnas
                        </div>
                    </q-item-section>
                    <q-item-section avatar>
                        <q-btn
                            icon="close"
                            flat
                            round
                            dense
                            @click="showDialog = false"
                        />
                    </q-item-section>
                </q-item>
            </q-card-section>
            <q-separator />
            <q-card-section style="max-height: 60vh" class="scroll">
                <div class="my-3">
                    <p>
                        Para mostrar los campos de la tabla, seleccione la
                        casilla de verificación correspondiente.
                    </p>
                </div>
                <div
                    class="form-check form-switch form-switch-md"
                    v-for="(column, index) in columns"
                    :key="index"
                >
                    <input
                        class="form-check-input"
                        type="checkbox"
                        v-model="column.visible"
                    />
                    <label class="form-check-label">{{ column.label }}</label>
                </div>
            </q-card-section>
            <q-card-actions align="right" class="no-gutter-x">
                <q-btn
                    label="Guardar"
                    no-caps
                    @click="saveColumnsTable"
                    color="primary"
                    class="q-mr-sm"
                />
                <q-btn
                    label="Cerrar"
                    no-caps
                    @click="columnsDialog = false"
                    color="grey-7"
                />
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { darkMode } from "../../../../hook/appConfig";
import { useDataTable } from "../../../../composables/useDataTable";
import PanelOnu from "./onus/PanelOnu.vue";
import FilterOnus from "./FilterOnus.vue";
import SyncFromApi from "./SyncFromApi.vue";
import { message } from "../../../../helpers/toastMsg";

defineOptions({
    name: "OltOnus",
});

const props = defineProps({
    object: Object,
    defaultFilters: {
        type: Object,
        default: {},
    },
    hasPermission: Object,
});

const emits = defineEmits([
    "reload",
    "update-columns",
    "created",
    "enabled",
    "removed",
]);

const showDialog = ref(false);
const columnsDialog = ref(false);
const { saveColumns, getColumns } = useDataTable();

const columns = ref([
    {
        name: "status",
        field: "status",
        label: "Estado",
        align: "center",
        sortable: false,
    },
    {
        name: "actions",
        field: "actions",
        label: "",
        align: "center",
        sortable: false,
    },
    {
        name: "name",
        field: "name",
        label: "Nombre",
        align: "left",
        sortable: true,
    },
    {
        name: "sn",
        field: "sn",
        label: "SN/MAC",
        align: "left",
        sortable: true,
    },
    {
        name: "unique_external_id",
        field: "unique_external_id",
        label: "Id externo",
        align: "left",
        sortable: true,
    },
    {
        name: "board",
        field: "board",
        label: "Tarjeta",
        align: "left",
        sortable: true,
    },
    {
        name: "port",
        field: "port",
        label: "Puerto",
        align: "left",
        sortable: true,
    },
    {
        name: "administrative_status",
        field: "administrative_status",
        label: "Estado admin.",
        align: "left",
        sortable: true,
    },
    {
        name: "address",
        field: "address",
        label: "Dirección",
        align: "left",
        sortable: true,
    },
    {
        name: "onu",
        field: "onu",
        label: "ONU",
        align: "left",
        sortable: true,
    },
    {
        name: "onu_type_name",
        field: "onu_type_name",
        label: "Tipo",
        align: "left",
        sortable: true,
    },
    {
        name: "pon_type",
        field: "pon_type",
        label: "Tipo PON",
        align: "left",
        sortable: true,
    },
    {
        name: "signal",
        field: "signal",
        label: "Señal",
        align: "left",
        sortable: false,
    },
    {
        name: "mode",
        field: "mode",
        label: "B/R",
        align: "center",
        sortable: false,
    },
    {
        name: "service_ports",
        field: "service_ports",
        label: "VLAN",
        align: "center",
        sortable: false,
        format: (val) => {
            if (val?.length > 0) {
                let temp = val.map((v) => {
                    if (v.svlan !== "" && v.vlan !== "") {
                        return `${v.vlan},${v.svlan}`;
                    } else if (v.vlan !== "") {
                        return v.vlan;
                    }
                });
                return temp;
            }
            return null;
        },
    },
    {
        name: "voip_service",
        field: "voip_service",
        label: "VoIP",
        align: "center",
        sortable: false,
    },
    {
        name: "catv",
        field: "catv",
        label: "TV",
        align: "center",
        sortable: false,
    },
    {
        name: "zone_name",
        field: "zone_name",
        label: "Zona",
        align: "left",
        sortable: true,
    },
    {
        name: "authorization_date_humans",
        field: "authorization_date_humans",
        label: "Autorizada",
        align: "left",
        sortable: true,
        sort: (a, b, rowA, rowB) =>
            new Date(rowA.last_synced_at) - new Date(rowB.last_synced_at),
    },
    {
        name: "last_synced_at_humans",
        field: "last_synced_at_humans",
        label: "Ultima sincronización",
        align: "left",
        sortable: true,
        sort: (a, b, rowA, rowB) =>
            new Date(rowA.last_synced_at) - new Date(rowB.last_synced_at),
    },
]);
const rows = ref([]);
const formFilters = ref({});

let timer = null;
const loading = ref(false);

const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 1,
    search: null, //"100 - LILLIAN VILLAVICENCIO VALADEZ"
    force: false,
});

onMounted(() => {
    formFilters.value = props.defaultFilters;
    getColumnsTable();
    onRequest();
    timer = setInterval(() => {
        if (!loading.value) {
            onRequest();
        }
    }, 60000);
});

onUnmounted(() => {
    clearInterval(timer);
});

watch(
    () => pagination.value.search,
    () => {
        onRequest();
    }
);

const getColumnsTable = async () => {
    const storedColumns = getColumns("olt-configured");
    if (storedColumns && storedColumns.length > 0) {
        columns.value.forEach((column) => {
            const storedColumn = storedColumns.find(
                (col) => col.name === column.name
            );
            if (storedColumn) {
                column.visible = storedColumn.visible;
            }
        });
    } else {
        columns.value.forEach((column) => {
            column.visible = true;
        });
        emits("update-columns", getColumnsMap());
    }
};

const getColumnsMap = () => {
    return columns.value.map((col) => ({
        name: col.name,
        visible: col.visible,
    }));
};

const saveColumnsTable = async () => {
    try {
        const columnsData = getColumnsMap();
        await saveColumns("olt-configured", columnsData);
        emits("update-columns", columnsData);
        columnsDialog.value = false;
    } catch (error) {
        console.log(error);
    }
};

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);

const onRequest = async (attrs) => {
    if (!loading.value) {
        if (attrs) {
            pagination.value = attrs.pagination;
        }
        loading.value = true;
        await axios
            .post("/olts/onus/configured", {
                ...pagination.value,
                columns: visibleColumns.value.map((c) =>
                    c.name.replace("_humans", "")
                ),
                params:
                    Object.keys(formFilters.value).length > 0
                        ? formFilters.value
                        : null,
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
                pagination.value.force = false;
            });
    } else {
        message("Petición en proceso", "info");
    }
};

const updateRow = (data) => {
    const found = rows.value.find((r) => r.id === data.id);
    if (found) {
        Object.assign(found, data);
    }
};
</script>
<style scoped>
.q-field__append.row > button.q-icon {
    padding: 0px;
}
</style>
