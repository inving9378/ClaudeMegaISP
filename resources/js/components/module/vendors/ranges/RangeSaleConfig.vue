<template>
    <h3 class="text-center mb-4">Actualizar rangos de venta</h3>
    <div class="q-pa-md">
        <q-card>
            <q-card-section
                class="d-flex"
                style="justify-content: space-between"
            >
                <div class="text-h6">Lista</div>

                <!-- <a
                    href="/administracion/user/crear?role=vendedor"
                    class="btn btn-success waves-effect waves-light"
                >
                    Agregar Vendedor
                </a> -->
            </q-card-section>
            <q-table
                v-table-resizable
                :dark="darkMode"
                :rows="data"
                :columns="columns"
                :pagination="pagination"
                :rows-per-page-label="'Elementos por página'"
                binary-state-sort
                :loading="loading"
                no-data-label="No hay elementos para mostrar"
            >
                <template v-slot:top="props">
                    <div class="d-flex justify-content-end">
                        <!-- <button
                            type="button"
                            class="btn btn-outline-info"
                            data-bs-toggle="modal"
                            data-bs-target="#modaleditcolumn"
                            style="margin-left: auto; margin-right: 10px"
                        >
                            ...
                        </button> -->
                        <button
                            class="btn btn-outline-secondary"
                            @click="getListRanges"
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
                    </div>
                </template>
                <template v-slot:body-cell-actions="props">
                    <div class="d-flex justify-content-center gap-2">
                        <q-btn
                            flat
                            round
                            color="primary"
                            icon="edit"
                            size="sm"
                            @click="getRangeById(props.row.id)"
                        />
                    </div>
                </template>
            </q-table>
        </q-card>
    </div>
    <modal
        :show="showModalEdit"
        :size="'md'"
        @update:show="showModalEdit = $event"
        title="Editar"
    >
        <template #body>
            <div class="form-group">
                <div class="mb-3">
                    <label for="sector">Sector</label>
                    <input
                        type="text"
                        class="form-control"
                        id="sector"
                        v-model="rangeData.sector"
                        required
                    />
                </div>
                <div class="mb-3">
                    <label for="range">Rango</label>
                    <input
                        type="text"
                        class="form-control"
                        id="range"
                        v-model="rangeData.range"
                        required
                    />
                </div>
                <div class="mb-3">
                    <label for="number_of_prospects"
                        >Número de prospectos</label
                    >
                    <input
                        type="number"
                        class="form-control"
                        id="number_of_prospects"
                        v-model="rangeData.number_of_prospects"
                        required
                    />
                </div>
                <div class="mb-3">
                    <label for="number_of_sales">Número de ventas</label>
                    <input
                        type="number"
                        class="form-control"
                        id="number_of_sales"
                        v-model="rangeData.number_of_sales"
                        required
                    />
                </div>
            </div>
        </template>
        <template #footer>
            <button class="btn btn-primary" @click="updateRange">
                Guardar
            </button>
        </template>
    </modal>
</template>

<script setup>
import { ref, onMounted, reactive } from "vue";
import Modal from "../../../../shared/ModalSimple.vue";
import Swal from "sweetalert2";
import { getAll, editRange, update } from "./helper/helper.js";
import { darkMode } from "../../../../hook/appConfig.js";

const columns = [
    {
        name: "id",
        label: "ID",
        align: "left",
        field: "id",
        sortable: true,
    },
    {
        name: "sector",
        label: "Sector",
        align: "left",
        field: "sector",
        sortable: true,
    },
    {
        name: "range",
        label: "Rango",
        align: "left",
        field: "range",
        sortable: true,
    },
    {
        name: "number_of_prospects",
        label: "Número de prospectos",
        align: "center",
        field: "number_of_prospects",
        sortable: true,
    },
    {
        name: "number_of_sales",
        label: "Número de ventas",
        align: "center",
        field: "number_of_sales",
        sortable: true,
    },
    {
        name: "actions",
        label: "Acciones",
        align: "center",
        field: "actions",
    },
];

const data = ref([]);
const loading = ref(false);
const showModalEdit = ref(false);
const rangeData = reactive({
    id: null,
    range: "",
    sector: "",
    number_of_prospects: 0,
    number_of_sales: 0,
});

const pagination = ref({
    descending: false,
    page: 1,
    rowsPerPage: 50,
    rowsNumber: 10,
});

onMounted(() => {
    getListRanges();
});

const getListRanges = async () => {
    try {
        loading.value = true;
        data.value = await getAll();
        loading.value = false;
    } catch (error) {
        console.log(error);
    } finally {
        loading.value = false;
    }
};

const getRangeById = async (idRange) => {
    showModalEdit.value = true;
    try {
        const response = await editRange(idRange);
        rangeData.id = response.id;
        rangeData.sector = response.sector;
        rangeData.range = response.range;
        rangeData.number_of_prospects = response.number_of_prospects;
        rangeData.number_of_sales = response.number_of_sales;
    } catch (error) {
        console.log(error);
    }
};

const updateRange = async (e) => {
    e.preventDefault();

    try {
        const response = await update(rangeData.id, rangeData);
        showModalEdit.value = false;
        getListRanges();
        Swal.fire("¡Actualizado!", response.message, "success");
    } catch (error) {
        Swal.fire("¡Error!", "Ocurrio un error", "warning");
        showModalEdit.value = false;
    }
};
</script>
