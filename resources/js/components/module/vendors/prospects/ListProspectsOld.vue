<template>
    <div class="q-pa-md">
        <q-card>
            <q-card-section
                class="d-flex"
                style="justify-content: space-between"
            >
                <div class="text-h6">Lista de Prospectos</div>
                <a
                    :href="'/crm/crear'"
                    class="btn btn-success waves-effect waves-light"
                >
                    Agregar prospecto
                </a>
            </q-card-section>
            <q-table
                v-table-resizable="visibleColumns"
                :dark="darkMode"
                :rows="data"
                :columns="visibleColumns"
                :filter="filter"
                :rows-per-page-label="'Elementos por página'"
                :rows-per-page-options="rowPerPageOptions"
                v-model:pagination="pagination"
                binary-state-sort
                :loading="loading"
                no-data-label="No hay elementos para mostrar"
            >
                <template v-slot:top="props">
                    <div class="d-flex justify-content-end">
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
                <template v-slot:body-cell-No="props">
                    <q-td :props="props">
                        {{ props.pageIndex + 1 }}
                    </q-td>
                </template>
                <template v-slot:body-cell-name="props">
                    <q-td :props="props">
                        <a :href="'/crm/editar/' + props.row.crm_id">{{
                            props.row.name
                        }}</a>
                    </q-td>
                </template>
                <template v-slot:body-cell-father_last_name="props">
                    <q-td :props="props">
                        <a :href="'/crm/editar/' + props.row.crm_id">{{
                            props.row.father_last_name
                        }}</a>
                    </q-td>
                </template>
                <template v-slot:body-cell-mother_last_name="props">
                    <q-td :props="props">
                        <a :href="'/crm/editar/' + props.row.crm_id">{{
                            props.row.mother_last_name
                        }}</a>
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
import { getById } from "./helper/request.js";
import Modal from "../../../../shared/ModalSimple.vue";
import { darkMode } from "../../../../hook/appConfig.js";
import { useDataTable } from "../../../../composables/useDataTable.js";

const props = defineProps({
    id: Number,
});

const columns = ref([
    {
        name: "No",
        align: "start",
        label: "No.",
        field: "No",
        sortable: false,
        visible: true,
    },
    {
        name: "crm_id",
        align: "start",
        label: "Id del prospecto",
        field: "crm_id",
        sortable: true,
        visible: true,
    },
    {
        name: "name",
        align: "start",
        label: "Nombre",
        field: "name",
        sortable: true,
        visible: true,
    },
    {
        name: "father_last_name",
        align: "start",
        label: "Apellido Paterno",
        field: "father_last_name",
        sortable: true,
        visible: true,
    },
    {
        name: "mother_last_name",
        align: "start",
        label: "Apellido Materno",
        field: "mother_last_name",
        sortable: true,
        visible: true,
    },
    {
        name: "phone",
        align: "start",
        label: "Teléfono",
        field: "phone",
        sortable: true,
        visible: true,
    },
    {
        name: "email",
        align: "start",
        label: "Correo Electronico",
        field: "email",
        sortable: true,
        visible: true,
    },
    {
        name: "phone",
        align: "start",
        label: "Teléfono",
        field: "phone",
        sortable: true,
        visible: true,
    },
    {
        name: "street",
        align: "start",
        label: "Dirección",
        field: "street",
        sortable: true,
        visible: true,
    },
    {
        name: "zip",
        align: "start",
        label: "Codigo Postal",
        field: "zip",
        sortable: true,
        visible: true,
    },
    {
        name: "crm_status",
        align: "start",
        label: "Status",
        field: "crm_status",
        sortable: true,
        visible: true,
    },
    {
        name: "last_contacted",
        align: "start",
        label: "Ultimo contacto",
        field: "last_contacted",
        sortable: true,
        visible: true,
    },
    {
        name: "high_date",
        align: "start",
        label: "Fecha de alta",
        field: "high_date",
        sortable: true,
        visible: true,
    },
]);

const data = ref([]);
const rowPerPageOptions = ref([5, 10, 15, 25, 50, 100, 0]);
const loading = ref(false);
const filter = ref("");
const showModal = ref(false);
const tableIdentifier = ref("prospectos");
const { getColumns, saveColumns } = useDataTable();

const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 50,
    rowsNumber: 10,
});

onMounted(() => {
    getColumnsTable();
    getProspects();
});

const getProspects = async () => {
    try {
        loading.value = true;
        data.value = await getById(props.id);
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
