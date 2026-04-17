<template>
    <chart-card title="Estado de los prospectos">
        <template #chart>
            <div style="min-height: 365px">
                <q-card flat>
                    <q-card-section class="q-pa-none">
                        <label class="form-label mt-3"
                            >Filtrar por rango de fecha</label
                        ><br />
                        <VueDatePicker
                            v-model="date"
                            position="left"
                            locale="es"
                            :max-date="new Date()"
                            min-date="2024/06/01"
                            :teleport="true"
                            placeholder="Selecciona un rango de fecha"
                            range
                            :enableTimePicker="false"
                        />
                    </q-card-section>

                    <q-card-section class="q-pa-none q-mt-md">
                        <q-table
                            v-table-resizable
                            flat
                            :rows="rows"
                            :columns="columns"
                            :loading="loading"
                            :dark="darkMode"
                            row-key="name"
                            rows-per-page-label="Elementos por página"
                            :rows-per-page-options="[5, 10]"
                            no-data-label="No hay elementos para mostrar"
                            loading-label="Obteniendo datos, por favor espere..."
                            no-results-label="No se encontraron coincidencias"
                            :pagination-label="
                                (start, end, total) =>
                                    `${start}-${end} de ${total}`
                            "
                        >
                            <template v-slot:body-cell-percentage="{ row }">
                                <q-td>
                                    <div class="progress" style="height: 25px">
                                        <div
                                            class="progress-bar"
                                            role="progressbar"
                                            :style="{
                                                width: `${row.percentage}%`,
                                            }"
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
                    </q-card-section>
                </q-card>
            </div>
        </template>
    </chart-card>
</template>

<script setup>
import { ref, onMounted, defineProps, watch } from "vue";
import VueDatePicker from "@vuepic/vue-datepicker";
import "@vuepic/vue-datepicker/dist/main.css";
import ChartCard from "../../../base/card/chart/ChartCard.vue";
import { prospectsByStatus } from "./helper/request.js";
import { darkMode } from "../../../../hook/appConfig.js";

const rows = ref([]);
const date = ref();

const props = defineProps({
    id: {
        type: Number,
        default: null,
    },
});
const loading = ref(false);
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
    getData();
});

watch(date, () => {
    getData();
});

const getData = async () => {
    loading.value = true;
    rows.value = await prospectsByStatus(props.id, date.value);
    loading.value = false;
};
</script>
