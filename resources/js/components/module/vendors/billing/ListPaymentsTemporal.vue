<template>
    <div>
        <div class="q-pa-md">
            <q-card>
                <q-card-section
                    class="d-flex"
                    style="justify-content: space-between"
                >
                    <div class="text-h6">Lista de pagos</div>
                </q-card-section>

                <q-table
                    v-table-resizable="visibleColumns"
                    row-key="id"
                    v-model:pagination="pagination"
                    ref="tableRef"
                    no-data-label="No hay elementos para mostrar"
                    :rows="payments"
                    :columns="visibleColumns"
                    :loading="loading"
                    :dark="darkMode"
                    :rows-per-page-label="'Elementos por página'"
                    :rows-per-page-options="rowPerPageOptions"
                    :filter="filter"
                    @request="getListPayments"
                    style="max-height: 70vh"
                >
                    <template v-slot:body-cell-action="props">
                        <div class="d-flex justify-content-center">
                            <span
                                class="text-primary me-2"
                                role="button"
                                @click="getTicket(props.row.id)"
                            >
                                <i class="far fa-file-pdf"></i>
                            </span>
                            <span
                                class="text-primary me-2"
                                role="button"
                                @click="openModalEditPayment(props.row.id)"
                            >
                                <i class="fas fa-edit"></i>
                            </span>
                            <span
                                class="text-primary"
                                role="button"
                                @click="deleteRow(props.row.id)"
                            >
                                <i class="fas fa-trash"></i>
                            </span>
                        </div>
                    </template>
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

                            <!-- <select
                                class="form-select"
                                aria-label="Default select example"
                                v-model="state"
                                style="width: 200px"
                                @change="filterTable()"
                            >
                                <option value="all">Todos</option>
                                <option value="Por pagar">Por pagar</option>
                                <option value="Pendiente">Pendiente</option>
                                <option value="Pagado">Pagado</option>
                            </select> -->

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
                    <template v-slot:body-cell-method_of_payment="props">
                        <q-td :props="props">
                            <span class="badge-primary">{{
                                props.row.method_of_payment
                            }}</span>
                        </q-td>
                    </template>
                    <template v-slot:body-cell-amount="props">
                        <q-td :props="props"> $ {{ props.row.amount }} </q-td>
                    </template>
                    <template v-slot:body-cell-status="props">
                        <q-td :props="props">
                            <span :class="getBadgeClass(props.row.status)">
                                {{ props.row.status }}
                            </span>
                        </q-td>
                    </template>
                </q-table>
            </q-card>
        </div>
        <modal
            :show="showModal"
            :size="'lg'"
            @update:show="showModal = $event"
            title="Ver comprobante"
        >
            <template #body>
                <Ticket
                    :name="ticket.name_complete"
                    :address="ticket.address"
                    :city="ticket.city_municipality"
                    :state="ticket.state_country"
                    :zip="ticket.code_postal"
                    :data="ticket.payments"
                    :total="ticket.total_amount"
                    :columns="columnsTicket"
                />
            </template>

            <template #footer>
                <button
                    class="btn btn-success waves-effect waves-light"
                    @click="downloadReceipt"
                >
                    Descargar <i class="fas fa-file-download"></i>
                </button>
            </template>
        </modal>
        <!-- ----------------------------------------------------------------------->
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
        <!-- ----------------------------------------------------------------------->
        <EditPayment
            :payment_id="idPayment"
            :seller_id="props.seller_id"
            v-model:showModalEdit="showModalEdit"
            @updateListPayments="reloadTable"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import Swal from "sweetalert2";
import Modal from "../../../../shared/ModalSimple.vue";
import EditPayment from "./EditPayment.vue";
import Ticket from "../components/Ticket.vue";
import {
    getAllPayments,
    deletePayment,
    getTicketOfSeller,
    dowloadTicket,
} from "./helper/helper";
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

const emit = defineEmits(["updateTotalAmount"]);

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
        name: "payment_number",
        align: "start",
        label: "Número de recibo",
        field: "payment_number",
        sortable: true,
        visible: true,
    },
    {
        name: "payment_date",
        align: "start",
        label: "Fecha de pago",
        field: "payment_date",
        sortable: true,
        visible: true,
    },
    {
        name: "amount",
        align: "start",
        label: "Monto",
        field: "amount",
        sortable: true,
        visible: true,
    },
    {
        name: "method_of_payment",
        align: "start",
        label: "Método de pago",
        field: "method_of_payment",
        sortable: true,
        visible: true,
    },
    {
        name: "comment",
        align: "start",
        label: "Comentario",
        field: "comment",
        sortable: true,
        visible: true,
    },
    {
        name: "status",
        align: "start",
        label: "Estado",
        field: "status",
        sortable: true,
        visible: true,
    },
    {
        name: "created_by_name",
        align: "start",
        label: "Pago creado por",
        field: "created_by_name",
        sortable: true,
        visible: true,
    },
    {
        name: "action",
        label: "Acciones",
        align: "center",
        field: "action",
        sortable: false,
        visible: true,
    },
]);

const columnsTicket = [
    { name: "Número de pago", value: "payment_number" },
    { name: "Fecha de pago", value: "payment_date" },
    { name: "Monto", value: "amount" },
    { name: "Método de pago", value: "method_of_payment" },
];

const payments = ref([]);
const rowPerPageOptions = ref([5, 10, 15, 25, 50, 100, 0]);
const loading = ref(false);
const date = ref();
const state = ref("all");
const filter = ref("");
const showModal = ref(false);
const showModalColumns = ref(false);
const showModalEdit = ref(false);
const idPayment = ref(null);
const ticket = ref({});
const tableIdentifier = ref("pagos-vendedores");
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
});

const getListPayments = async ({ pagination: { page, rowsPerPage } }) => {
    loading.value = true;

    try {
        const { data, total } = await getAllPayments(
            props.seller_id,
            page,
            rowsPerPage,
            filter.value
        );

        payments.value.splice(0, payments.value.length, ...data);

        pagination.value.page = page;
        pagination.value.rowsPerPage = rowsPerPage;
        pagination.value.rowsNumber = total;
    } catch (error) {
        console.error("Error in onRequest:", error);
    } finally {
        loading.value = false;
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

const deleteRow = async (paymentId /* commissionId */) => {
    try {
        const confirmed = await Swal.fire({
            title: "Confirmar eliminación",
            text: "¿Está seguro de que desea eliminar este pago?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Sí, Eliminar",
            cancelButtonText: "Cancelar",
        });

        if (confirmed.isConfirmed) {
            const response = await deletePayment(paymentId);

            emit("updateTotalAmount");

            Swal.fire({
                title: "Eliminado",
                text: response.message,
                icon: "success",
            });

            getListPayments({ pagination: pagination.value });
        }
    } catch (error) {
        Swal.fire({
            title: "Error",
            text: "Ocurrio un error",
            icon: "error",
        });
    }
};

async function getTicket(id) {
    await getTicketOfSeller(props.seller_id, id).then((response) => {
        ticket.value = response;
        showModal.value = true;
        idPayment.value = id;
    });
}

const downloadReceipt = async () => {
    try {
        await dowloadTicket(props.seller_id, idPayment.value);
    } catch (error) {
        console.log(error);
    }
};

const openModalEditPayment = (paymentId) => {
    idPayment.value = paymentId;
    showModalEdit.value = true;
};

const tableRef = ref();

const reloadTable = async () => {
    await getListPayments({ pagination: pagination.value });
};

const getBadgeClass = (status) => {
    switch (status) {
        case "Pagado":
            return "badge-green";
        case "Pendiente":
            return "badge-yellow";
        case "Por pagar":
            return "badge-red";
        default:
            return "badge-primary";
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

const filterTable = async () => {
    await reloadTable();

    payments.value = payments.value.filter((transaction) => {
        if (state.value === "all") {
            return true; // Include all transactions when state is 'all'
        } else {
            return transaction.status === state.value; // Include transactions where status matches state.value
        }
    });
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

.badge-green {
    background-color: #29cc97;
    color: #ffffff;
    padding: 0 8px;
    padding-top: 2px;
    padding-bottom: 2px;
    border-radius: 3px;
    font-weight: 500;
}

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
    background-color: #d63384;
    color: #ffffff;
    padding: 0 8px;
    padding-top: 2px;
    padding-bottom: 2px;
    border-radius: 3px;
    font-weight: 500;
}
</style>
