<template>
    <div class="vnd-wrap">
        <div class="d-flex align-items-center gap-2 mb-3 mt-3">
            <i class="bi bi-cash-coin fs-4"></i>
            <h1 class="h4 fw-bold mb-0">Comisiones de los vendedores</h1>
        </div>
        <div class="q-pa-md">
            <q-card class="vnd-card">
                <q-card-section
                    class="d-flex"
                    style="justify-content: space-between"
                >
                    <div class="vnd-title">Reglas de comisión</div>
                </q-card-section>

                <q-table
                    v-table-resizable="visibleColumns"
                    row-key="id"
                    v-model:pagination="pagination"
                    ref="tableRef"
                    no-data-label="No hay elementos para mostrar"
                    :dark="darkMode"
                    :rows="rules"
                    :columns="visibleColumns"
                    :loading="loading"
                    :rows-per-page-label="'Elementos por página'"
                    :rows-per-page-options="rowPerPageOptions"
                    :filter="filter"
                    @request="getListRules"
                    style="max-height: 70vh"
                >
                    <template v-slot:body-cell-actions="props">
                        <div class="d-flex justify-content-center">
                            <span class="text-primary me-2" role="button">
                                <a
                                    :href="
                                        'reglas-comisiones/editar/' +
                                        props.row.id
                                    "
                                >
                                    <i class="fas fa-edit"></i>
                                </a>
                            </span>
                            <span
                                class="text-primary"
                                role="button"
                                @click="deleteRuleOfVendor(props.row.id)"
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
                                @click="showModal = true"
                            >
                                ...
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

                            <a
                                href="/configuracion/reglas-comisiones/crear"
                                class="btn btn-success waves-effect waves-light"
                            >
                                Agregar nueva regla
                            </a>
                        </div>
                    </template>
                    <template v-slot:body-cell-name="props">
                        <q-td :props="props"
                            ><b>{{ props.row.name }}</b></q-td
                        >
                    </template>
                    <template v-slot:body-cell-amount="props">
                        <q-td :props="props"> $ {{ props.row.amount }} </q-td>
                    </template>
                    <template v-slot:body-cell-fixed_sales_commission="props">
                        <q-td :props="props">
                            $ {{ props.row.fixed_sales_commission }}
                        </q-td>
                    </template>
                    <template v-slot:body-cell-commission_percentage="props">
                        <q-td :props="props">
                            {{ props.row.commission_percentage }}%
                        </q-td>
                    </template>
                    <template v-slot:body-cell-total_bonus="props">
                        <q-td :props="props">
                            $ {{ props.row.total_bonus }}
                        </q-td>
                    </template>
                    <template v-slot:body-cell-installation_cost="props">
                        <q-td :props="props">
                            $ {{ props.row.installation_cost }}
                        </q-td>
                    </template>
                    <template v-slot:body-cell-sellers_count="props">
                        <q-td :props="props">
                            <b
                                ><a
                                    :href="
                                        '/configuracion/reglas-comisiones/vendedores/' +
                                        props.row.id
                                    "
                                    >{{ props.row.sellers_count }}</a
                                ></b
                            >
                        </q-td>
                    </template>
                    <template v-slot:body-cell-period="props">
                        <q-td :props="props">
                            <span class="vnd-badge vnd-badge-info">
                                {{ props.row.period }}
                            </span>
                        </q-td>
                    </template>
                </q-table>
            </q-card>
        </div>
        <!-- ----------------------------------------------------------------------->
        <modal
            :show="showModal"
            :size="'xs'"
            @update:show="showModal = $event"
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
import { ref, onMounted, computed } from "vue";
import Swal from "sweetalert2";
import Modal from "../../../../shared/ModalSimple.vue";
import { getAllRules, deleteVendorRule } from "./helper/helper";
import { darkMode } from "../../../../hook/appConfig";
import { useDataTable } from "../../../../composables/useDataTable";

const columns = ref([
    {
        name: "id",
        label: "ID",
        align: "left",
        field: "id",
        sortable: true,
        visible: true,
    },
    {
        name: "name",
        label: "Nombre de la regla",
        align: "left",
        field: "name",
        sortable: true,
        visible: true,
    },
    {
        name: "zone",
        label: "Zona",
        align: "left",
        field: "zone",
        sortable: true,
        visible: true,
    },
    {
        name: "amount",
        label: "Sueldo",
        align: "left",
        field: "amount",
        sortable: true,
        visible: true,
    },
    {
        name: "number_of_prospects",
        label: "Número de prospectos requeridos",
        align: "center",
        field: "number_of_prospects",
        sortable: true,
        visible: true,
    },
    {
        name: "minimum_sales",
        label: "Minimo de ventas",
        align: "center",
        field: "minimum_sales",
        sortable: true,
        visible: true,
    },
    {
        name: "fixed_sales_commission",
        label: "Comision por venta (Fija)",
        align: "left",
        field: "fixed_sales_commission",
        sortable: true,
        visible: true,
    },
    {
        name: "commission_percentage",
        label: "Comision por venta (Porcentaje)",
        align: "center",
        field: "commission_percentage",
        sortable: true,
        visible: true,
    },
    {
        name: "period",
        label: "Periodo",
        align: "left",
        field: "period",
        sortable: true,
        visible: true,
    },
    {
        name: "commission_percentage_additional",
        label: "Comisión por venta adicional (Porcentaje)",
        align: "left",
        field: "commission_percentage_additional",
        sortable: true,
        visible: true,
    },
    {
        name: "fixed_sales_commission_additional",
        label: "Comisión por venta adicional (Fija)",
        align: "left",
        field: "fixed_sales_commission_additional",
        sortable: true,
        visible: true,
    },
    {
        name: "total_bonus",
        label: "Bono mensual",
        align: "left",
        field: "total_bonus",
        sortable: true,
        visible: true,
    },
    {
        name: "number_sales_required",
        label: "Número de ventas para bono mensual",
        align: "center",
        field: "number_sales_required",
        sortable: true,
        visible: true,
    },
    {
        name: "installation_cost",
        label: "Costo de instalacion",
        align: "left",
        field: "installation_cost",
        sortable: true,
        visible: true,
    },
    {
        name: "sellers_count",
        label: "Vendedores",
        align: "center",
        field: "sellers_count",
        sortable: true,
        visible: true,
    },
    {
        name: "actions",
        label: "Acciones",
        align: "center",
        field: "actions",
        visible: true,
    },
]);

const rules = ref([]);
const rowPerPageOptions = ref([5, 10, 15, 25, 50, 100, 0]);
const loading = ref(false);
const filter = ref("");
const showModal = ref(false);
const tableIdentifier = ref("reglas-comisiones");
const { getColumns, saveColumns } = useDataTable();

const pagination = ref({
    page: 1,
    rowsPerPage: 50,
    rowsNumber: 0,
});

onMounted(() => {
    getColumnsTable();
    tableRef.value.requestServerInteraction();
});

const getListRules = async ({ pagination: { page, rowsPerPage } }) => {
    loading.value = true;

    try {
        const { data, total } = await getAllRules(page, rowsPerPage);

        rules.value.splice(0, rules.value.length, ...data);

        pagination.value.page = page;
        pagination.value.rowsPerPage = rowsPerPage;
        pagination.value.rowsNumber = total;
    } catch (error) {
        console.error("Error in onRequest:", error);
    } finally {
        loading.value = false;
    }
};

const deleteRuleOfVendor = async (id) => {
    try {
        const confirmed = await Swal.fire({
            title: "Confirmar eliminación",
            text: "¿Está seguro de que desea eliminar la regla?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Sí, Eliminar",
            cancelButtonText: "Cancelar",
        });

        if (confirmed.isConfirmed) {
            const response = await deleteVendorRule(id);

            Swal.fire({
                title: "Eliminado",
                text: response.message,
                icon: "success",
            });

            reloadTable();
        }
    } catch (error) {
        console.log(error);
        Swal.fire({
            title: "Error",
            text: "Ocurrio un error",
            icon: "error",
        });
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
        showModal.value = false;
    } catch (error) {
        console.log(error);
    }
};

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);

const tableRef = ref();

const reloadTable = () => {
    getListRules({ pagination: pagination.value });
};
</script>

<style scoped>
/* Restyle con el sistema visual de la Torre de Control, tokens locales por componente (mismo
   patrón que VendedorListar.vue, ver resources/sass/base/dark_mode/dark_mode.scss para el
   override global de modo oscuro de badges y q-card, que no se toca aquí). Item #9991079. */
.vnd-wrap {
    --vnd-ink: #111827;
    --vnd-info: #2563eb;
    --vnd-info-bg: #eff6ff;
}

.vnd-title {
    font-size: 1.275rem;
    font-weight: 700;
    color: var(--vnd-ink);
    margin: 0;
}

.vnd-card {
    border-radius: 14px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.vnd-badge {
    display: inline-flex;
    align-items: center;
    font-size: 12.5px;
    font-weight: 600;
    padding: 4px 12px;
    border-radius: 999px;
}

.vnd-badge-info {
    background-color: var(--vnd-info-bg);
    color: var(--vnd-info);
}
</style>
