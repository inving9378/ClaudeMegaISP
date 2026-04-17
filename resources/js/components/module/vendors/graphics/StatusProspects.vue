<template>
    <chart-card title="Estado de los prospectos">
        <template #chart>
            <div class="q-pa-md" style="min-height: 365px">
                <q-card>
                    <div class="mx-4 mt-2">
                        <label class="form-label mt-3"
                            >Filtrar por rango de fecha</label
                        >
                        <q-card-section class="d-flex" style="gap: 20px">
                            <VueDatePicker
                                v-model="date"
                                position="left"
                                locale="es"
                                :max-date="new Date()"
                                :teleport="true"
                                placeholder="Selecciona un rango de fecha"
                                range
                            >
                            </VueDatePicker>
                            <button
                                type="button"
                                class="btn btn-primary"
                                @click="getData"
                            >
                                Buscar
                            </button>
                        </q-card-section>
                    </div>

                    <q-table
                        v-table-resizable
                        :dark="darkMode"
                        :rows="rows"
                        :columns="columns"
                        row-key="name"
                        :rows-per-page-label="'Elementos por página'"
                        :rows-per-page-options="rowPerPageOptions"
                        no-data-label="No hay elementos para mostrar"
                    >
                        <template v-slot:body-cell-percentage="{ row }">
                            <q-td>
                                <div class="progress" style="height: 25px">
                                    <div
                                        class="progress-bar"
                                        role="progressbar"
                                        :style="{ width: `${row.percentage}%` }"
                                        aria-valuenow="25"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                    >
                                        <div
                                            class="d-flex justify-content-center align-items-center"
                                            style="color: white"
                                        >
                                            {{ row.percentage }}%
                                        </div>
                                    </div>
                                </div>
                            </q-td>
                        </template>
                    </q-table>
                </q-card>
            </div>
        </template>
    </chart-card>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { prospectsByStatus } from "../helper/request.js";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import ChartCard from "../../../base/card/chart/ChartCard.vue";
import { darkMode } from "../../../../hook/appConfig.js";

const rows = ref([]);
const rowPerPageOptions = ref([5, 10]);
const date = ref();

const columns = [
    {
        name: "crm_status",
        align: "center",
        label: "Status",
        field: "crm_status",
        sortable: true,
    },
    {
        name: "total",
        align: "center",
        label: "Conteo",
        field: "total",
        sortable: true,
    },
    {
        name: "percentage",
        align: "center",
        label: "Porcentaje",
        field: "percentage",
        sortable: true,
    },
];

onMounted(() => {
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);
    const endDate = new Date();
    date.value = [startDate, endDate];

    getData();
});

const getData = async () => {
    try {
        rows.value = await prospectsByStatus(
            date.value[0].toISOString().slice(0, 10),
            date.value[1].toISOString().slice(0, 10)
        );
    } catch (error) {
        console.log(error);
    }
};
</script>
