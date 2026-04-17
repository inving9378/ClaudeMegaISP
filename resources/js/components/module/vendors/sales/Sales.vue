<template>
    <q-card>
        <q-card-section class="d-flex" style="justify-content: space-between">
            <div class="text-h6">Listado de ventas</div>
        </q-card-section>
        <q-table
            v-table-resizable="visibleColumns"
            :dark="darkMode"
            :rows="rows"
            :columns="visibleColumns"
            wrap-cells
            loading-label="Obteniendo ventas, por favor espere..."
            no-data-label="No existen ventas disponibles"
            no-results-label="No se encontraron coincidencias"
            rows-per-page-label="Ventas por página"
            :pagination-label="
                (start, end, total) => `${start}-${end} de ${total}`
            "
            :rows-per-page-options="[20, 30, 50, 100]"
            v-model:pagination="pagination"
            binary-state-sort
            :loading="loading"
            @request="onRequest"
        >
            <template v-slot:top="props">
                <div class="row">
                    <div class="col-3 no-padding">
                        <label>Fecha alta</label>
                        <VueDatePicker
                            v-model="pagination.discharge_date"
                            position="right"
                            locale="es"
                            :teleport="true"
                            placeholder="Fecha alta"
                            range
                            week-start="0"
                            :format="customFormat"
                            :clearable="true"
                            :enable-time-picker="false"
                            class="no-padding"
                            :dark="darkMode"
                        >
                        </VueDatePicker>
                    </div>
                    <div class="col-3">
                        <label>Fecha activación</label>
                        <VueDatePicker
                            v-model="pagination.activation_date"
                            position="right"
                            locale="es"
                            :teleport="true"
                            placeholder="Fecha de activación"
                            range
                            week-start="0"
                            :format="customFormat"
                            :clearable="true"
                            :enable-time-picker="false"
                            class="no-padding"
                            :dark="darkMode"
                        >
                        </VueDatePicker>
                    </div>
                    <div class="col-6 q-pt-lg">
                        <div class="d-flex justify-content-end">
                            <button
                                type="button"
                                class="btn btn-outline-info"
                                @click="showModal = true"
                            >
                                ...</button
                            ><q-btn
                                outline
                                color="info"
                                icon="mdi-sync"
                                padding="10px"
                                class="q-mx-sm"
                                @click="onRequest({ pagination })"
                            />
                            <q-input
                                borderless
                                dense
                                v-model="searchInput"
                                placeholder="Buscar"
                                class="mb-0"
                                style="margin-left: 16px; border: 1px solid"
                                :dark="darkMode"
                            >
                            </q-input>
                            <q-btn
                                flat
                                round
                                dense
                                :icon="
                                    props.inFullscreen
                                        ? 'fullscreen_exit'
                                        : 'fullscreen'
                                "
                                @click="props.toggleFullscreen"
                                class="q-ml-md"
                            />
                        </div>
                    </div>
                </div>
            </template>
        </q-table>

        <div class="row q-pt-md no-gutter-x" v-if="rows && rows.length > 0">
            <div class="col q-pa-sm text-center client-active">Activo</div>
            <div class="col q-pa-sm text-center client-block">Bloqueado</div>
            <div class="col q-pa-sm text-center client-cancel">Cancelado</div>
            <div class="col q-pa-sm text-center client-inactive">Inactivo</div>
        </div>
    </q-card>

    <!-- Modal -->
    <modal
        :show="showModal"
        :size="'xs'"
        @update:show="showModal = $event"
        title="Mostrar columnas/Ocultar columnas"
    >
        <template #body>
            <div class="my-3">
                <p>
                    Para mostrar los campos de la tabla, seleccione la casilla
                    de verificación correspondiente.
                </p>
            </div>
            <div
                class="form-check form-switch form-switch-md"
                v-for="(column, index) in columns.filter((c) => !c.required)"
                :key="index"
            >
                <input
                    class="form-check-input"
                    type="checkbox"
                    v-model="column.visible"
                />
                <label class="form-check-label">{{ column.label }}</label>
            </div>
        </template>
        <template #footer>
            <button class="btn btn-primary" @click="saveColumnsTable">
                Guardar
            </button>
        </template>
    </modal>
</template>

<script setup>
import { ref, onMounted, computed, watch, nextTick } from "vue";
import { getSalesBySeller } from "./helper/request.js";
import { useDataTable } from "../../../../composables/useDataTable.js";
import { useDatePicker } from "../../../../composables/useDatePicker.js";
import Modal from "../../../../shared/ModalSimple.vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import { darkMode } from "../../../../hook/appConfig.js";
import { debounce } from "lodash";
import moment from "moment/moment.js";

const props = defineProps({
    id: Number,
});

const { customFormat } = useDatePicker();
const { getColumns, saveColumns } = useDataTable();
const columns = ref([
    {
        name: "id",
        field: "id",
        label: "ID",
        align: "start",
        classes: (row) => {
            return row.css_state ?? null;
        },
        sortable: true,
        visible: true,
        required: true,
    },
    {
        name: "name",
        field: "name",
        label: "Nombre",
        align: "start",
        classes: (row) => {
            return row.css_state ?? null;
        },
        sortable: true,
        visible: true,
    },
    {
        name: "father_last_name",
        field: "father_last_name",
        label: "Apellido paterno",
        align: "start",
        classes: (row) => {
            return row.css_state ?? null;
        },
        sortable: true,
        visible: true,
    },
    {
        name: "mother_last_name",
        field: "mother_last_name",
        label: "Apellido materno",
        align: "start",
        classes: (row) => {
            return row.css_state ?? null;
        },
        sortable: true,
        visible: true,
    },
    {
        name: "phone",
        field: "phone",
        label: "Teléfono",
        align: "start",
        classes: (row) => {
            return row.css_state ?? null;
        },
        sortable: true,
        visible: true,
    },
    {
        name: "phone2",
        field: "phone2",
        label: "Teléfono2",
        align: "start",
        classes: (row) => {
            return row.css_state ?? null;
        },
        sortable: true,
        visible: true,
    },
    {
        name: "estado",
        field: "estado",
        label: "Estado",
        align: "start",
        classes: (row) => {
            return row.css_state ?? null;
        },
        sortable: true,
        visible: true,
        required: true,
    },
    {
        name: "package_price",
        field: "package_price",
        label: "Servicio",
        align: "start",
        classes: (row) => {
            return row.css_state ?? null;
        },
        sortable: true,
        visible: true,
        format: (val) => {
            return `$${val}`;
        },
    },
    {
        name: "activation_date",
        field: "activation_date",
        label: "Fecha de activación",
        align: "start",
        classes: (row) => {
            return row.css_state ?? null;
        },
        sortable: true,
        visible: true,
        format: (val) => {
            try {
                return moment(val).format("DD/MM/YYYY");
            } catch (error) {
                return val;
            }
        },
    },
]);
const rows = ref([]);
const loading = ref(false);
const showModal = ref(false);
const tableIdentifier = ref("ventas-vendedores");
const searchInput = ref("");
const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 30,
    rowsNumber: 1,
    sortBy: "id",
    search: null,
    discharge_date: null,
    activation_date: null,
    columns: [],
    filters: [],
});

const performSearch = debounce((searchTerm) => {
    pagination.value.search = searchTerm;
    pagination.value.page = 1;
    onRequest();
}, 500);

onMounted(async () => {
    await getColumnsTable();
    nextTick(() => {
        onRequest();
    });
});

watch(
    () => visibleColumns,
    (n) => {
        pagination.value.columns = n;
    },
    { deep: true }
);

watch(searchInput, (newSearchTerm) => {
    performSearch(newSearchTerm);
});

watch(
    () => pagination.value.discharge_date,
    (n) => {
        updateFilters("discharge_date", {
            type: "date",
            value: n ? (n[1] ? n : n[0]) : null,
        });
    }
);

watch(
    () => pagination.value.activation_date,
    (n) => {
        updateFilters("activation_date", {
            type: "date",
            value: n ? (n[1] ? n : n[0]) : null,
        });
    }
);

const updateFilters = (column, params) => {
    let index = pagination.value.filters.findIndex((f) => f.column === column);
    if (index >= 0) {
        if (params.value !== null) {
            pagination.value.filters[index].value = params.value;
        } else {
            pagination.value.filters.splice(index, 1);
        }
    } else {
        pagination.value.filters.push({
            column,
            ...params,
        });
    }
    onRequest();
};

const onRequest = async (attrs) => {
    loading.value = true;
    if (attrs) {
        pagination.value = attrs.pagination;
    }
    pagination.value.columns = visibleColumns.value
        .filter((c) => c.visible)
        .map((c) => c.name);
    let data = await getSalesBySeller(props.id, pagination.value);
    if (data !== null) {
        rows.value = data.clients;
        pagination.value.rowsNumber = data.total;
    } else {
        rows.value = [];
    }
    loading.value = false;
};

const getColumnsTable = async () => {
    try {
        const response = await getColumns(tableIdentifier.value);
        const storedColumns = response;
        if (storedColumns && storedColumns.length > 0) {
            columns.value.forEach((c) => {
                c.visible = storedColumns.includes(c.name);
            });
        }
    } catch (error) {
        console.log(error);
    }
};

const saveColumnsTable = async () => {
    try {
        const columnsData = visibleColumns.value.map((col) => col.name);
        await saveColumns(tableIdentifier.value, columnsData);
        showModal.value = false;
        await onRequest();
        columns.value.forEach((c) => {
            c.visible = columnsData.includes(c.name);
        });
    } catch (error) {
        console.log(error);
    }
};

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);
</script>
