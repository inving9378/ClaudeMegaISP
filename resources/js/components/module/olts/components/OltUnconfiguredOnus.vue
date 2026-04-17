<template>
    <q-table
        v-table-resizable="visibleColumns"
        :columns="visibleColumns"
        :rows="rows"
        :loading="loading"
        :dark="darkMode"
        :filter="filter"
        flat
        wrap-cells
        row-key="id"
        color="primary"
        loading-label="Obteniendo onus, por favor espere..."
        no-data-label="No existen onus disponibles"
        no-results-label="No se encontraron coincidencias"
        :pagination="{ rowsPerPage: 0 }"
        hide-pagination
        class="q-mb-xl no-padding"
    >
        <template v-slot:top>
            <div class="row no-padding">
                <div class="col-auto no-padding">
                    <q-input
                        dense
                        debounce="300"
                        v-model="filter"
                        placeholder="Filtrar"
                        :dark="darkMode"
                        style="width: 300px"
                    >
                        <template v-slot:append>
                            <q-icon name="search" />
                        </template>
                    </q-input>
                </div>
                <div class="col">
                    <q-select
                        dense
                        options-dense
                        emit-value
                        map-options
                        clearable
                        debounce="300"
                        v-model="currentOlt"
                        placeholder="Olt"
                        :options="olts"
                        option-value="id"
                        option-label="name"
                        :display-value="
                            currentOlt ? undefined : 'Todas las Olt'
                        "
                        :dark="darkMode"
                        style="width: 300px"
                    />
                </div>
                <div class="col col-auto">
                    <q-btn
                        color="primary"
                        class="q-mr-sm"
                        label="..."
                        @click="columnsDialog = true"
                    />
                </div>
                <div class="col col-auto no-padding">
                    <sync-from-api
                        :has-permission="hasPermission"
                        :loading="loading"
                        @reload="loadData"
                    />
                </div>
            </div>
        </template>
        <template v-slot:body-cell-actions="props">
            <td class="text-center">
                <form-onu
                    :object="props.row"
                    :for-after="true"
                    :olts="olts"
                    :type-onus="nomenclatures.type_onus"
                    :zones="nomenclatures.zones"
                    :clients="nomenclatures.clients"
                    :speed-profiles="nomenclatures.speed_profiles"
                    :odbs="nomenclatures.odbs"
                    :loading="loadingNomenclatures"
                    :client="client"
                    :has-permission="hasPermission"
                    @created="onCreated"
                    v-if="hasAdd"
                />
            </td>
        </template>
    </q-table>

    <div class="row" v-if="hasAdd">
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-6 col-xl-6">
            <form-onu
                label="Agregar ONU para posterior autorización"
                color="positive"
                :flat="false"
                :for-after="true"
                :olts="olts"
                :type-onus="nomenclatures.type_onus"
                :zones="nomenclatures.zones"
                :clients="nomenclatures.clients"
                :speed-profiles="nomenclatures.speed_profiles"
                :odbs="nomenclatures.odbs"
                :loading="loadingNomenclatures"
                :current-olt="currentOlt"
                :client="client"
                :has-permission="hasPermission"
                @created="onCreated"
            />
        </div>
        <div class="col-xs-12 col-sm-6 col-md-6 col-lg-6 col-xl-6">
            <q-chip
                outline
                color="primary"
                text-color="white"
                :ripple="false"
                v-if="client"
            >
                {{ client.id }} - {{ client.name }}
            </q-chip>
        </div>
    </div>

    <q-table
        v-table-resizable="columnsSaved"
        :columns="columnsSaved"
        :rows="rowsSaved"
        :loading="loadingSaved"
        :dark="darkMode"
        :filter="filter"
        title="ONUs guardadas para su posterior autorización"
        flat
        wrap-cells
        row-key="id"
        color="primary"
        loading-label="Obteniendo onus, por favor espere..."
        no-data-label="No existen onus disponibles"
        no-results-label="No se encontraron coincidencias"
        :pagination="{ rowsPerPage: 0 }"
        hide-pagination
        class="q-mt-xl no-padding"
        v-if="rowsSaved.length > 0"
    >
        <template v-slot:body-cell-actions="props">
            <td class="text-center" style="width: 200px">
                <panel-onu
                    :onu="props.row"
                    :has-permission="hasPermission"
                    @enabled="loadSavedData"
                    @removed="loadSavedData"
                    @update="loadSavedData"
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
import {
    getNomenclatures,
    getOLTs,
    getUnconfiguredOnus,
    getSavedUnconfiguredOnus,
} from "../helper/request";
import FormOnu from "./form/FormOnu.vue";
import PanelOnu from "./onus/PanelOnu.vue";
import SyncFromApi from "./SyncFromApi.vue";

defineOptions({
    name: "OltUnconfiguredOnus",
});

const props = defineProps({
    olt: Object,
    hasPermission: Object,
    client: Object,
});

const emits = defineEmits(["created", "reload", "update-columns"]);

const showDialog = ref(false);
const columnsDialog = ref(false);
const filter = ref("");
const loading = ref(false);
const loadingSaved = ref(false);
const { saveColumns, getColumns } = useDataTable();

const columns = ref([
    {
        name: "pon_type",
        field: "pon_type",
        label: "Tipo PON",
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
        name: "onu",
        field: "onu",
        label: "ONU",
        align: "left",
        sortable: true,
    },
    {
        name: "sn",
        field: "sn",
        label: "Serie",
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
        name: "olt_str",
        field: "olt_str",
        label: "Olt",
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
        align: "center",
        sortable: false,
        required: true,
    },
]);

const columnsSaved = ref([
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
        label: "Serie",
        align: "left",
        sortable: true,
    },
    {
        name: "olt_name",
        field: "olt_name",
        label: "Olt",
        align: "left",
        sortable: true,
    },
    {
        name: "actions",
        field: "actions",
        align: "center",
        sortable: false,
        required: true,
    },
]);

const rows = ref([]);
const rowsSaved = ref([]);

let timer = null;
const loadingOlt = ref(false);
const olts = ref([]);
const currentOlt = ref(null);
const loadingNomenclatures = ref(false);
const nomenclatures = ref({
    type_onus: [],
    zones: [],
    clients: [],
    speed_profiles: [],
    odbs: [],
});

onMounted(() => {
    getColumnsTable();
    loadOlts();
    currentOlt.value = props.olt ?? null;
    loadData();
    loadSavedData();
    timer = setInterval(() => {
        emits("reload", false);
    }, 60000);
    loadNomenclatures();
});

onUnmounted(() => {
    clearInterval(timer);
});

watch(currentOlt, (n) => {
    loadData();
});

const hasAdd = computed(() => {
    return props.hasPermission?.data.canView("onu_add") ?? false;
});

const getColumnsTable = async () => {
    const storedColumns = getColumns("olt-unconfigured-onus");
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
        await saveColumns("olt-unconfigured-onus", columnsData);
        emits("update-columns", columnsData);
        columnsDialog.value = false;
    } catch (error) {
        console.log(error);
    }
};

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);

const loadData = async (force = false) => {
    loading.value = true;
    const result = await getUnconfiguredOnus(currentOlt.value, { force });
    if (result.success) {
        rows.value = result.rows;
    } else {
        message(result.message, "error");
    }
    loading.value = false;
};

const loadSavedData = async () => {
    loadingSaved.value = true;
    const result = await getSavedUnconfiguredOnus();
    if (result.success) {
        rowsSaved.value = result.rows;
    } else {
        message(result.message, "error");
    }
    loadingSaved.value = false;
};

const loadOlts = async () => {
    loadingOlt.value = true;
    const result = await getOLTs();
    if (result.success) {
        olts.value = result.rows;
    } else {
        message(result.message, "error");
    }
    loadingOlt.value = false;
};

const loadNomenclatures = async () => {
    loadingNomenclatures.value = true;
    const result = await getNomenclatures();
    if (result) {
        nomenclatures.value = { ...result };
    }
    loadingNomenclatures.value = false;
};

const onCreated = (onu) => {
    emits("created", onu);
    loadData(true);
};
</script>
<style scoped>
.q-field__append.row > button.q-icon {
    padding: 0px;
}
</style>
