<template>
    <div class="q-pa-md">
        <Breadcrumb :list="breadcrumbList" />
        <q-card>
            <q-card-section
                class="d-flex"
                style="justify-content: space-between"
            >
                <div class="text-h6">Listado de vendedores</div>

                <a
                    href="/administracion/user/crear?role=vendedor"
                    class="btn btn-success waves-effect waves-light ms-auto"
                >
                    Agregar Vendedor
                </a>
            </q-card-section>
            <q-table
                v-table-resizable="visibleColumns"
                :rows="data"
                :columns="visibleColumns"
                :filter="filter"
                :dark="darkMode"
                :rows-per-page-label="'Elementos por página'"
                :rows-per-page-options="rowPerPageOptions"
                v-model:pagination="pagination"
                binary-state-sort
                :loading="loading"
                no-data-label="No hay elementos para mostrar"
            >
                <template v-slot:top="props">
                    <div class="d-flex justify-content-end">
                        <button
                            type="button"
                            class="btn btn-outline-info"
                            @click="showModal = true"
                        >
                            ...
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
                <template v-slot:body-cell-name="props">
                    <q-td :props="props">
                        <a
                            :href="
                                '/vendedores/' +
                                props.row.seller_id +
                                '/seguimiento-vendedor/' +
                                props.row.id
                            "
                            >{{ props.row.name }}</a
                        >
                    </q-td>
                </template>
                <template v-slot:body-cell-type="props">
                    <q-td :props="props">
                        <span :class="'badge-' + props.row.type">{{
                            props.row.type
                        }}</span>
                    </q-td>
                </template>
                <template v-slot:body-cell-status_seller="props">
                    <q-td :props="props">
                        <span :class="'badge-' + props.row.status_seller">{{
                            props.row.status_seller
                        }}</span>
                    </q-td>
                </template>
                <template v-slot:body-cell-balance="props">
                    <q-td :props="props">
                        $
                        <b>{{ props.row.balance }}</b>
                    </q-td>
                </template>
            </q-table>
        </q-card>

        <!-- Modal -->
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
import { getAll } from "./helper/request.js";
import Modal from "../../../shared/ModalSimple.vue";
import Breadcrumb from "../../base/shared/Breadcrumb.vue";
import { darkMode } from "../../../hook/appConfig.js";
import { useDataTable } from "../../../composables/useDataTable.js";

const props = defineProps({
    id: Number,
    seller_id: Number,
});

const columns = ref([
    {
        name: "seller_id",
        required: true,
        label: "ID",
        align: "left",
        field: "seller_id",
        sortable: true,
        visible: true,
    },
    {
        name: "type",
        required: true,
        label: "Tipo",
        align: "left",
        field: "type",
        sortable: true,
        visible: true,
    },
    {
        name: "name",
        required: true,
        label: "Nombre",
        align: "left",
        field: "name",
        sortable: true,
        visible: true,
    },
    {
        name: "father_last_name",
        align: "left",
        label: "Apellido paterno",
        field: "father_last_name",
        sortable: true,
        visible: true,
    },
    {
        name: "mother_last_name",
        align: "left",
        label: "Apellido materno",
        field: "mother_last_name",
        sortable: true,
        visible: true,
    },
    {
        name: "address",
        align: "left",
        label: "Dirección",
        field: "address",
        sortable: true,
        visible: true,
    },
    {
        name: "city_municipality",
        align: "start",
        label: "Municipio",
        field: "city_municipality",
        sortable: true,
        visible: true,
    },
    {
        name: "state_country",
        align: "left",
        label: "Estado",
        field: "state_country",
        sortable: true,
        visible: true,
    },
    {
        name: "phone",
        align: "left",
        label: "Teléfono",
        field: "phone",
        sortable: true,
        visible: true,
    },
    {
        name: "balance",
        align: "right",
        label: "Saldo del vendedor",
        field: "balance",
        sortable: true,
        visible: true,
    },
    {
        name: "rfc",
        align: "center",
        label: "RFC",
        field: "rfc",
        sortable: true,
        visible: true,
    },
    {
        name: "status_seller",
        align: "left",
        label: "Status",
        field: "status_seller",
        sortable: true,
        visible: true,
    },
]);

const data = ref([]);
const rowPerPageOptions = ref([5, 10, 15, 25, 50, 100, 0]);
const loading = ref(false);
const filter = ref("");
const showModal = ref(false);
const tableIdentifier = ref("vendedores");
const { getColumns, saveColumns } = useDataTable();

const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 50,
    rowsNumber: 10,
});

const breadcrumbList = ref([
    { title: "Dashboard", a: "/vendedores/dashboard" },
    { title: "Vendedores", a: "/vendedores" },
]);

onMounted(() => {
    getColumnsTable();
    getSellers();
});

const getSellers = async () => {
    try {
        loading.value = true;
        data.value = await getAll();
        loading.value = false;
    } catch (error) {
        loading.value = false;
        console.log(error);
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
</script>

<style scoped>
.badge-Interno {
    background-color: #d7f5fc;
    border-color: #b3edf9;
    color: #03c3ec;
    padding: 0 15px;
    padding-top: 5px;
    padding-bottom: 5px;
    border-radius: 5px;
}

.badge-Externo {
    background-color: #e7e7ff;
    border-color: #d2d3ff;
    color: #696cff;
    padding: 0 15px;
    padding-top: 5px;
    padding-bottom: 5px;
    border-radius: 5px;
}

.badge-Distribuidor {
    background-color: #dcdfe1;
    border-color: #bdc2c8;
    color: #233446;
    padding: 0 15px;
    padding-top: 5px;
    padding-bottom: 5px;
    border-radius: 5px;
}

.badge-Activo {
    background-color: #dff7d3;
    border-color: #d4f5c3;
    color: #71dd37;
    padding: 0 15px;
    padding-top: 5px;
    padding-bottom: 5px;
    border-radius: 5px;
}

.badge-Inactivo {
    background-color: #fff2d6;
    border-color: #ffe6b3;
    color: #ffab00;
    padding: 0 15px;
    padding-top: 5px;
    padding-bottom: 5px;
    border-radius: 5px;
}

.badge-Bloqueado {
    background-color: #ffe0db;
    border-color: #ffc5bb;
    color: #ff3e1d;
    padding: 0 15px;
    padding-top: 5px;
    padding-bottom: 5px;
    border-radius: 5px;
}
</style>
