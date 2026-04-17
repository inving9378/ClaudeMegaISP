<template>
    <q-list bordered>
        <q-item class="bg-dark text-white">
            <q-item-section avatar>
                <q-icon name="mdi-heart-pulse" class="q-mr-xs" />
            </q-item-section>
            <q-item-section> Puertos de interrupción </q-item-section>
        </q-item>
        <q-item style="padding: 0">
            <q-item-section>
                <q-table
                    :columns="columns"
                    :rows="rows"
                    :dark="darkMode"
                    :loading="loading"
                    flat
                    wrap-cells
                    row-key="id"
                    color="primary"
                    loading-label="Obteniendo datos, por favor espere..."
                    no-data-label="No existen datos disponibles"
                    no-results-label="No se encontraron coincidencias"
                    rows-per-page-label="Tarjetas por página"
                    :pagination="{ rowsPerPage: 0 }"
                    hide-pagination
                >
                    <template v-slot:body-cell-board="props">
                        <q-td class="text-center" :props="props">
                            <q-item-label
                                class="cursor-pointer text-primary"
                                @click="
                                    () =>
                                        emits('change-tab', 'diagnostics', {
                                            board: props.row.board,
                                            port: props.row.port,
                                            olt_id: props.row.olt_id,
                                        })
                                "
                            >
                                {{ props.row.board }}/{{ props.row.port }}
                            </q-item-label>
                        </q-td>
                    </template>
                </q-table>
            </q-item-section>
        </q-item>
    </q-list>
</template>

<script setup>
import { onMounted, onUnmounted, ref, watch } from "vue";
import { getOLTData } from "../../helper/request";
import { message } from "../../../../../helpers/toastMsg";
import { darkMode } from "../../../../../hook/appConfig";

defineOptions({
    name: "PonOutage",
});

const props = defineProps({
    oltId: Number,
});

const emits = defineEmits(["change-tab"]);

const rows = ref([]);

const columns = ref([
    {
        name: "olt_str",
        field: "olt_str",
        label: "Olt",
        align: "left",
        sortable: true,
    },
    {
        name: "board",
        field: "board",
        label: "Board/Puerto",
        align: "center",
        sortable: true,
    },
    {
        name: "total_onus",
        field: "total_onus",
        label: "ONUs",
        align: "center",
        sortable: true,
    },
    {
        name: "los_count",
        field: "los_count",
        label: "LOS",
        align: "center",
        sortable: true,
    },
    {
        name: "power_count",
        field: "power_count",
        label: "Power",
        align: "center",
        sortable: true,
    },
    {
        name: "cause",
        field: "cause",
        label: "Causa",
        align: "left",
        sortable: true,
    },
    {
        name: "latest_status_change_humans",
        field: "latest_status_change_humans",
        label: "Desde",
        align: "center",
        sortable: true,
    },
]);

const loading = ref(false);
let timer;

onMounted(() => {
    loadData();
    timer = setInterval(loadData, 60000);
});

onUnmounted(() => {
    clearInterval(timer);
});

watch(
    () => props.oltId,
    () => {
        loadData();
    }
);

const loadData = async (force = false) => {
    loading.value = true;
    const result = await getOLTData(
        props.oltId ? `/olts/outage-pons/${props.oltId}` : "/olts/outage-pons",
        { force }
    );
    if (result.success) {
        rows.value = result.rows;
    } else {
        message(result.message, "error");
    }
    loading.value = false;
};
</script>
