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

                    <q-card-section
                        v-if="loadError"
                        class="q-pa-none q-mt-md"
                    >
                        <q-banner
                            class="bg-negative text-white rounded-borders"
                            dense
                        >
                            No se pudieron cargar los datos. Intenta de nuevo.
                        </q-banner>
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
                                    <div class="sp-track">
                                        <div
                                            class="sp-fill"
                                            :style="{
                                                width: `${row.percentage}%`,
                                                background:
                                                    barColor(row.percentage),
                                            }"
                                        >
                                            <span class="sp-label"
                                                >{{ row.percentage }}%</span
                                            >
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
// #9990603 — distingue "error de carga" (rojo) de "sin registros" (no-data-label).
const loadError = ref(false);

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

// Color de la barra por valor con MÚLTIPLES paradas que se MEZCLAN suavemente entre sí:
// rojo → naranja → ámbar → amarillo → lima → verde. Se interpola (lerp) el color entre las
// dos paradas adyacentes según el %, así al acercarse al siguiente umbral el color nuevo se
// va fundiendo con el viejo (no hay saltos bruscos).
const COLOR_STOPS = [
    { p: 0, c: [239, 68, 68] }, // rojo    #ef4444
    { p: 20, c: [249, 115, 22] }, // naranja #f97316
    { p: 40, c: [245, 158, 11] }, // ámbar   #f59e0b
    { p: 60, c: [234, 179, 8] }, // amarillo#eab308
    { p: 80, c: [132, 204, 22] }, // lima    #84cc16
    { p: 100, c: [34, 197, 94] }, // verde   #22c55e
];
const barColor = (p) => {
    const v = Math.max(0, Math.min(100, Number(p) || 0));
    let a = COLOR_STOPS[0];
    let b = COLOR_STOPS[COLOR_STOPS.length - 1];
    for (let i = 0; i < COLOR_STOPS.length - 1; i++) {
        if (v >= COLOR_STOPS[i].p && v <= COLOR_STOPS[i + 1].p) {
            a = COLOR_STOPS[i];
            b = COLOR_STOPS[i + 1];
            break;
        }
    }
    const t = b.p === a.p ? 0 : (v - a.p) / (b.p - a.p);
    const mix = a.c.map((ca, i) => Math.round(ca + (b.c[i] - ca) * t));
    return `rgb(${mix[0]}, ${mix[1]}, ${mix[2]})`;
};

onMounted(() => {
    getData();
});

watch(date, () => {
    getData();
});

const getData = async () => {
    loading.value = true;
    const data = await prospectsByStatus(props.id, date.value);
    if (data === null) {
        loadError.value = true;
        rows.value = [];
    } else {
        loadError.value = false;
        rows.value = data;
    }
    loading.value = false;
};
</script>

<style scoped>
/* Barra de porcentaje con clases propias — esquiva la regla GLOBAL .progress/.progress-bar
   de MegaFamilia (height 6px + fondo gris) que rompía el color y el alto de la barra. */
.sp-track {
    width: 100%;
    height: 24px;
    background: rgba(148, 163, 184, 0.25);
    border-radius: 8px;
    overflow: hidden;
}
.sp-fill {
    height: 100%;
    min-width: 2.6rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: width 0.4s ease;
}
.sp-label {
    color: #fff;
    font-weight: 600;
    font-size: 12px;
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.25);
}
</style>
