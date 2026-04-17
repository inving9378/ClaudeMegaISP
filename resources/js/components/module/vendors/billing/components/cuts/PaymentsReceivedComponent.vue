<template>
    <div class="row no-gutter-x">
        <div class="col-xs-12 col-sm-12 col-md-8 col-lg-8 col-xl-8">
            <q-card class="my-card q-mr-xs" flat bordered>
                <q-item>
                    <q-item-section>
                        <q-item-label>Lista de pagos recibidos</q-item-label>
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
                                    <label>ID/Recibo/Cliente/Cantidad</label>
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
                                                >{{
                                                    scope.opt.type
                                                }}</q-item-label
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
                        <template
                            v-slot:bottom-row
                            v-if="
                                visibleColumns
                                    .map((c) => c.name)
                                    .includes('amount')
                            "
                        >
                            <tr>
                                <td
                                    v-for="c in visibleColumns.length - 2"
                                    :key="`col-payment-received-${c}`"
                                ></td>
                                <td
                                    class="text-right text-bold"
                                    style="padding-right: 0"
                                >
                                    Total efectivo:
                                </td>
                                <td>{{ totalAmount }}</td>
                            </tr>
                        </template>
                    </q-table>
                </q-card-section>
            </q-card>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-4 col-lg-4 col-xl-4">
            <log-activities :user="userId" padding="14px" />
        </div>
    </div>

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
import { ref, onMounted, watch, computed, onBeforeMount } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import Modal from "../../../../../../shared/ModalSimple.vue";
import LogActivities from "../../../components/LogActivities.vue";
import { useDataTable } from "../../../../../../composables/useDataTable";
import { useDatePicker } from "../../../../../../composables/useDatePicker";
import { darkMode } from "../../../../../../hook/appConfig";
import { getReceivedPaymentsByBox } from "../../helper/cutBox";
import { getMethodsPayments } from "../../helper/helper";
import moment from "moment";

const props = defineProps({
    userId: Number,
    box: Object,
});

const emits = defineEmits(["loaded"]);

const { customFormat } = useDatePicker();

const showModal = ref(false);
const loading = ref(false);
const tableIdentifier = ref("billing-payments-received");
const { getColumns, saveColumns } = useDataTable();

const columns = ref([
    {
        name: "id",
        field: "id",
        label: "ID",
        align: "left",
        sortable: true,
        required: true,
    },
    {
        name: "receipt",
        field: "receipt",
        label: "Número de recibo",
        align: "left",
        sortable: true,
        required: true,
    },
    {
        name: "client_main_information",
        field: "client_main_information",
        label: "Cliente",
        align: "left",
        sortable: true,
        required: true,
        format: (val) => {
            return `${val.client_id} - ${val.client_name_with_fathers_names}`;
        },
    },
    {
        name: "created_at",
        field: "created_at",
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
        name: "payment_method_id",
        field: "payment_method_id",
        label: "Tipo",
        align: "left",
        sortable: false,
        format: (val) => {
            let method = paymentMethods.value.find((m) => m.id === val);
            return method?.type ?? "No definido";
        },
    },
    {
        name: "amount",
        field: "amount",
        label: "Monto",
        align: "left",
        sortable: true,
        required: true,
    },
]);

const filters = ref(null);

const loadingPaymentMethods = ref(false);
const rows = ref([]);
const paymentMethods = ref([]);

onBeforeMount(() => {
    initFilters();
});

onMounted(() => {
    getColumnsTable();
    loadPaymentMethods();
    onRequest();
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
                r.receipt.toLowerCase().includes(s) ||
                r.id.toString().includes(s) ||
                r.amount.toString().includes(s) ||
                r.client_main_information.client_id.toString().includes(s) ||
                r.client_main_information.client_name_with_fathers_names
                    .toLowerCase()
                    .includes(s)
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
                moment(r.created_at).isBetween(start, end, "day", "[]")
            );
        } else if (start) {
            temp = temp.filter((r) =>
                moment(r.created_at).isSame(start, "day")
            );
        }
    }
    return temp;
});

const totalAmount = computed(() => {
    let total = filteredRows.value
        .filter((r) => r.payment_method_id === 1)
        .reduce((t, p) => t + p.amount, 0);
    emits("loaded", total);
    return total;
});

const onRequest = async () => {
    loading.value = true;
    let data = await getReceivedPaymentsByBox(props.box.id);
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
</script>
