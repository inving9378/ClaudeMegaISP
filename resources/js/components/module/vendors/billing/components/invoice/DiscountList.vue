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
                    <label for="discountDate">Fecha de cobro</label>
                    <VueDatePicker
                        id="discountDate"
                        v-model="pagination.discountDate"
                        position="right"
                        locale="es"
                        :teleport="true"
                        range
                        week-start="0"
                        :format="customFormat"
                        :enableTimePicker="false"
                        :dark="darkMode"
                    >
                    </VueDatePicker>
                </div>
                <div class="col self-center">
                    <label for="createdBy" style="margin-left: -10px"
                        >Cobrado por</label
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
                        :dark="darkMode"
                        map-options
                        :options="createdBy"
                        :loading="loadingCreatedBy"
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
                <q-td
                    v-for="col in props.cols"
                    :key="col.name"
                    :props="props"
                    :class="col.name === 'actions' ? 'text-center' : null"
                >
                    <q-btn
                        :href="`/vendedores/payments-sellers/discount-receipt/${props.row.id}`"
                        target="_blank"
                        icon="fa fa-file-pdf"
                        flat
                        size="xs"
                        round
                        color="primary"
                        v-if="col.name === 'actions'"
                    />
                    <span v-else>
                        {{ col.value }}
                    </span>
                </q-td>
            </q-tr>
            <q-tr v-show="props.expand" :props="props">
                <q-td colspan="100%">
                    <q-table
                        v-table-resizable
                        dense
                        wrap-cells
                        flat
                        :columns="columnsDiscount"
                        :rows="props.row.sales.map((s) => s.data)"
                        :dark="darkMode"
                        hide-bottom
                    />
                </q-td>
            </q-tr>
        </template>
    </q-table>
</template>

<script setup>
import { watch, ref, onMounted } from "vue";
import { getDiscountsBySeller, getAllUsers } from "../../helper/helper";
import VueDatePicker from "@vuepic/vue-datepicker";
import { darkMode } from "../../../../../../hook/appConfig";
import { useDatePicker } from "../../../../../../composables/useDatePicker";

const props = defineProps({
    seller_id: {
        type: Number,
        required: true,
    },
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
    discountDate: null,
    createdBy: null,
});

const columns = [
    {
        name: "date",
        field: "date",
        label: "Fecha",
        align: "left",
        sortable: true,
    },
    {
        name: "discount",
        field: "discount",
        label: "Cantidad descontada",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${val}`;
        },
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
    {
        name: "actions",
        field: "actions",
        label: "Acciones",
        align: "center",
        sortable: false,
    },
];

const columnsDiscount = [
    {
        name: "client_id",
        field: "client_id",
        label: "Id del cliente",
        align: "left",
        sortable: true,
    },
    {
        name: "client",
        field: "client",
        label: "Nombre del cliente",
        align: "left",
        sortable: true,
    },
    {
        name: "service",
        field: "service",
        label: "Servicio",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "date",
        field: "date",
        label: "Activación",
        align: "right",
        sortable: true,
    },
    {
        name: "installation_cost",
        field: "installation_cost",
        label: "Costo de instalación",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "amount_by_client",
        field: "amount_by_client",
        label: "Pagado por el cliente",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "discount_by_client",
        field: "discount_by_client",
        label: "Deuda por cliente",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "discount_by_additional_sale",
        field: "discount_by_additional_sale",
        label: "Deuda por venta adicional",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "total_discount",
        field: "total_discount",
        label: "Deuda total",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "total",
        field: "total",
        label: "Total pagado",
        align: "right",
        sortable: true,
        format: (val, row) => {
            return `$${
                Math.round(
                    (parseFloat(row.to_pay) +
                        parseFloat(row.amount_by_seller)) *
                        100
                ) / 100
            }`;
        },
    },
    {
        name: "to_pay",
        field: "to_pay",
        label: "Deuda pagada",
        align: "right",
        sortable: true,
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
    {
        name: "current_debt",
        field: "current_debt",
        label: "Deuda restante",
        align: "right",
        sortable: true,
        format: (val, row) => {
            return `$${Math.round((val - row.to_pay) * 100) / 100}`;
        },
    },
];

const rows = ref([]);
const searched = ref(false);
const createdBy = ref([]);
const loadingCreatedBy = ref(false);

onMounted(() => {
    getCreatedBy();
    onRequest();
});

watch(
    () => pagination.value.discountDate,
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
    let data = await getDiscountsBySeller(props.seller_id, pagination.value);
    if (data !== null) {
        rows.value = data.data;
        pagination.value.rowsNumber = data.total;
    } else {
        rows.value = [];
    }
    loading.value = false;

    emits(
        "loaded",
        rows.value.reduce((t, p) => t + parseFloat(p.discount), 0)
    );
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
