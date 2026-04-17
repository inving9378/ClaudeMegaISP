<template>
    <q-card class="my-card" flat bordered>
        <q-item>
            <q-item-section>
                <q-item-label>Proveedores/Gastos</q-item-label>
            </q-item-section>
            <q-item-section class="no-padding" avatar>
                <form-suppliers-expenses-component
                    :box-id="box.id"
                    :payment-methods="paymentMethods"
                    @created="(r) => rows.push(r)"
                    v-if="hasAdd && !closing && !box.closed"
                />
            </q-item-section>
            <q-item-section class="no-padding" avatar>
                <q-btn
                    color="primary"
                    class="q-mr-sm"
                    label="..."
                    @click="showModal = true"
                />
            </q-item-section>
            <q-item-section class="no-padding" avatar>
                <q-btn
                    color="primary"
                    class="q-mr-sm"
                    icon="history"
                    :loading="loading"
                    @click="onRequest"
                />
            </q-item-section>
        </q-item>

        <q-separator />

        <q-card-section>
            <q-table
                v-table-resizable="visibleColumns"
                :columns="visibleColumns"
                :rows="filteredRows"
                :loading="loading"
                :dark="darkMode"
                flat
                wrap-cells
                row-key="id"
                loading-label="Obteniendo registros, por favor espere..."
                no-data-label="No existen registros disponibles"
                no-results-label="No se encontraron coincidencias"
                rows-per-page-label="registros por página"
                :pagination-label="
                    (start, end, total) => `${start}-${end} de ${total}`
                "
                :rows-per-page-options="[20, 30, 50, 100]"
            >
                <template v-slot:top>
                    <div class="row no-padding">
                        <div class="col">
                            <label>Número de recibo/Cantidad</label>
                            <q-input
                                dense
                                outlined
                                color="primary"
                                v-model="filters.search"
                                clearable
                                :dark="darkMode"
                            />
                        </div>
                        <div class="col">
                            <label>Método de pago</label>
                            <q-select
                                v-model="filters.paymentMethod"
                                outlined
                                dense
                                options-dense
                                option-label="type"
                                option-value="id"
                                emit-value
                                :clearable="true"
                                map-options
                                :options="paymentMethods"
                                :dark="darkMode"
                            >
                                <template v-slot:selected-item="scope">
                                    <q-item-label
                                        lines="1"
                                        style="margin-top: 5px"
                                        >{{ scope.opt.type }}</q-item-label
                                    >
                                </template>
                            </q-select>
                        </div>
                        <div class="col">
                            <label>Fecha de pago</label>
                            <VueDatePicker
                                v-model="filters.paymentDate"
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
                    </div>
                </template>
                <template v-slot:body-cell-actions="props">
                    <q-td class="text-center">
                        <form-suppliers-expenses-component
                            :object="props.row"
                            :payment-methods="paymentMethods"
                            @updated="onUpdateRow"
                            v-if="hasEdit"
                        />
                        <q-btn
                            icon="delete"
                            flat
                            round
                            dense
                            color="danger"
                            size="12px"
                            :loading="props.row.loading"
                            @click="destroy(props.row)"
                            v-if="hasDelete"
                        />
                    </q-td>
                </template>
                <template
                    v-slot:bottom-row
                    v-if="visibleColumns.map((c) => c.name).includes('amount')"
                >
                    <tr>
                        <td
                            v-for="c in visibleColumns.length -
                            (hasActions ? 3 : 2)"
                            :key="`col-payment-expenses-${c}`"
                        ></td>
                        <td
                            class="text-right text-bold"
                            style="padding-right: 0"
                        >
                            Total efectivo:
                        </td>
                        <td>{{ totalAmount }}</td>
                        <td v-if="hasActions"></td>
                    </tr>
                </template>
            </q-table>
        </q-card-section>
    </q-card>

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
import { ref, onMounted, computed, onBeforeMount, watch } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import Modal from "../../../../../../shared/ModalSimple.vue";
import FormSuppliersExpensesComponent from "./FormSuppliersExpensesComponent.vue";
import { darkMode } from "../../../../../../hook/appConfig";
import { useDataTable } from "../../../../../../composables/useDataTable";
import { useDatePicker } from "../../../../../../composables/useDatePicker";
import { error500, message } from "../../../../../../helpers/toastMsg";
import Swal from "sweetalert2";
import { getMethodsPayments } from "../../helper/helper";
import moment from "moment";
import {
    destroySuppliersExpenses,
    listSuppliersExpenses,
} from "../../helper/cutSuppliersExpenses";

const props = defineProps({
    box: Object,
    hasPermission: Object,
    closing: Boolean,
});

const emits = defineEmits(["loaded"]);

const { customFormat } = useDatePicker();

const showModal = ref(false);
const loading = ref(false);
const loadingPaymentMethods = ref(false);
const tableIdentifier = ref("billing-suppliers-expenses");
const { getColumns, saveColumns } = useDataTable();

const filters = ref(null);

const columns = ref([
    {
        name: "invoice_number",
        field: "invoice_number",
        label: "Número de recibo",
        align: "left",
        sortable: true,
        required: true,
    },
    {
        name: "payment_method_str",
        field: "payment_method_str",
        label: "Método de pago",
        align: "left",
        sortable: true,
    },
    {
        name: "payment_date",
        field: "payment_date",
        label: "Fecha",
        align: "left",
        sortable: true,
        format: (val) => {
            try {
                return moment(val).format("DD/MM/YYYY");
            } catch (error) {
                return val;
            }
        },
    },
    {
        name: "comments",
        field: "comments",
        label: "Comentarios",
        align: "left",
        sortable: true,
    },
    {
        name: "amount",
        field: "amount",
        label: "Cantidad",
        align: "left",
        sortable: true,
        required: true,
    },
]);

const rows = ref([]);
const paymentMethods = ref([]);

onBeforeMount(() => {
    initFilters();
});

onMounted(() => {
    if (hasActions.value) {
        columns.value.push({
            name: "actions",
            field: "actions",
            label: "Acciones",
            align: "center",
            sortable: false,
            required: true,
        });
    }
    getColumnsTable();
    onRequest();
    loadPaymentMethods();
});

watch(
    () => props.box,
    () => {
        initFilters();
        onRequest();
    },
    {
        deep: true,
    }
);

const initFilters = () => {
    filters.value = {
        search: null,
        paymentDate: null,
        period: null,
        paymentMethod: null,
        paymentType: null,
        createdBy: null,
    };
};

const hasAdd = computed(() => {
    return (
        props.hasPermission?.data.canView("seller_cuts_add_expenses") ?? false
    );
});

const hasEdit = computed(() => {
    return (
        props.hasPermission?.data.canView("seller_cuts_edit_expenses") ?? false
    );
});

const hasDelete = computed(() => {
    return (
        props.hasPermission?.data.canView("seller_cuts_delete_expenses") ??
        false
    );
});

const hasActions = computed(() => {
    return (hasAdd.value || hasEdit.value) && !props.box.closed;
});

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);

const filteredRows = computed(() => {
    let temp = rows.value;
    const { search, paymentMethod, paymentDate } = filters.value;
    if (search) {
        let s = search.toLowerCase();
        temp = temp.filter(
            (r) =>
                r.invoice_number.toLowerCase().includes(s) ||
                r.amount.toString().includes(s)
        );
    }
    if (paymentMethod && paymentMethod > 0) {
        temp = temp.filter((r) => r.payment_method_id === paymentMethod);
    }
    if (paymentDate) {
        let start = paymentDate[0],
            end = paymentDate[1];
        if (start && end) {
            temp = temp.filter((r) =>
                moment(r.payment_date).isBetween(start, end, "day", "[]")
            );
        } else if (start) {
            temp = temp.filter((r) =>
                moment(r.payment_date).isSame(start, "day")
            );
        }
    }
    return temp;
});

const totalAmount = computed(() => {
    const total = filteredRows.value
        .filter((r) => r.payment_method_id === 1)
        .reduce((t, p) => t + p.amount, 0);
    emits("loaded", total);
    return total;
});

const onRequest = async () => {
    loading.value = true;
    let data = await listSuppliersExpenses(props.box.id);
    if (data !== null) {
        rows.value = data;
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

const loadPaymentMethods = async () => {
    loadingPaymentMethods.value = true;
    let data = await getMethodsPayments();
    paymentMethods.value = data;
    loadingPaymentMethods.value = false;
};

const onUpdateRow = (r) => {
    const row = rows.value.find((rr) => rr.id === r.id);
    if (row) {
        Object.assign(row, r);
    }
};

const destroy = async (object) => {
    Swal.fire({
        title: "Confirmación!",
        text: "Seguro que desea eliminar este gasto?",
        icon: "question",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Si",
        cancelButtonText: "No",
    }).then(async (result) => {
        if (result.isConfirmed) {
            object.loading = true;
            const result = await destroySuppliersExpenses(object.id);
            if (result) {
                message("Gasto eliminado correctamente");
                rows.value = rows.value.filter((r) => r.id !== object.id);
            } else {
                object.loading = false;
                error500();
            }
        }
    });
};
</script>
