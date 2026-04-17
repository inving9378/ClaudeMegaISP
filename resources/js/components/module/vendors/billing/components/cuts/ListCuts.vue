<template>
    <q-table
        v-table-resizable="visibleColumns"
        :columns="visibleColumns"
        :rows="rows"
        :loading="loading"
        :dark="darkMode"
        wrap-cells
        v-model:pagination="pagination"
        row-key="id"
        loading-label="Obteniendo registros, por favor espere..."
        no-data-label="No existen registros disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="registros por página"
        :pagination-label="(start, end, total) => `${start}-${end} de ${total}`"
        :rows-per-page-options="[20, 30, 50, 100]"
        @request="onRequest"
    >
        <template v-slot:top>
            <div class="row no-padding">
                <div class="col">
                    <label for="search">ID</label>
                    <q-input
                        for="search"
                        dense
                        outlined
                        debounce="300"
                        color="primary"
                        v-model="pagination.search"
                        @keydown.enter.prevent="search"
                    >
                        <template v-slot:after>
                            <q-btn
                                icon="search"
                                color="primary"
                                @click="search"
                            />
                            <q-btn
                                icon="close"
                                color="grey-7"
                                @click="searchClear"
                                v-if="searched"
                            />
                        </template>
                    </q-input>
                </div>
                <div class="col">
                    <label for="date">Fecha</label>
                    <VueDatePicker
                        id="date"
                        v-model="pagination.date"
                        position="right"
                        locale="es"
                        :teleport="true"
                        range
                        week-start="0"
                        :dark="darkMode"
                        :format="customFormat"
                        :enableTimePicker="false"
                    >
                    </VueDatePicker>
                </div>
                <div class="col self-end text-right no-padding">
                    <q-btn
                        color="primary"
                        class="q-mr-sm"
                        label="..."
                        @click="showModal = true"
                    />
                    <q-btn
                        color="primary"
                        class="q-mr-sm"
                        icon="history"
                        :loading="loading"
                        @click="onRequest(pagination.value)"
                    />
                </div>
            </div>
        </template>
        <template v-slot:body-cell-actions="props">
            <q-td class="text-center">
                <q-btn
                    icon="fas fa-eye"
                    flat
                    size="xs"
                    round
                    color="primary"
                    @click="emits('show-box', props.row.id)"
                >
                    <q-tooltip>Ver detalles</q-tooltip>
                </q-btn>
                <q-btn
                    :href="`/sellers/cuts/box-pdf/${props.row.id}`"
                    target="_blank"
                    icon="fa fa-file-pdf"
                    flat
                    size="xs"
                    round
                    color="primary"
                    v-if="props.row.closed"
                >
                    <q-tooltip>Mostrar en PDF</q-tooltip>
                </q-btn>
                <q-btn
                    icon="fa fa-file-pdf"
                    flat
                    size="xs"
                    round
                    color="primary"
                    disable
                    v-else
                />
            </q-td>
        </template>
    </q-table>

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
        </template>
        <template #footer>
            <button class="btn btn-primary" @click="saveColumnsTable">
                Guardar
            </button>
        </template>
    </modal>
</template>

<script setup>
import { ref, onMounted, watch, computed } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import Modal from "../../../../../../shared/ModalSimple.vue";
import { darkMode } from "../../../../../../hook/appConfig";
import { useDataTable } from "../../../../../../composables/useDataTable";
import { useDatePicker } from "../../../../../../composables/useDatePicker";
import { cuts } from "../../helper/cutBox";

const props = defineProps({
    userId: Number,
    updateListPayments: {
        type: Boolean,
    },
});

const emits = defineEmits(["show-box"]);

const { customFormat } = useDatePicker();

const showModal = ref(false);
const loading = ref(false);
const tableIdentifier = ref("billing-list-cuts");
const { getColumns, saveColumns } = useDataTable();

const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 1,
    search: null,
    date: null,
    period: null,
    paymentMethod: null,
    paymentType: null,
    createdBy: null,
});

const columns = ref([
    {
        name: "id",
        field: "id",
        label: "Caja",
        align: "left",
        sortable: true,
    },
    {
        name: "start_date",
        field: "start_date",
        label: "Fecha",
        align: "left",
        sortable: true,
    },
    {
        name: "start_time",
        field: "start_time",
        label: "Inicio",
        align: "left",
        sortable: true,
    },
    {
        name: "end_time",
        field: "end_time",
        label: "Fin",
        align: "left",
        sortable: true,
    },
    {
        name: "user_str",
        field: "user_str",
        label: "Operador",
        align: "left",
        sortable: false,
    },
    {
        name: "actions",
        field: "actions",
        label: "Acciones",
        align: "center",
        sortable: false,
    },
]);

const rows = ref([]);
const searched = ref(false);

onMounted(() => {
    getColumnsTable();
    onRequest();
});

watch(
    () => pagination.value.date,
    () => {
        onRequest();
    }
);

watch(
    () => pagination.value.period,
    () => {
        onRequest();
    }
);

watch(
    () => pagination.value.paymentType,
    () => {
        onRequest();
    }
);

watch(
    () => pagination.value.paymentMethod,
    () => {
        onRequest();
    }
);

watch(
    () => pagination.value.createdBy,
    () => {
        onRequest();
    }
);

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);

const search = () => {
    let { search } = pagination.value;
    if (search && search.trim() !== "") {
        searched.value = true;
        onRequest();
    }
};

const searchClear = async () => {
    pagination.value.search = null;
    await onRequest();
    searched.value = false;
};

const onRequest = async (attrs) => {
    loading.value = true;
    if (attrs) {
        pagination.value = attrs.pagination;
    }
    let data = await cuts(props.userId, pagination.value);
    if (data !== null) {
        rows.value = data.data;
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
        }
    } catch (error) {
        console.log(error);
    }
};

const saveColumnsTable = async () => {
    try {
        const columnsData = columns.value.map((col) => ({
            name: col.name,
            visible: col.visible,
        }));

        await saveColumns(tableIdentifier.value, columnsData);
        showModal.value = false;
    } catch (error) {
        console.log(error);
    }
};
</script>
