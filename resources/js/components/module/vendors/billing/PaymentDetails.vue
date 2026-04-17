<template>
    <div class="q-pa-md">
        <q-card class="mb-3">
            <q-card-section
                class="d-flex"
                style="justify-content: space-between"
            >
                <div class="text-h6">Registro de ventas</div>
            </q-card-section>

            <q-table
                v-table-resizable
                :rows="payments"
                :columns="columns"
                :filter="filter"
                :dark="darkMode"
                :rows-per-page-label="'Elementos por página'"
                :rows-per-page-options="rowPerPageOptions"
                v-model:pagination="pagination"
                binary-state-sort
                @request="onRequest"
                ref="tableRef"
                :loading="loading"
                no-data-label="No hay elementos para mostrar"
            >
                <template v-slot:top="props">
                    <div
                        class="d-flex justify-content-end align-items-center gap-3"
                    >
                        <!-- <label>Buscar</label> -->
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

                        <button
                            id="btn_payment_details"
                            class="btn btn-outline-secondary"
                            @click="onRequest(props)"
                        >
                            <i class="fas fa-sync"></i>
                        </button>
                    </div>
                </template>

                <template v-slot:body-cell-amount="props">
                    <q-td :props="props"> ${{ props.row.amount }} </q-td>
                </template>

                <template v-slot:body-cell-remaining_shares="props">
                    <q-td :props="props">
                        <span class="badge-primary">
                            {{ props.row.remaining_shares }}
                        </span>
                    </q-td>
                </template>
            </q-table>
        </q-card>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import {
    getListPaymentsOfCustomers,
    date,
    dateSearchData,
    filtersCustomers,
} from "./helper/helper";
import { debounce } from "lodash";
import { darkMode } from "../../../../hook/appConfig";
import { useDatePicker } from "../../../../composables/useDatePicker";

const props = defineProps({
    user_id: {
        type: Number,
        required: true,
    },
    seller_id: {
        type: Number,
        required: true,
    },
});

const { customFormat } = useDatePicker();

const columns = [
    {
        name: "id",
        align: "center",
        label: "Id del cliente",
        field: "id",
        sortable: true,
        visible: true,
    },
    {
        name: "plan_sold",
        align: "start",
        label: "Nombre del cliente",
        field: "plan_sold",
        sortable: true,
        visible: true,
    },
    {
        name: "amount",
        align: "center",
        label: "Precio del paquete",
        field: "amount",
        sortable: true,
        visible: true,
    },
    {
        name: "remaining_shares",
        align: "center",
        label: "Cuotas restantes",
        field: "remaining_shares",
        sortable: true,
        visible: true,
    },
];
const rowPerPageOptions = ref([5, 10, 15, 25, 50, 100, 0]);
const loading = ref(false);
const filter = ref("");
const tableRef = ref();

const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 50,
    rowsNumber: 0,
    sortBy: "id",
});

const seller_id = ref(props.user_id);
const startPagination = ref(0);
const payments = ref([]);

onMounted(async () => {
    tableRef.value.requestServerInteraction();
});

const getPaymentOfSeller = async (props) => {
    const { page, rowsPerPage, sortBy, descending } = props.pagination;
    const filter = props.filter;
    startPagination.value = (page - 1) * rowsPerPage;
    pagination.value.page = page;
    pagination.value.rowsPerPage = rowsPerPage;
    pagination.value.sortBy = sortBy;
    pagination.value.descending = descending;
    loading.value = true;
    try {
        const { data, total } = await getListPaymentsOfCustomers(
            seller_id.value,
            pagination.value.page,
            pagination.value.rowsPerPage,
            filter,
            startPagination.value,
            pagination.value.descending,
            pagination.value.sortBy,
            filtersCustomers.value
        );
        payments.value = data.map((payment) => {
            const totalPayments = 5;
            const completedPayments = payment.completed_payment;
            return {
                id: payment.id,
                plan_sold: payment.full_name,
                amount: parseFloat(payment.package_price),
                remaining_shares: totalPayments - completedPayments,
            };
        });
        pagination.value.page = page;
        pagination.value.rowsPerPage = rowsPerPage;
        pagination.value.rowsNumber = total;
    } catch (error) {
        console.error("Error in onRequest:", error);
    } finally {
        loading.value = false;
    }
};

const onRequestDebounced = debounce((props) => {
    getPaymentOfSeller(props);
}, 3000);

function onRequest(props) {
    if (props.filter != "") {
        onRequestDebounced(props);
    } else {
        getPaymentOfSeller(props);
    }
}

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

watch(date, async () => {
    filtersCustomers.value = {
        ...filtersCustomers.value,
        activation_date: date.value,
    };
    dateSearchData.value = customFormat(date.value);
    try {
        const { data, total } = await getListPaymentsOfCustomers(
            seller_id.value,
            pagination.value.page,
            pagination.value.rowsPerPage,
            filter.value,
            startPagination.value,
            pagination.value.descending,
            pagination.value.sortBy,
            filtersCustomers.value
        );
        payments.value = data.map((payment) => {
            const totalPayments = 5;
            const completedPayments = payment.completed_payment;
            return {
                id: payment.id,
                plan_sold: payment.full_name,
                amount: parseFloat(payment.package_price),
                remaining_shares: totalPayments - completedPayments,
            };
        });
        pagination.value.page = pagination.value.page;
        pagination.value.rowsPerPage = pagination.value.rowsPerPage;
        pagination.value.rowsNumber = total;
    } catch (error) {
        console.error("Error in onRequest:", error);
    } finally {
        loading.value = false;
    }
});
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
