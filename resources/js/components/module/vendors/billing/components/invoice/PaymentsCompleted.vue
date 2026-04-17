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
        loading-label="Obteniendo pagos, por favor espere..."
        no-data-label="No existen pagos disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Pagos por página"
        :pagination-label="(start, end, total) => `${start}-${end} de ${total}`"
        :rows-per-page-options="[20, 30, 50, 100]"
        @request="onRequest"
    >
        <template v-slot:top>
            <div class="row no-padding">
                <div class="col">
                    <label for="search">Número de recibo</label>
                    <q-input
                        for="search"
                        dense
                        outlined
                        debounce="300"
                        color="primary"
                        v-model="pagination.search"
                        :dark="darkMode"
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
                    <label for="paymentDate">Fecha de pago</label>
                    <VueDatePicker
                        id="paymentDate"
                        v-model="pagination.paymentDate"
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
                <div class="col">
                    <label for="period">Período pagado</label>
                    <VueDatePicker
                        id="period"
                        v-model="pagination.period"
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
                <div class="col">
                    <label for="paymentType">Tipo de pago</label>
                    <q-select
                        v-model="pagination.paymentType"
                        outlined
                        for="paymentType"
                        dense
                        options-dense
                        emit-value
                        :clearable="true"
                        map-options
                        :options="paymentTypes"
                        :dark="darkMode"
                    >
                        <template v-slot:selected-item="scope">
                            <q-item-label lines="1" style="margin-top: 5px">{{
                                scope.opt.label
                            }}</q-item-label>
                        </template>
                    </q-select>
                </div>
                <div class="col">
                    <label for="paymentMethod">Método de pago</label>
                    <q-select
                        v-model="pagination.paymentMethod"
                        outlined
                        for="paymentMethod"
                        dense
                        options-dense
                        option-label="type"
                        option-value="id"
                        emit-value
                        :clearable="true"
                        map-options
                        :options="paymentMethods"
                        :loading="loadingPaymentMethods"
                        :dark="darkMode"
                    >
                        <template v-slot:selected-item="scope">
                            <q-item-label lines="1" style="margin-top: 5px">{{
                                scope.opt.type
                            }}</q-item-label>
                        </template>
                    </q-select>
                </div>
                <div class="col">
                    <label for="createdBy">Creado por</label>
                    <q-select
                        v-model="pagination.createdBy"
                        outlined
                        for="createdBy"
                        option-label="name"
                        option-value="id"
                        dense
                        options-dense
                        emit-value
                        :clearable="true"
                        map-options
                        :options="createdBy"
                        :loading="loadingCreatedBy"
                        :dark="darkMode"
                        ><template v-slot:selected-item="scope">
                            <q-item-label lines="1" style="margin-top: 5px">{{
                                scope.opt.name
                            }}</q-item-label>
                        </template></q-select
                    >
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
                    icon="fas fa-file-signature"
                    flat
                    size="xs"
                    round
                    color="primary"
                    @click="onShowCropper(props.row)"
                />
                <q-btn
                    :href="`/vendedores/payments-sellers/payment-receipt-by-type/${props.row.id}`"
                    target="_blank"
                    icon="fa fa-file-pdf"
                    flat
                    size="xs"
                    round
                    color="primary"
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

    <q-dialog v-model="cropperDialog" @hide="file = null">
        <q-card style="width: 600px">
            <q-card-section>
                <cropper-field
                    name="avatar"
                    :image="copperImage"
                    @cancel="cropperDialog = false"
                    @finish="attachSignature"
                />
            </q-card-section>
        </q-card>
    </q-dialog>
</template>

<script setup>
import { watch, ref, onMounted, computed } from "vue";
import {
    getPaymentsBySeller,
    fieldsRules,
    getMethodsPayments,
    getAllUsers,
    saveSignature,
} from "../../helper/helper";
import VueDatePicker from "@vuepic/vue-datepicker";
import { darkMode } from "../../../../../../hook/appConfig";

import Modal from "../../../../../../shared/ModalSimple.vue";
import CropperField from "../../../../../../shared/CropperField.vue";
import { message } from "../../../../../../helpers/toastMsg";
import { useDataTable } from "../../../../../../composables/useDataTable";
import { useDatePicker } from "../../../../../../composables/useDatePicker";

const props = defineProps({
    user: String | Number,
});

const emits = defineEmits(["loaded"]);

const { customFormat } = useDatePicker();

const showModal = ref(false);
const loading = ref(false);
const cropperDialog = ref(false);
const copperImage = ref(null);

const tableIdentifier = ref("billing-payments-completed");
const { getColumns, saveColumns } = useDataTable();

const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 1,
    search: null,
    paymentDate: null,
    period: null,
    paymentMethod: null,
    paymentType: null,
    createdBy: null,
});

const columns = ref([
    {
        name: "invoice_number",
        field: "invoice_number",
        label: "Número de recibo",
        align: "left",
        sortable: true,
    },
    {
        name: "payment_date",
        field: "payment_date",
        label: "Fecha de pago",
        align: "left",
        sortable: true,
    },
    {
        name: "period",
        field: "period",
        label: "Período pagado",
        align: "left",
        sortable: false,
        format: (val, row) => {
            return row.commissions[0].period;
        },
    },
    {
        name: "type",
        field: "type",
        label: "Tipo de pago",
        align: "left",
        sortable: false,
        format: (val, row) => {
            return fieldsRules[row.commissions[0].type].label;
        },
    },
    {
        name: "payment_method_str",
        field: "payment_method_str",
        label: "Método de pago",
        align: "left",
        sortable: false,
    },
    {
        name: "created_str",
        field: "created_str",
        label: "Creado por",
        align: "left",
        sortable: false,
    },
    {
        name: "amount",
        field: "amount",
        label: "Cantidad pagada",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${val}`;
        },
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
const paymentMethods = ref([]);
const paymentTypes = ref([]);
const createdBy = ref([]);
const loadingPaymentMethods = ref(false);
const loadingCreatedBy = ref(false);
const currentPayment = ref(null);

onMounted(() => {
    for (let key of Object.keys(fieldsRules)) {
        paymentTypes.value.push({
            label: fieldsRules[key].label || "",
            value: key,
        });
    }
    getColumnsTable();
    getPaymentMethods();
    getCreatedBy();
    onRequest();
});

watch(
    () => pagination.value.paymentDate,
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
    let data = await getPaymentsBySeller(props.user, pagination.value);
    if (data !== null) {
        rows.value = data.data;
        pagination.value.rowsNumber = data.total;
    } else {
        rows.value = [];
    }
    loading.value = false;
    emits(
        "loaded",
        rows.value.reduce((t, p) => t + parseFloat(p.amount), 0)
    );
};

const onShowCropper = (object) => {
    currentPayment.value = object;
    copperImage.value = object.signature ?? null;
    cropperDialog.value = true;
};

const attachSignature = async (name, img) => {
    const result = await saveSignature(currentPayment.value.id, img);
    if (result) {
        currentPayment.value.signature = result.signature;
        message("Firma adjuntada correctamente", "success");
        cropperDialog.value = false;
    } else {
        message(
            "Error al adjuntar la firma, consulte al administrador.",
            "error"
        );
    }
};

const getPaymentMethods = async () => {
    loadingPaymentMethods.value = true;
    let data = await getMethodsPayments();
    paymentMethods.value = data;
    loadingPaymentMethods.value = false;
};

const getCreatedBy = async () => {
    loadingCreatedBy.value = true;
    let data = await getAllUsers();
    createdBy.value = data;
    loadingCreatedBy.value = false;
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
<style scoped>
.q-field__append.row > button.q-icon {
    padding: 0px;
}
</style>
