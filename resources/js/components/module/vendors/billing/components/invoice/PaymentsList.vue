<template>
    <q-table
        v-table-resizable
        :columns="columns"
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
            <div class="row">
                <div class="col self-center">
                    <label for="search" style="margin-left: -10px"
                        >Número de recibo</label
                    >
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
                <div class="col self-center">
                    <label for="paymentDate">Fecha de pago</label>
                    <VueDatePicker
                        id="paymentDate"
                        v-model="pagination.paymentDate"
                        position="right"
                        locale="es"
                        :teleport="true"
                        range
                        week-start="0"
                        :format="customFormat"
                        :enableTimePicker="false"
                    >
                    </VueDatePicker>
                </div>
                <div class="col self-center">
                    <label for="paymentMethod" style="margin-left: -10px"
                        >Método de pago</label
                    >
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
                <div class="col self-center">
                    <label for="createdBy" style="margin-left: -10px"
                        >Creado por</label
                    >
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
            </div>
        </template>
        <template v-slot:header="props">
            <q-tr :props="props">
                <q-th auto-width />
                <q-th
                    class="text-bold"
                    v-for="col in props.cols"
                    :key="col.name"
                    :props="props"
                >
                    {{ col.label }}
                </q-th>
            </q-tr>
        </template>
        <template v-slot:body="props">
            <q-tr :props="props">
                <q-td auto-width>
                    <q-btn
                        size="sm"
                        color="primary"
                        round
                        dense
                        @click="props.expand = !props.expand"
                        :icon="props.expand ? 'remove' : 'add'"
                    />
                </q-td>
                <q-td v-for="col in props.cols" :key="col.name" :props="props">
                    {{ col.value }}
                </q-td>
            </q-tr>
            <q-tr v-show="props.expand" :props="props">
                <q-td colspan="100%">
                    <q-table
                        v-table-resizable
                        wrap-cells
                        dense
                        flat
                        :columns="columnsCommissions"
                        :rows="props.row.commissions"
                        :dark="darkMode"
                        hide-bottom
                    />
                </q-td>
            </q-tr> </template
    ></q-table>
</template>

<script setup>
import { watch, ref, onMounted, computed } from "vue";
import {
    getPaymentsBySeller,
    fieldsRules,
    getMethodsPayments,
    getAllUsers,
} from "../../helper/helper";
import VueDatePicker from "@vuepic/vue-datepicker";
import { darkMode } from "../../../../../../hook/appConfig";
import { useDatePicker } from "../../../../../../composables/useDatePicker";

const props = defineProps({
    user: String | Number,
});

const emits = defineEmits(["loaded"]);

const { customFormat } = useDatePicker();

const loading = ref(false);
const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 1,
    search: null,
    paymentDate: null,
    paymentMethod: null,
    createdBy: null,
});

const columns = [
    {
        name: "payment_date",
        field: "payment_date",
        label: "Fecha",
        align: "left",
        sortable: true,
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
        name: "invoice_number",
        field: "invoice_number",
        label: "Número de recibo",
        align: "left",
        sortable: true,
    },
];

const columnsCommissions = [
    {
        name: "period",
        field: "period",
        label: "Período",
        align: "left",
        sortable: true,
    },
    {
        name: "type",
        field: "type",
        label: "Tipo",
        align: "left",
        sortable: false,
        format: (val) => {
            return fieldsRules[val].label;
        },
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
];

const rows = ref([]);
const searched = ref(false);
const paymentMethods = ref([]);
const createdBy = ref([]);
const loadingPaymentMethods = ref(false);
const loadingCreatedBy = ref(false);

onMounted(() => {
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
</script>
<style scoped>
.q-field__append.row > button.q-icon {
    padding: 0px;
}
</style>
