<template>
    <div>
        <div class="q-pa-md">
            <q-card>
                <q-card-section
                    class="d-flex"
                    style="justify-content: space-between"
                >
                    <div class="text-h6">Lista de transacciones</div>
                </q-card-section>
                <q-table
                    v-table-resizable="visibleColumns"
                    row-key="id"
                    v-model:pagination="pagination"
                    ref="tableRef"
                    no-data-label="No hay elementos para mostrar"
                    :dark="darkMode"
                    :rows="transactions"
                    :columns="visibleColumns"
                    :loading="loading"
                    :rows-per-page-label="'Elementos por página'"
                    :rows-per-page-options="rowPerPageOptions"
                    :filter="filter"
                    @request="getListTransactions"
                    style="max-height: 70vh"
                >
                    <template v-slot:top="props">
                        <div
                            class="d-flex justify-content-end align-items-center gap-3"
                        >
                            <button
                                type="button"
                                class="btn btn-outline-info"
                                @click="showModalColumns = true"
                            >
                                ...
                            </button>

                            <select
                                class="form-select"
                                aria-label="Default select example"
                                v-model="methodPayment"
                                @change="searchForRange"
                                style="width: 200px"
                            >
                                <option value="all">Todos</option>
                                <option
                                    v-for="method in methodsPayments"
                                    :key="method.id"
                                    :value="method.id"
                                >
                                    {{ method.type }}
                                </option>
                            </select>

                            <label>Periodo</label>
                            <VueDatePicker
                                v-model="date"
                                position="left"
                                locale="es"
                                :max-date="new Date()"
                                :teleport="true"
                                placeholder="Periodo"
                                range
                                multi-calendars
                                style="width: 350px"
                            >
                            </VueDatePicker>
                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                @click="searchForRange"
                            >
                                <i class="fas fa-search"></i>
                            </button>

                            <button
                                class="btn btn-outline-secondary"
                                @click="reloadTable"
                            >
                                <i class="fas fa-sync"></i>
                            </button>

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

                            <q-input
                                borderless
                                dense
                                v-model="filter"
                                placeholder="Buscar"
                                class="mb-0"
                                style="margin-left: 16px; border: 1px solid"
                                :dark="darkMode"
                            >
                            </q-input>
                        </div>
                    </template>
                    <template v-slot:body-cell-method_of_payment_name="props">
                        <q-td :props="props">
                            <span class="badge-primary">{{
                                props.row.method_of_payment_name
                            }}</span>
                        </q-td>
                    </template>
                    <template v-slot:body-cell-total_amount="props">
                        <q-td :props="props">
                            $ {{ props.row.total_amount }}
                        </q-td>
                    </template>
                    <template v-slot:body-cell-account_balance="props">
                        <q-td :props="props">
                            $ {{ props.row.account_balance }}
                        </q-td>
                    </template>
                </q-table>
            </q-card>
        </div>
        <!------------------------------------------------------------------------->
        <modal
            :show="showModalColumns"
            :size="'xs'"
            @update:show="showModalColumns = $event"
            title="Mostrar columnas/Ocultar columnas"
        >
            <template #body>
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
            </template>
            <template #footer>
                <button class="btn btn-primary" @click="saveColumnsTable">
                    Guardar
                </button>
            </template>
        </modal>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import Modal from "../../../../shared/ModalSimple.vue";
import { getMethodsPayments, getTransactionsBySeller } from "./helper/helper";
import { darkMode } from "../../../../hook/appConfig";
import { useDataTable } from "../../../../composables/useDataTable";

const props = defineProps({
    seller_id: {
        type: Number,
        required: true,
    },
    updateListPayments: {
        type: Boolean,
        required: true,
    },
});

const columns = ref([
    {
        name: "id",
        align: "start",
        label: "ID",
        field: "id",
        sortable: true,
        visible: true,
    },
    {
        name: "transaction_date",
        align: "center",
        label: "Fecha de la transacción",
        field: "transaction_date",
        sortable: true,
        visible: true,
    },
    {
        name: "previous_balance",
        align: "center",
        label: "Monto de transacción",
        field: "previous_balance",
        sortable: true,
        visible: true,
    },
    {
        name: "new_balance",
        align: "center",
        label: "Saldo restante",
        field: "new_balance",
        sortable: true,
        visible: true,
    },
    {
        name: "method_of_payment_name",
        align: "start",
        label: "Método de pago",
        field: "method_of_payment_name",
        sortable: true,
        visible: true,
    },
]);

const transactions = ref([]);
const rowPerPageOptions = ref([5, 10, 15, 25, 50, 100, 0]);
const loading = ref(false);
const date = ref();
const methodPayment = ref("all");
const filter = ref("");
const showModalColumns = ref(false);
const methodsPayments = ref([]);
const tableIdentifier = ref("transacciones");
const { getColumns, saveColumns } = useDataTable();

const pagination = ref({
    page: 1,
    rowsPerPage: 50,
    rowsNumber: 0,
});

onMounted(() => {
    getColumnsTable();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);
    const endDate = new Date();
    date.value = [startDate, endDate];
    tableRef.value.requestServerInteraction();
    getListMethodsPayments();
});

const getListTransactions = async ({ pagination: { page, rowsPerPage } }) => {
    loading.value = true;
    const startDate = date.value[0].toISOString().slice(0, 10);
    const endDate = date.value[1].toISOString().slice(0, 10);

    try {
        const { data, total } = await getTransactionsBySeller(
            props.seller_id,
            startDate,
            endDate,
            methodPayment.value,
            page,
            rowsPerPage,
            filter.value
        );

        transactions.value.splice(0, transactions.value.length, ...data);

        pagination.value.page = page;
        pagination.value.rowsPerPage = rowsPerPage;
        pagination.value.rowsNumber = total;
    } catch (error) {
        console.error("Error in onRequest:", error);
    } finally {
        loading.value = false;
    }
};

const getListMethodsPayments = async () => {
    try {
        methodsPayments.value = await getMethodsPayments();
    } catch (error) {
        console.log(error);
    }
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
        showModalColumns.value = false;
    } catch (error) {
        console.log(error);
    }
};

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);

const searchForRange = async () => {
    await getListTransactions({ pagination: pagination.value });
};

const tableRef = ref();

const reloadTable = () => {
    getListTransactions({ pagination: pagination.value });
};

watch(
    () => props.updateListPayments,
    () => {
        reloadTable();
    }
);
</script>

<style scoped>
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
