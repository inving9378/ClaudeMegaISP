<template>
    <div class="row q-mb-md">
        <div class="col text-right">
            <button
                type="button"
                class="btn btn-success waves-effect waves-light"
                data-bs-toggle="modal"
                @click="() => handleAction()"
                v-if="hasPermission.data.canView(`add_data_plan_promotion`)"
            >
                Agregar
            </button>
        </div>
    </div>

    <q-table
        v-table-resizable="visibleColumns"
        :columns="visibleColumns"
        :rows="rows"
        :loading="loading"
        :dark="darkMode"
        :filter="pagination.search"
        title="Listado de promociones"
        wrap-cells
        row-key="id"
        color="primary"
        loading-label="Obteniendo datos, por favor espere..."
        no-data-label="No existen registros disponibles"
        no-results-label="No se encontraron coincidencias"
        rows-per-page-label="Registros por página"
        :pagination-label="(start, end, total) => `${start}-${end} de ${total}`"
        :rows-per-page-options="[5, 10, 20, 30, 50, 100]"
        v-model:pagination="pagination"
        @request="onRequest"
    >
        <template v-slot:top-right="props">
            <div class="row no-padding">
                <div class="col col-auto">
                    <visible-columns
                        :columns="columns"
                        :table-id="tableId"
                        @update-columns="
                            (cols) => {
                                columns = cols;
                                onRequest();
                            }
                        "
                    />
                    <q-btn
                        icon="sync"
                        outline
                        padding="8px"
                        color="info"
                        class="q-ml-sm"
                        @click="() => onRequest()"
                    />
                </div>
                <div class="col col-auto no-padding">
                    <q-input
                        dense
                        outlined
                        debounce="300"
                        v-model="pagination.search"
                        placeholder="Filtrar"
                        :dark="darkMode"
                        style="width: 300px"
                    >
                        <template v-slot:append>
                            <q-icon name="search" />
                        </template>
                    </q-input>
                </div>
                <div class="col col-auto">
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
                    />
                    <q-btn
                        color="primary"
                        label="Exportar"
                        no-caps
                        @click="
                            exportData({
                                columns: visibleColumns.filter(
                                    (c) => c.name !== 'actions'
                                ),
                                params: pagination,
                                url: '/configuracion/data-plan-promotions/data',
                            })
                        "
                    />
                </div>
            </div>
        </template>
        <template v-slot:body-cell-actions="props">
            <td class="text-center" style="width: 50px">
                <q-btn
                    icon="far fa-edit"
                    dense
                    flat
                    color="primary"
                    size="xs"
                    @click="() => handleAction(props.row)"
                    v-if="
                        hasPermission.data.canView(`edit_data_plan_promotion`)
                    "
                />
                <q-btn
                    icon="far fa-trash-alt"
                    dense
                    flat
                    color="negative"
                    size="xs"
                    @click="() => destroy(props.row.id)"
                    v-if="
                        hasPermission.data.canView(`delete_data_plan_promotion`)
                    "
                />
            </td>
        </template>
    </q-table>

    <data-plan-promotion-form
        :show="showDialog"
        :profiles="profiles"
        :data="currentRow"
        @created="() => onRequest()"
        @updated="() => onRequest()"
        @hide="
            () => {
                showDialog = false;
                currentRow = null;
            }
        "
    />
</template>

<script setup>
import Datatable from "../../../base/shared/Datatable";
import { computed, onBeforeMount, onMounted, reactive, ref, watch } from "vue";
import DatatableHelper from "../../../../helpers/datatableHelper";
import DataPlanPromotionForm from "./DataPlanPromotionForm.vue";
import { useDataTable } from "../../../../composables/useDataTable";
import VisibleColumns from "../../../../shared/VisibleColumns.vue";
import { darkMode } from "../../../../hook/appConfig";
import Swal from "sweetalert2";
import { hideLoading, showLoading } from "../../../../helpers/loading";
import { message } from "../../../../helpers/toastMsg";
import Permission from "../../../../helpers/Permission";
import { allViewHasPermission } from "../../../../helpers/Request";

defineOptions({
    name: "DataPlanPromotionTable",
});

const props = defineProps({
    profiles: {
        type: Array,
        default: [],
    },
});

const hasPermission = reactive({
    data: new Permission({}),
});

const showDialog = ref(false);
const title = ref("Crear promoción");
const datatable = reactive({
    table: new DatatableHelper({}),
});
const action = ref("/configuracion/data-plan-promotions/add");
const reloadCrud = ref(true);
const loading = ref(false);
const tableId = "setting-data-plan-promotion";
const currentRow = ref(null);

const { saveColumns, getColumns, exportData } = useDataTable();
const columns = ref([
    {
        name: "name",
        field: "name",
        label: "Nombre",
        align: "left",
        sortable: true,
    },
    {
        name: "upload",
        field: "upload",
        label: "Subida",
        align: "left",
        sortable: true,
    },
    {
        name: "download",
        field: "download",
        label: "Bajada",
        align: "left",
        sortable: true,
    },
    {
        name: "caduced",
        field: "caduced",
        label: "Caducidad",
        align: "left",
        sortable: false,
    },
]);

const rows = ref([]);
const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 20,
    rowsNumber: 1,
    search: null,
    export: false,
});

onBeforeMount(async () => {
    hasPermission.data = new Permission(await allViewHasPermission());
    if (
        hasPermission.data.canView(`edit_data_plan_promotion`) ||
        hasPermission.data.canView(`delete_data_plan_promotion`)
    ) {
        columns.value.push({
            name: "actions",
            field: "actions",
            label: "Acciones",
            align: "left",
            sortable: false,
            style: "width:50px;",
            headerClasses: "text-center",
        });
    }
});

onMounted(async () => {
    await getColumnsTable();
    await onRequest();
});

watch(
    () => pagination.value.search,
    () => {
        onRequest();
    }
);

const getColumnsTable = async () => {
    const storedColumns = await getColumns(tableId);
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
};

const onRequest = async (attrs) => {
    if (attrs) {
        pagination.value = attrs.pagination;
    }
    loading.value = true;
    await axios
        .post("/configuracion/data-plan-promotions/data", {
            ...pagination.value,
            columns: columns.value.filter((c) => c.visible).map((c) => c.name),
        })
        .then((res) => {
            let { objects, total } = res.data;
            rows.value = objects;
            pagination.value.rowsNumber = total;
        })
        .catch(() => {
            rows.value = [];
        })
        .finally(() => {
            loading.value = false;
        });
};

const visibleColumns = computed(() =>
    columns.value.filter((column) => column.visible)
);

const handleAction = (row = null) => {
    currentRow.value = row;
    showDialog.value = true;
};

const destroy = (id) => {
    Swal.fire({
        title: "Esta seguro que desea eliminar?",
        text: "No podrás deshacer esta acción.",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, continuar",
        cancelButtonText: "Cancelar",
    }).then(async (result) => {
        if (result.isConfirmed) {
            showLoading("showTextDef");
            await axios
                .delete(`/configuracion/data-plan-promotions/destroy/${id}`)
                .then((response) => {
                    message("Promoción eliminada correctamente");
                    onRequest();
                })
                .catch((error) => {
                    const backendMessage =
                        error?.response?.data?.message ??
                        error?.response?.data?.error ??
                        "";
                    const isForeignKeyError =
                        typeof backendMessage === "string" &&
                        backendMessage.includes("SQLSTATE[23000]");

                    if (isForeignKeyError) {
                        message(
                            "No se puede eliminar este elemento porque tiene registros asociados. Elimine primero los elementos relacionados",
                            "error"
                        );
                    } else {
                        message(
                            backendMessage || "Ocurrió un error inesperado.",
                            "error"
                        );
                    }
                })
                .finally(() => {
                    hideLoading();
                });
        }
    });
};
</script>

<style scoped></style>
