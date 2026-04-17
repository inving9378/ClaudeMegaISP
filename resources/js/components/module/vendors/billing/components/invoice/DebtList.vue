<template>
    <q-table
        v-table-resizable="visibleColumns"
        :columns="visibleColumns"
        :rows="rows"
        :loading="loading"
        :dark="darkMode"
        wrap-cells
        row-key="id"
        color="primary"
        loading-label="Obteniendo deudas, por favor espere..."
        no-data-label="No existen deudas disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Deudas por página"
        :pagination-label="(start, end, total) => `${start}-${end} de ${total}`"
        :rows-per-page-options="[5, 10, 20, 30, 50, 100]"
        :pagination="{
            rowsPerPage: 20,
        }"
        selection="multiple"
        v-model:selected="selected"
        :selected-rows-label="getSelectedString"
    >
        <template v-slot:top>
            <div class="d-flex justify-content-end align-items-center">
                <q-btn
                    color="primary"
                    class="q-mr-sm"
                    label="..."
                    @click="showModal = true"
                />
                <q-btn
                    label="Actualizar"
                    no-caps
                    :loading="loading"
                    @click="loadData"
                    color="primary"
                    class="q-mr-sm"
                />
                <charger-form
                    :seller_id="seller_id"
                    :charger-list="selected"
                    :disabled="selected.length === 0 || loading"
                    @created="onCreatedDiscount"
                    v-if="hasEdit"
                />
            </div>
        </template>
        <template v-slot:body-cell-amount="props">
            <q-td :props="props">
                <first-payments
                    :payments="props.row.amount"
                    :data="props.row.payments"
                    :client="`${props.row.client} - ${props.row.client_id}`"
                    :badge="false"
                />
            </q-td> </template
    ></q-table>

    <div class="row text-right no-gutter-x q-pa-sm">
        <div class="col">
            <q-btn
                color="primary"
                class="q-mr-sm"
                label="..."
                @click="showModal = true"
            />
            <q-btn
                label="Actualizar"
                no-caps
                :loading="loading"
                @click="loadData"
                color="primary"
                class="q-mr-sm"
            />
            <charger-form
                :seller_id="seller_id"
                :charger-list="selected"
                :disabled="selected.length === 0 || loading"
                @created="onCreatedDiscount"
                v-if="hasEdit"
            />
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
import { watch, ref, onMounted, computed } from "vue";
import { getPendingDiscountsBySeller, fieldsRules } from "../../helper/helper";
import ChargerForm from "./ChargerForm.vue";
import FirstPayments from "../FirstPayments.vue";
import { darkMode } from "../../../../../../hook/appConfig";
import modal from "../../../../../../shared/ModalSimple.vue";
import { useDataTable } from "../../../../../../composables/useDataTable";

const props = defineProps({
    user: String | Number,
    seller_id: {
        type: Number,
        required: true,
    },
    hasEdit: {
        type: Boolean,
        default: false,
    },
});

const emits = defineEmits(["hide", "loaded"]);

const showModal = ref(false);
const reloadBalance = ref(false);
const loading = ref(false);
const selected = ref([]);
const tableIdentifier = ref("billing-dbts");
const { getColumns, saveColumns } = useDataTable();

const columns = ref([
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
        name: "amount_by_seller",
        field: "amount_by_seller",
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
        format: (val) => {
            return `$${Math.round(val * 100) / 100}`;
        },
    },
]);

const rows = ref([]);

onMounted(() => {
    getColumnsTable();
    loadData();
});

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

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);

const loadData = async () => {
    loading.value = true;
    let data = await getPendingDiscountsBySeller(props.user);
    rows.value = data;
    loading.value = false;
    emits(
        "loaded",
        rows.value.reduce((t, p) => t + parseFloat(p.current_debt), 0)
    );
};

const getSelectedString = () => {
    return selected.value.length === 0
        ? ""
        : `${selected.value.length} deuda${
              selected.value.length > 1 ? "s" : ""
          } seleccionadas de ${rows.value.length}`;
};

const onCreatedDiscount = () => {
    selected.value = [];
    loadData();
    reloadBalance.value = true;
};
</script>
<style scoped>
.q-field__append.row > button.q-icon {
    padding: 0px;
}
</style>
