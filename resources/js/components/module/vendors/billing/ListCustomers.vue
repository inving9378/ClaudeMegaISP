<template>
    <q-table
        v-table-resizable="visibleColumns"
        wrap-cells
        :rows="rows"
        :columns="visibleColumns"
        :dark="darkMode"
        loading-label="Obteniendo clientes, por favor espere..."
        no-data-label="No existen clientes disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Clientes por página"
        :rows-per-page-options="rowPerPageOptions"
        :pagination-label="(start, end, total) => `${start}-${end} de ${total}`"
        v-model:pagination="pagination"
        binary-state-sort
        @request="onRequest"
        :loading="loading"
    >
        <template v-slot:top="props">
            <div class="row">
                <div class="col-4 no-padding">
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
                <div class="col-8">
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
        <template v-slot:body-cell-completed_payment="props">
            <q-td :props="props">
                <first-payments
                    :payments="props.row.completed_payment"
                    :data="props.row.payments"
                    :client="`${props.row.full_name} - ${props.row.id}`"
                />
            </q-td>
        </template>
        <template v-slot:body-cell-pending_payment="props">
            <q-td :props="props">
                <span class="badge-primary" v-if="props.row.state === 'ok'">
                    {{ props.row.pending_payment }}
                </span>
                <span
                    class="badge-yellow"
                    v-else-if="props.row.state === 'in_term'"
                >
                    {{ props.row.pending_payment }}
                </span>
                <span class="badge-red" v-else>
                    {{ props.row.pending_payment }}
                </span>
            </q-td>
        </template>
    </q-table>

    <div class="row q-pt-md no-gutter-x" v-if="rows && rows.length > 0">
        <div class="col q-pa-sm text-center client-active">Activo</div>
        <div class="col q-pa-sm text-center client-block">Bloqueado</div>
        <div class="col q-pa-sm text-center client-cancel">Cancelado</div>
        <div class="col q-pa-sm text-center client-inactive">Inactivo</div>
    </div>

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

import VueDatePicker from "@vuepic/vue-datepicker";

import Modal from "../../../../shared/ModalSimple.vue";
import FirstPayments from "./components/FirstPayments.vue";
import { darkMode } from "../../../../hook/appConfig";
import { useDataTable } from "../../../../composables/useDataTable";
import { useDatePicker } from "../../../../composables/useDatePicker";
import { getSalesBySeller } from "../sales/helper/request";
import moment from "moment";
import { debounce, cloneDeep } from "lodash";
import { requestColumnsDatatableByModule } from "../../../../helpers/Request";

const props = defineProps({
    user_id: {
        type: Number,
        required: true,
    },
});

const { customFormat } = useDatePicker();
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
        name: "completed_payment",
        align: "center",
        label: "Cuotas pagadas",
        field: "completed_payment",
        sortable: false,
        visible: true,
        classes: (row) => {
            return row.css_state;
        },
    },
    {
        name: "pending_payment",
        align: "center",
        label: "Cuotas restantes",
        field: "pending_payment",
        sortable: false,
        visible: true,
        classes: (row) => {
            return row.css_state;
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
const rowPerPageOptions = ref([20, 30, 50, 100]);
const loading = ref(false);
const showModal = ref(false);
const tableIdentifier = ref("pagos-clientes");
const { getColumns, saveColumns } = useDataTable();

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
    let data = await getSalesBySeller(props.user_id, pagination.value);
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
            const stored = columnsData.find((col) => col.name === c.name);
            if (stored) {
                c.visible = stored.visible;
            }
        });
    } catch (error) {
        console.log(error);
    }
};

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);
</script>
<style scoped>
.badge-yellow {
    background-color: #fd7e14;
    color: #ffffff;
    padding: 0 8px;
    padding-top: 2px;
    padding-bottom: 2px;
    border-radius: 3px;
    font-weight: 500;
}

.badge-red {
    background-color: red;
    color: #ffffff;
    padding: 0 8px;
    padding-top: 2px;
    padding-bottom: 2px;
    border-radius: 3px;
    font-weight: 500;
}

.badge-primary {
    background-color: #357bf2;
    color: #ffffff;
    padding: 0 8px;
    padding-top: 2px;
    padding-bottom: 2px;
    border-radius: 3px;
    font-weight: 500;
}
</style>
